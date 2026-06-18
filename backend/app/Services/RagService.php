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
     * Envia os trechos de livros recuperados + resposta do usuário para o Gemini
     * e gera um insight personalizado do Mentor contendo reflexões, meditação e desafio prático.
     */
    public function generateInsight(string $userAnswer, array $contexts, int $currentDay = 1, array $history = []): string
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        // Une os trechos de livros recuperados em um único texto estruturado
        $contextText = implode("\n\n--- Trecho de Apoio ---\n\n", $contexts);

        // Constrói o histórico da jornada para guiar a progressão dinâmica
        $historyText = "";
        foreach ($history as $step) {
            $historyText .= "Dia {$step['current_day']} (Fase: {$step['phase']}):\n";
            $historyText .= "Pergunta: {$step['question']}\n";
            $historyText .= "Resposta do Usuário: {$step['user_answer']}\n\n";
        }

        // Engenharia de Prompt (System Prompt & RAG Context para estruturar resposta em JSON)
        $prompt = "Você é o Oráculo/Mentor da Jornada do Herói de autoconhecimento do usuário. Sua tarefa é analisar a resposta atual do usuário, usar a sabedoria dos livros fornecidos e gerar uma resposta estruturada estritamente em formato JSON.\n\n";
        $prompt .= "Instruções:\n";
        $prompt .= "1. Analise de forma reflexiva e profunda a resposta do usuário.\n";
        $prompt .= "2. Utilize os trechos de livros fornecidos abaixo como base de sabedoria para estruturar o seu insight.\n";
        $prompt .= "3. Proponha um desafio prático comportamental ou mental baseado estritamente nas práticas dos livros (como dicotomia do controle do Estoicismo, integração da sombra de Jung ou reflexão do mentor de Campbell).\n";
        $prompt .= "4. A jornada deve avançar dinamicamente pelas fases da Jornada do Herói: 'Partida / Separação', 'Iniciação / Provações' e 'Retorno / Integração'. O dia atual da jornada é o Dia {$currentDay}.\n";
        $prompt .= "5. Se o dia atual for 12 ou mais, avalie se o usuário demonstrou maturidade e integração nas respostas anteriores. Se sim, você pode finalizar a jornada definindo 'finished' como true. A jornada DEVE obrigatoriamente terminar até o Dia 21.\n\n";
        $prompt .= "Você deve retornar APENAS o JSON abaixo (sem markdown extra, sem texto fora do JSON):\n";
        $prompt .= "{\n";
        $prompt .= "  \"insight\": \"(Sua reflexão profunda e acolhedora, conectando a resposta do usuário aos conceitos dos livros. Máximo 4 parágrafos)\",\n";
        $prompt .= "  \"meditation\": \"(Uma frase curta de meditação focada no tema de hoje. Máximo 150 caracteres)\",\n";
        $prompt .= "  \"challenge\": \"(O desafio prático ou atividade psicológica recomendada com base nas técnicas dos livros. Seja claro e acionável)\",\n";
        $prompt .= "  \"phase\": \"(A fase sugerida para o dia seguinte: 'Partida / Separação', 'Iniciação / Provações' ou 'Retorno / Integração')\",\n";
        $prompt .= "  \"next_question\": \"(A próxima pergunta reflexiva personalizada para o dia seguinte, dando continuidade orgânica ao aprendizado do usuário)\",\n";
        $prompt .= "  \"finished\": false\n";
        $prompt .= "}\n\n";

        if (!empty($historyText)) {
            $prompt .= "=== HISTÓRICO DAS ETAPAS ANTERIORES ===\n";
            $prompt .= $historyText . "\n";
        }

        $prompt .= "=== TRECHOS DOS LIVROS (CONTEXTO RECUPERADO) ===\n";
        $prompt .= $contextText . "\n\n";
        $prompt .= "=== RESPOSTA ATUAL DO USUÁRIO ===\n";
        $prompt .= "\"{$userAnswer}\"\n\n";
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
