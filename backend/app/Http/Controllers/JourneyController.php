<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RagService;
use App\Models\BookChunk;
use App\Models\UserJourney;

class JourneyController extends Controller
{
    private RagService $ragService;

    // Estrutura didática de 21 dias clássicos da Jornada do Herói (Campbell + Jung)
    private static array $journeyMap = [
        1 => [
            'phase' => 'Partida / Separação',
            'theme' => 'O Mundo Comum',
            'question' => 'O que na sua vida atual parece estagnado, cômodo demais ou precisando de uma transformação sincera?'
        ],
        2 => [
            'phase' => 'Partida / Separação',
            'theme' => 'O Chamado à Aventura',
            'question' => 'Qual foi o sinal, interno ou externo, que te despertou para a necessidade de mudar isso?'
        ],
        3 => [
            'phase' => 'Partida / Separação',
            'theme' => 'A Recusa do Chamado',
            'question' => 'Quais são as suas maiores desculpas, medos ou resistências internas que você usa para evitar essa mudança?'
        ],
        4 => [
            'phase' => 'Partida / Separação',
            'theme' => 'O Encontro com o Mentor',
            'question' => 'Se você pudesse invocar a sabedoria de um grande mestre (ou da sua própria intuição mais profunda), qual conselho acha que ele te daria hoje?'
        ],
        5 => [
            'phase' => 'Partida / Separação',
            'theme' => 'A Travessia do Primeiro Limiar',
            'question' => 'Qual o primeiro limite prático que você precisa ultrapassar para se afastar do que é cômodo e seguro?'
        ],
        6 => [
            'phase' => 'Partida / Separação',
            'theme' => 'O Ventre da Baleia',
            'question' => 'Ao se desapegar da sua antiga versão, qual a sensação de transição ou de vazio que você experimenta?'
        ],
        7 => [
            'phase' => 'Partida / Separação',
            'theme' => 'Consolidação da Partida',
            'question' => 'Olhando para trás, o que do seu antigo eu você deixa na praia de partida definitiva?'
        ],
        8 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'O Caminho de Provas',
            'question' => 'Qual o primeiro obstáculo do dia a dia que desafia a sua nova determinação?'
        ],
        9 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'O Encontro com a Deusa',
            'question' => 'Como você pode cultivar mais amor-próprio e autocompaixão ao invés de cobranças duras nesse momento?'
        ],
        10 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'A Tentação do Ego',
            'question' => 'Em qual padrão nocivo ou distração o seu ego tenta te arrastar para que você desista do seu progresso?'
        ],
        11 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'Integração da Sombra',
            'question' => 'Encare o seu pior defeito ou fraqueza hoje. De que forma ele tenta te proteger nos bastidores?'
        ],
        12 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'A Apoteose',
            'question' => 'O que significa para você desapegar totalmente da necessidade de estar certo ou no controle?'
        ],
        13 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'A Conquista do Elixir',
            'question' => 'Qual a maior verdade ou insight que você descobriu ao atravessar as suas provações internas?'
        ],
        14 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'A Reconciliação Interna',
            'question' => 'Como você pacifica o conflito entre o seu eu do passado e o seu eu renovado de hoje?'
        ],
        15 => [
            'phase' => 'Iniciação / Provações',
            'theme' => 'Consolidação das Provas',
            'question' => 'Quais forças psicológicas você sente que desenvolveu e que agora fazem parte da sua armadura?'
        ],
        16 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'A Recusa do Retorno',
            'question' => 'Por que às vezes sentimos medo ou preguiça de levar a nossa nova sabedoria para a nossa rotina prática anterior?'
        ],
        17 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'A Corrida Mágica',
            'question' => 'Como manter o seu centramento quando o mundo exterior ou as outras pessoas exigirem que você volte a agir como antes?'
        ],
        18 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'O Resgate de Fora',
            'question' => 'Quais hábitos, rituais diários ou pessoas podem te apoiar a não esquecer o que você aprendeu?'
        ],
        19 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'A Travessia do Limiar de Retorno',
            'question' => 'De que forma prática você trará a sua nova consciência para as suas atividades mais simples de amanhã?'
        ],
        20 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'Senhor de Dois Mundos',
            'question' => 'Como equilibrar a sua busca interna de autoconhecimento com os seus deveres e obrigações no mundo material?'
        ],
        21 => [
            'phase' => 'Retorno / Integração',
            'theme' => 'Liberdade para Viver',
            'question' => 'Completando a jornada, qual o elixir prático final que você carrega no peito para viver livre do medo da mudança?'
        ]
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

        $firstDay = self::$journeyMap[1];

        return response()->json([
            'message' => 'Jornada iniciada com sucesso!',
            'current_day' => 1,
            'phase' => $firstDay['phase'],
            'question' => $firstDay['question']
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

        // 2. Determina qual o dia atual e o passo da conversação (step 1, 2 ou 3)
        $lastEntry = UserJourney::where('user_id', $userId)->latest('id')->first();
        
        if (!$lastEntry) {
            $currentDay = 1;
            $currentStep = 1;
        } else {
            if ($lastEntry->step === 1 || $lastEntry->step === 2) {
                // Continua no mesmo dia, avançando o passo conversacional
                $currentDay = $lastEntry->current_day;
                $currentStep = $lastEntry->step + 1;
            } else {
                // step === 3 significa que concluiu o dia anterior, avança para o próximo dia
                $currentDay = $lastEntry->current_day + 1;
                $currentStep = 1;
            }
        }

        // Se a jornada anterior já foi concluída ou passou de 21 dias, bloqueia novas respostas
        if ($lastEntry && $lastEntry->step === 3) {
            $lastInsight = $lastEntry->mentor_insight;
            $isLastFinished = (bool)($lastInsight['finished'] ?? false);
            if ($isLastFinished || $currentDay > 21) {
                return response()->json([
                    'message' => 'Parabéns! Você já concluiu todos os dias da sua jornada do herói.'
                ], 400); // 400 Bad Request
            }
        }

        // Determina a pergunta e a fase do dia atual de acordo com o passo
        $currentDayData = self::$journeyMap[$currentDay] ?? [
            'phase' => 'Iniciação / Provações',
            'question' => 'Como você se sente com relação a esse momento?'
        ];

        if ($currentStep === 1) {
            $currentQuestion = $currentDayData['question'];
            $currentPhase = $currentDayData['phase'];
        } else {
            $lastInsight = $lastEntry->mentor_insight;
            $currentQuestion = $lastInsight['next_question'] ?? 'Como você se sente com relação a esse momento?';
            $currentPhase = $lastEntry->phase;
        }

        // 3. FLUXO DO RAG COM DIVERSIFICAÇÃO DE LIVROS (Evita repetições nos top 3)
        $retrievedTexts = [];
        
        try {
            $totalChunks = BookChunk::count();

            if ($totalChunks > 0) {
                // Gera o vetor de embedding da resposta do usuário
                $userEmbedding = $this->ragService->generateEmbedding($userAnswer);
                $chunks = BookChunk::all();
                $similarities = [];

                // Compara a resposta do usuário com cada trecho de livro
                foreach ($chunks as $chunk) {
                    $score = $this->ragService->calculateCosineSimilarity($userEmbedding, $chunk->embedding);
                    $similarities[] = [
                        'content' => $chunk->content,
                        'book_title' => $chunk->book_title,
                        'score' => $score
                    ];
                }

                // Ordena por pontuação de similaridade decrescente
                usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);

                // Filtra para garantir que teremos no máximo 1 trecho de cada livro nos Top 3
                $usedBooks = [];
                foreach ($similarities as $sim) {
                    $bookTitle = $sim['book_title'];
                    if (!in_array($bookTitle, $usedBooks)) {
                        $retrievedTexts[] = $sim['content'];
                        $usedBooks[] = $bookTitle;
                        if (count($retrievedTexts) === 3) {
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Busca de RAG pulada temporariamente: " . $e->getMessage());
        }

        // 4. Junta as respostas dadas no dia atual para passar como histórico de curto prazo para a IA
        $currentDayAnswers = [];
        if ($currentStep === 1) {
            $currentDayAnswers = [$userAnswer];
        } elseif ($currentStep === 2) {
            $currentDayAnswers = [$lastEntry->user_answer, $userAnswer];
        } elseif ($currentStep === 3) {
            // Busca o registro do step 1 e step 2 do mesmo dia
            $step1Entry = UserJourney::where('user_id', $userId)->where('current_day', $currentDay)->where('step', 1)->first();
            $step2Entry = UserJourney::where('user_id', $userId)->where('current_day', $currentDay)->where('step', 2)->first();
            $currentDayAnswers = [
                $step1Entry ? $step1Entry->user_answer : '',
                $step2Entry ? $step2Entry->user_answer : '',
                $userAnswer
            ];
        }

        // Recupera o histórico completo apenas dos dias CONCLUÍDOS (step === 3)
        $history = UserJourney::where('user_id', $userId)
            ->where('step', 3)
            ->orderBy('current_day', 'asc')
            ->get()
            ->toArray();

        // 5. GERAÇÃO DE INSIGHT VIA IA (Prompt Híbrido)
        $aiRawText = "";
        try {
            $nextDayTheme = self::$journeyMap[$currentDay + 1] ?? null;
            
            $aiRawText = $this->ragService->generateInsight(
                $currentDayAnswers,
                $currentStep,
                $currentDay,
                $currentDayData,
                $nextDayTheme,
                $retrievedTexts,
                $history
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro na geração de insight do Gemini: " . $e->getMessage(), [
                'exception' => $e
            ]);

            // Caso de falha de conexão: fallback no formato JSON correto
            if ($currentStep < 3) {
                $aiRawText = json_encode([
                    'insight_curto' => "O mentor ouviu sua resposta: '{$userAnswer}'. No entanto, a conexão falhou.",
                    'next_question' => "O que você aprendeu com essa dificuldade técnica de hoje?"
                ]);
            } else {
                $aiRawText = json_encode([
                    'insight' => "O mentor ouviu sua resposta: '{$userAnswer}'. No entanto, a conexão falhou.",
                    'meditation' => 'Mantenha a calma e respire profundamente.',
                    'challenge' => 'Reflita sobre os seus sentimentos e medite em silêncio por 5 minutos.',
                    'phase' => 'Iniciação / Provações',
                    'next_question' => 'O que você aprendeu com essa dificuldade de hoje?',
                    'finished' => false
                ]);
            }
        }

        // Faz o parsing da resposta do AI (com tolerância a falhas)
        $parsedInsight = $this->parseAiResponse($aiRawText, $currentStep);

        // 6. Salva a resposta e o conselho gerado no banco SQLite
        $entry = UserJourney::create([
            'user_id' => $userId,
            'current_day' => $currentDay,
            'step' => $currentStep,
            'phase' => $currentPhase,
            'question' => $currentQuestion,
            'user_answer' => $userAnswer,
            'mentor_insight' => $parsedInsight
        ]);

        // Retorna a resposta HTTP RESTful indicando o step e o estado do dia
        return response()->json([
            'message' => 'Insight do Mentor gerado com sucesso!',
            'journey_record' => $entry,
            'current_step' => $currentStep,
            'is_day_completed' => $currentStep === 3,
            'next_day' => ($currentStep === 3 && $parsedInsight['finished']) ? null : (($currentStep === 3) ? $currentDay + 1 : $currentDay),
            'next_question' => $parsedInsight['finished'] ? null : $parsedInsight['next_question'],
            'next_phase' => $parsedInsight['finished'] ? null : ($parsedInsight['next_phase'] ?? $currentPhase),
            'finished' => (bool)($parsedInsight['finished'] ?? false)
        ], 200);
    }

    /**
     * Auxiliar para decodificar e validar a resposta do Gemini em formato JSON.
     */
    private function parseAiResponse(string $aiText, int $step): array
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
            if ($step < 3) {
                return [
                    'insight' => $data['insight_curto'] ?? $data['insight'] ?? 'O mentor ouviu e compreendeu sua reflexão.',
                    'next_question' => $data['next_question'] ?? 'Como você se sente com relação a esse momento?',
                    'finished' => false
                ];
            }

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
        if ($step < 3) {
            return [
                'insight' => $aiText,
                'next_question' => 'O que mais chamou sua atenção no seu comportamento hoje?',
                'finished' => false
            ];
        }

        return [
            'insight' => $aiText,
            'meditation' => 'Encontre um momento de silêncio para refletir.',
            'challenge' => 'Escreva em seu diário sobre a reflexão de hoje.',
            'next_phase' => 'Iniciação / Provações',
            'next_question' => 'O que mais chamou sua atenção no seu comportamento hoje?',
            'finished' => false
        ];
    }

    /**
     * Endpoint 3: GET /api/journey/history/{userId}
     * Retorna o histórico completo das etapas da jornada de um herói.
     */
    public function history($userId)
    {
        $history = \App\Models\UserJourney::where('user_id', $userId)
            ->orderBy('current_day', 'asc')
            ->get();

        return response()->json($history, 200);
    }
}
