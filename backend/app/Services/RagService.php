<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RagService
{
    /**
     * Algoritmo de Chunking (Fatiamento de texto com sobreposição).
     */
    public function splitText(string $text, int $chunkSize = 800, int $overlap = 100): array
    {
        $chunks = [];
        $textLength = mb_strlen($text);
        
        if ($textLength <= $chunkSize) {
            return [$text];
        }

        $start = 0;
        while ($start < $textLength) {
            $chunk = mb_substr($text, $start, $chunkSize);
            $chunks[] = trim($chunk);
            $start += ($chunkSize - $overlap);
            
            if ($start >= $textLength - $overlap) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Algoritmo de Similaridade de Cosseno (Cosine Similarity).
     */
    public function calculateCosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = count($vecA);
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Envia um texto para a API do Gemini e obtém o vetor de embeddings.
     */
    public function generateEmbedding(string $text): array
    {
        $apiKey = config('services.gemini.key');
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $response = Http::post(
                "https://generativelanguage.googleapis.com/v1/models/gemini-embedding-2:embedContent?key={$apiKey}",
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ]
                ]
            );

            if ($response->successful()) {
                return $response->json('embedding.values', []);
            }

            if ($response->status() === 429 && $attempt < $maxAttempts) {
                $retrySeconds = 30; // Padrão caso não consiga ler o delay
                $errorData = $response->json();
                if (isset($errorData['error']['details'])) {
                    foreach ($errorData['error']['details'] as $detail) {
                        if (isset($detail['@type']) && $detail['@type'] === 'type.googleapis.com/google.rpc.RetryInfo') {
                            if (isset($detail['retryDelay'])) {
                                $retrySeconds = (int) rtrim($detail['retryDelay'], 's');
                            }
                        }
                    }
                }
                $retrySeconds += 2; // Margem de segurança

                if (app()->runningInConsole()) {
                    echo "\n⚠️ Cota de embeddings excedida. Aguardando {$retrySeconds}s para tentar novamente (Tentativa {$attempt}/{$maxAttempts})...\n";
                }

                sleep($retrySeconds);
                continue;
            }

            throw new \Exception("Erro ao gerar embedding no Gemini: " . $response->body());
        }

        throw new \Exception("Erro ao gerar embedding no Gemini: Limite de tentativas excedido.");
    }

    /**
     * Envia múltiplos textos em lote (batch) para a API do Gemini e obtém os vetores de embeddings.
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        $apiKey = config('services.gemini.key');

        // Prepara as requisições estruturadas para o lote
        $requests = [];
        foreach ($texts as $text) {
            $requests[] = [
                'model' => 'models/gemini-embedding-2',
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
        }

        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            // Faz o POST utilizando o cliente HTTP nativo do Laravel no endpoint de lote
            $response = Http::post(
                "https://generativelanguage.googleapis.com/v1/models/gemini-embedding-2:batchEmbedContents?key={$apiKey}",
                [
                    'requests' => $requests
                ]
            );

            if ($response->successful()) {
                $embeddings = $response->json('embeddings', []);
                return array_map(fn($emb) => $emb['values'] ?? [], $embeddings);
            }

            if ($response->status() === 429 && $attempt < $maxAttempts) {
                $retrySeconds = 45; // Padrão caso não consiga ler o delay
                $errorData = $response->json();
                if (isset($errorData['error']['details'])) {
                    foreach ($errorData['error']['details'] as $detail) {
                        if (isset($detail['@type']) && $detail['@type'] === 'type.googleapis.com/google.rpc.RetryInfo') {
                            if (isset($detail['retryDelay'])) {
                                $retrySeconds = (int) rtrim($detail['retryDelay'], 's');
                            }
                        }
                    }
                }
                $retrySeconds += 2; // Margem de segurança

                if (app()->runningInConsole()) {
                    echo "\n⚠️ Cota de batch embeddings excedida. Aguardando {$retrySeconds}s para tentar novamente (Tentativa {$attempt}/{$maxAttempts})...\n";
                }

                sleep($retrySeconds);
                continue;
            }

            throw new \Exception("Erro ao gerar batch embeddings no Gemini: " . $response->body());
        }

        throw new \Exception("Erro ao gerar batch embeddings no Gemini: Limite de tentativas excedido.");
    }

    /**
     * Envia os trechos de livros recuperados + respostas do usuário para o Gemini
     * e gera um insight personalizado do Mentor (de diálogo intermediário ou completo diário).
     */
    public function generateInsight(
        array $currentDayAnswers,
        int $step,
        int $currentDay,
        array $currentDayTheme,
        ?array $nextDayTheme,
        array $contexts,
        array $history
    ): string {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        // Une os trechos de livros recuperados em um único texto estruturado
        $contextText = implode("\n\n--- Trecho de Apoio ---\n\n", $contexts);

        // Constrói o histórico da jornada para guiar a progressão dinâmica
        $historyText = "";
        foreach ($history as $stepEntry) {
            $historyText .= "Dia {$stepEntry['current_day']} (Fase: {$stepEntry['phase']}):\n";
            $historyText .= "Pergunta Final: {$stepEntry['question']}\n";
            $historyText .= "Resposta do Usuário: {$stepEntry['user_answer']}\n\n";
        }

        // Define o prompt dinamicamente de acordo com o step atual da conversação
        $prompt = "";
        if ($step < 3) {
            // PROMPT PARA ETAPAS DIALÉTICAS INTERMEDIÁRIAS (Passo 1 e 2)
            $prompt = "Você é o Mentor/Oráculo da Jornada do Herói de autoconhecimento do usuário. Estamos no Dia {$currentDay} da Jornada.\n";
            $prompt .= "O tema do dia de hoje é: '{$currentDayTheme['theme']}' (Fase: {$currentDayTheme['phase']}).\n";
            $prompt .= "Estamos no Passo {$step} de 3 da conversa diária. Sua tarefa é acolher a resposta do usuário, cruzar semanticamente com os trechos dos livros de apoio fornecidos e criar a próxima pergunta de investigação para aprofundar o autoconhecimento dele.\n\n";
            $prompt .= "Instruções:\n";
            $prompt .= "1. Analise de forma atenta o que o usuário respondeu no passo atual.\n";
            $prompt .= "2. Formule uma resposta curta (insight_curto) de 1 parágrafo relacionando a reflexão dele com a filosofia dos livros.\n";
            $prompt .= "3. Crie uma subpergunta provocativa e instigante (next_question) para o passo seguinte, permitindo que ele elabore sentimentos ou detalhes mais profundos sobre a questão de hoje.\n";
            $prompt .= "4. Retorne a resposta ESTRITAMENTE no formato JSON abaixo, sem textos extras ou formatação markdown:\n";
            $prompt .= "{\n";
            $prompt .= "  \"insight_curto\": \"(Seu breve feedback filosófico baseado na resposta dele e nos livros. Máximo 1 parágrafo curto)\",\n";
            $prompt .= "  \"next_question\": \"(A pergunta investigativa complementar para o passo seguinte do mesmo dia)\"\n";
            $prompt .= "}\n\n";
        } else {
            // PROMPT PARA A ETAPA DE CONSOLIDAÇÃO DIÁRIA E INSIGHT COMPLETO (Passo 3)
            $prompt = "Você é o Mentor/Oráculo da Jornada do Herói de autoconhecimento do usuário. Estamos no Dia {$currentDay} da Jornada.\n";
            $prompt .= "O tema do dia de hoje é: '{$currentDayTheme['theme']}' (Fase: {$currentDayTheme['phase']}).\n";
            $prompt .= "Estamos no Passo 3 de 3 (Fechamento do Dia). O usuário respondeu às 3 perguntas de hoje. Sua tarefa é analisar o diálogo consolidado, usar os livros como base profunda e gerar a revelação diária contendo insight amplo, frase de meditação, desafio comportamental e a pergunta inicial do dia seguinte.\n\n";
            $prompt .= "Instruções:\n";
            $prompt .= "1. Analise o conjunto completo de respostas do dia de hoje.\n";
            $prompt .= "2. Gere um insight amplo (insight) de até 4 parágrafos, contextualizando a jornada psicológica do herói com trechos do RAG (Dhammapada, Campbell, Jung, Marco Aurélio).\n";
            $prompt .= "3. Proponha uma frase curta de meditação diária (meditation) focada no tema aprendido hoje (máximo 150 caracteres).\n";
            $prompt .= "4. Proponha um desafio comportamental prático e acionável (challenge) baseado na sabedoria aplicada dos livros.\n";
            if ($nextDayTheme) {
                $prompt .= "5. Mapeie a transição de tema. O tema do próximo dia (Dia " . ($currentDay + 1) . ") será: '{$nextDayTheme['theme']}'. A pergunta inicial mapeada para o próximo dia é: '{$nextDayTheme['question']}'. Você deve sugerir essa pergunta e fase no retorno JSON.\n";
            } else {
                $prompt .= "5. Não há dia seguinte (Fim da jornada de 21 dias). Defina 'finished' como true.\n";
            }
            $prompt .= "6. Se o dia atual for 21, defina 'finished' como true.\n";
            $prompt .= "7. Retorne a resposta ESTRITAMENTE no formato JSON abaixo, sem textos extras ou formatação markdown:\n";
            $prompt .= "{\n";
            $prompt .= "  \"insight\": \"(Sua revelação e reflexão profunda consolidando as 3 respostas dele aos livros. Máximo 4 parágrafos)\",\n";
            $prompt .= "  \"meditation\": \"(A frase curta de meditação. Máximo 150 caracteres)\",\n";
            $prompt .= "  \"challenge\": \"(O desafio prático comportamental recomendado)\",\n";
            $prompt .= "  \"phase\": \"(A fase do dia seguinte de acordo com o mapa: '" . ($nextDayTheme['phase'] ?? 'Retorno / Integração') . "')\",\n";
            $prompt .= "  \"next_question\": \"(A pergunta inicial para o dia seguinte que foi fornecida nas instruções: '" . ($nextDayTheme['question'] ?? '') . "')\",\n";
            $prompt .= "  \"finished\": " . ($currentDay >= 21 ? 'true' : 'false') . "\n";
            $prompt .= "}\n\n";
        }

        if (!empty($historyText)) {
            $prompt .= "=== HISTÓRICO DOS DIAS ANTERIORES CONCLUÍDOS ===\n";
            $prompt .= $historyText . "\n";
        }

        $prompt .= "=== TRECHOS DOS LIVROS DE HOJE (RAG CONTEXT) ===\n";
        $prompt .= $contextText . "\n\n";

        if ($step < 3) {
            $prompt .= "=== DIÁLOGO DO DIA ATUAL ATÉ AGORA ===\n";
            foreach ($currentDayAnswers as $i => $ans) {
                $prompt .= "Resposta Passo " . ($i + 1) . ": \"{$ans}\"\n";
            }
            $prompt .= "\n=== RESPOSTA ATUAL DO PASSO {$step} ===\n";
            $prompt .= "\"" . end($currentDayAnswers) . "\"\n\n";
        } else {
            $prompt .= "=== DIÁLOGO CONSOLIDADO DO DIA DE HOJE ===\n";
            $prompt .= "1ª Resposta: \"{$currentDayAnswers[0]}\"\n";
            $prompt .= "2ª Resposta: \"{$currentDayAnswers[1]}\"\n";
            $prompt .= "3ª Resposta: \"{$currentDayAnswers[2]}\"\n\n";
        }

        $prompt .= "RESPOSTA EM JSON:";

        // Envia para o modelo parametrizado no endpoint v1beta para suportar modelos experimentais e de thinking
        // Define o timeout para 120 segundos para permitir que o modelo processe a cadeia de pensamento (thinking) sem interrupções
        $response = Http::timeout(120)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]
        );

        if ($response->failed()) {
            throw new \Exception("Erro ao gerar insight no Gemini/Gemma: " . $response->body());
        }

        // Filtra e junta apenas as partes de resposta, descartando os pensamentos internos (thinking)
        $parts = $response->json('candidates.0.content.parts', []);
        $aiText = '';
        foreach ($parts as $part) {
            if (isset($part['thought']) && $part['thought'] === true) {
                continue;
            }
            $aiText .= $part['text'] ?? '';
        }

        return $aiText ?: 'O mentor está em silêncio no momento.';
    }
}
