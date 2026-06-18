<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RagService;
use App\Models\BookChunk;
use App\Models\UserJourney;

class JourneyController extends Controller
{
    private RagService $ragService;

    // Estrutura didática de perguntas e macrofases da Jornada do Herói
    private static array $journeyMap = [
        1 => ['phase' => 'Partida / Separação', 'question' => 'O que na sua vida atual parece estagnado ou precisando de mudança?'],
        2 => ['phase' => 'Partida / Separação', 'question' => 'Quais são os seus maiores medos, desculpas ou resistências internas ao pensar nessa mudança?'],
        3 => ['phase' => 'Partida / Separação', 'question' => 'Se você encontrasse um mentor sábio hoje, qual conselho você gostaria que ele te desse sobre esse problema?'],
        4 => ['phase' => 'Iniciação / Provações', 'question' => 'O que você está disposto a abrir mão hoje (uma rotina antiga, um apego) para cruzar o limiar da mudança?'],
        5 => ['phase' => 'Iniciação / Provações', 'question' => 'Descreva um hábito nocivo ou autossabotagem em que você costuma cair quando as coisas ficam difíceis.'],
        6 => ['phase' => 'Iniciação / Provações', 'question' => 'Encare o seu pior defeito (sua Sombra). De que forma esse defeito tenta te proteger nos bastidores?'],
        7 => ['phase' => 'Retorno / Integração', 'question' => 'Qual foi a maior lição prática que essa jornada te trouxe até aqui?'],
        8 => ['phase' => 'Retorno / Integração', 'question' => 'Como você planeja aplicar essa nova consciência na sua vida cotidiana real a partir de amanhã?']
    ];

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Endpoint 1: POST /api/journey/start
     * Inicia ou reinicia a jornada do usuário.
     */
    public function start(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $userId = $request->input('user_id');

        // Limpa apenas o progresso desse usuário específico
        UserJourney::where('user_id', $userId)->delete();

        return response()->json([
            'message' => 'Jornada iniciada com sucesso!',
            'current_day' => 1,
            'phase' => 'Partida / Separação',
            'question' => 'O que na sua vida atual parece estagnado ou precisando de mudança?'
        ], 200); // Retorna Status HTTP 200 OK
    }

    /**
     * Endpoint 2: POST /api/journey/respond
     * Recebe a resposta do usuário, executa o RAG e gera o insight do Mentor.
     */
    public function respond(Request $request)
    {
        // 1. Request Validation (Validação de Entrada)
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'answer' => 'required|string|min:10'
        ]);

        $userId = $request->input('user_id');
        $userAnswer = $request->input('answer');

        // 2. Determina qual o dia atual da jornada do usuário baseando-se no último registro
        $lastEntry = UserJourney::where('user_id', $userId)->latest('id')->first();
        $currentDay = $lastEntry ? $lastEntry->current_day + 1 : 1;

        // Se a jornada anterior já foi marcada como concluída ou passou de 21 dias, bloqueia novas respostas
        if ($lastEntry) {
            $lastInsight = $lastEntry->mentor_insight;
            $isLastFinished = (bool)($lastInsight['finished'] ?? false);
            if ($isLastFinished || $currentDay > 21) {
                return response()->json([
                    'message' => 'Parabéns! Você já concluiu todos os dias da sua jornada do herói.'
                ], 400); // 400 Bad Request
            }
        }

        // Determina a pergunta e a fase que estão sendo respondidas hoje
        if ($currentDay === 1) {
            $currentQuestion = 'O que na sua vida atual parece estagnado ou precisando de mudança?';
            $currentPhase = 'Partida / Separação';
        } else {
            $lastInsight = $lastEntry->mentor_insight;
            $currentQuestion = $lastInsight['next_question'] ?? 'Como você se sente com relação a esse momento?';
            $currentPhase = $lastInsight['next_phase'] ?? $lastInsight['phase'] ?? 'Iniciação / Provações';
        }

        // 3. FLUXO DO RAG (Programação Defensiva)
        $retrievedTexts = [];
        
        try {
            // Conta quantos chunks de livros estão indexados no SQLite
            $totalChunks = BookChunk::count();

            // Só executa a busca vetorial se você já tiver indexado os livros
            if ($totalChunks > 0) {
                // Gera o vetor de embedding da resposta do usuário
                $userEmbedding = $this->ragService->generateEmbedding($userAnswer);

                // Carrega todos os chunks do banco SQLite
                $chunks = BookChunk::all();
                $similarities = [];

                // Compara a resposta do usuário com cada trecho de livro
                foreach ($chunks as $chunk) {
                    $score = $this->ragService->calculateCosineSimilarity($userEmbedding, $chunk->embedding);
                    $similarities[] = [
                        'content' => $chunk->content,
                        'score' => $score
                    ];
                }

                // Ordena os blocos por pontuação de similaridade decrescente (do maior para o menor)
                usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);

                // Filtra os top 3 blocos mais parecidos semântica e filosoficamente
                $topChunks = array_slice($similarities, 0, 3);
                $retrievedTexts = array_column($topChunks, 'content');
            }
        } catch (\Exception $e) {
            // Programação Defensiva: Se o RAG falhar (por exemplo, erro de cota de embedding), 
            // a API não quebra. Ela apenas segue adiante sem trechos adicionais.
            \Illuminate\Support\Facades\Log::warning("Busca de RAG pulada temporariamente: " . $e->getMessage());
        }

        // Recupera o histórico completo de etapas anteriores desse usuário específico
        $history = UserJourney::where('user_id', $userId)->orderBy('current_day', 'asc')->get()->toArray();

        // 4. GERAÇÃO DE INSIGHT VIA IA
        $aiRawText = "";
        try {
            // Chama o Gemini passando a resposta, contextos de RAG, dia atual e histórico
            $aiRawText = $this->ragService->generateInsight($userAnswer, $retrievedTexts, $currentDay, $history);
        } catch (\Exception $e) {
            // Registra o erro detalhado no arquivo laravel.log
            \Illuminate\Support\Facades\Log::error("Erro na geração de insight do Gemini: " . $e->getMessage(), [
                'exception' => $e
            ]);

            // Caso de falha de conexão: fallback no formato JSON correto
            $aiRawText = json_encode([
                'insight' => "O mentor ouviu sua resposta: '{$userAnswer}'. No entanto, a conexão falhou.",
                'meditation' => 'Mantenha a calma e respire profundamente.',
                'challenge' => 'Reflita sobre os seus sentimentos e medite em silêncio por 5 minutos.',
                'phase' => 'Iniciação / Provações',
                'next_question' => 'O que você aprendeu com essa dificuldade técnica de hoje?',
                'finished' => false
            ]);
        }

        // Faz o parsing da resposta do AI (com tolerância a falhas)
        $parsedInsight = $this->parseAiResponse($aiRawText);

        // 5. Salva a resposta e o conselho gerado no banco SQLite
        $entry = UserJourney::create([
            'user_id' => $userId,
            'current_day' => $currentDay,
            'phase' => $currentPhase,
            'question' => $currentQuestion,
            'user_answer' => $userAnswer,
            'mentor_insight' => $parsedInsight
        ]);

        // Retorna a resposta HTTP RESTful
        return response()->json([
            'message' => 'Insight do Mentor gerado com sucesso!',
            'journey_record' => $entry,
            'next_day' => $parsedInsight['finished'] ? null : $currentDay + 1,
            'next_question' => $parsedInsight['finished'] ? null : $parsedInsight['next_question'],
            'next_phase' => $parsedInsight['finished'] ? null : $parsedInsight['next_phase'],
            'finished' => $parsedInsight['finished']
        ], 200);
    }

    /**
     * Auxiliar para decodificar e validar a resposta do Gemini em formato JSON.
     */
    private function parseAiResponse(string $aiText): array
    {
        $cleanText = trim($aiText);

        // Remove marcações markdown de blocos de código se presentes (ex: ```json ... ```)
        if (preg_match('/^```json\s*(.*?)\s*```$/is', $cleanText, $matches)) {
            $cleanText = trim($matches[1]);
        } elseif (preg_match('/\{(?:[^{}]|(?R))*\}/s', $cleanText, $matches)) {
            $cleanText = $matches[0];
        }

        $data = json_decode($cleanText, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return [
                'insight' => $data['insight'] ?? 'O mentor ouviu com atenção e convida você a continuar refletindo.',
                'meditation' => $data['meditation'] ?? 'Foque na respiração e na presença hoje.',
                'challenge' => $data['challenge'] ?? 'Escreva três sentimentos que surgiram hoje.',
                'next_phase' => $data['next_phase'] ?? $data['phase'] ?? 'Iniciação / Provações',
                'next_question' => $data['next_question'] ?? 'Como você se sente com relação a esse momento?',
                'finished' => (bool)($data['finished'] ?? false)
            ];
        }

        // Fallback se o parsing falhar completamente
        return [
            'insight' => $aiText,
            'meditation' => 'Encontre um momento de silêncio para refletir.',
            'challenge' => 'Escreva em seu diário sobre a reflexão de hoje.',
            'next_phase' => 'Iniciação / Provações',
            'next_question' => 'O que mais chamou sua atenção no seu comportamento hoje?',
            'finished' => false
        ];
    }
}
