<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class RagServiceTest extends TestCase
{
    // A trait RefreshDatabase reconstrói e limpa o banco de dados em memória RAM antes de cada teste
    use RefreshDatabase;

    private RagService $ragService;

    /**
     * Função setUp roda AUTOMATICAMENTE antes de cada teste individual.
     * Perfeito para preparar as classes que serão testadas.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Inicializamos o serviço de RAG que criaremos em seguida
        $this->ragService = new RagService();
    }

    /**
     * Teste 1: Valida se o fatiador (chunker) divide um texto longo corretamente.
     */
    public function test_it_can_split_text_into_chunks_correctly(): void
    {
        $text = "Este é um texto longo que simula um livro de autoconhecimento. Ele deve ser dividido pelo algoritmo.";
        
        // Chamamos o método splitText (que dividirá em pedaços de no máximo 50 caracteres)
        $chunks = $this->ragService->splitText($text, 50, 10);

        // Asserções (Validações):
        $this->assertIsArray($chunks); // Valida se retornou uma lista (array)
        $this->assertGreaterThan(1, count($chunks)); // Valida se dividiu em mais de 1 pedaço
        $this->assertNotEmpty($chunks[0]); // Valida se o primeiro pedaço não está vazio
    }

    /**
     * Teste 2: Valida se o cálculo matemático de similaridade de vetores está correto.
     */
    public function test_it_calculates_cosine_similarity_correctly(): void
    {
        // Vetores idênticos geométricos devem ter similaridade perfeita = 1.0 (100% iguais)
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [1.0, 0.0, 0.0];

        $similarity = $this->ragService->calculateCosineSimilarity($vecA, $vecB);

        $this->assertEquals(1.0, $similarity); // Valida se o resultado foi igual a 1.0

        // Vetores opostos/ortogonais devem ter similaridade = 0.0 (totalmente diferentes)
        $vecC = [1.0, 0.0, 0.0];
        $vecD = [0.0, 1.0, 0.0];

        $similarityOrthogonal = $this->ragService->calculateCosineSimilarity($vecC, $vecD);

        $this->assertEquals(0.0, $similarityOrthogonal); // Valida se o resultado foi igual a 0.0
    }

    /**
     * Teste 3: Valida se o serviço gera Embeddings chamando a API do Gemini.
     */
    public function test_it_can_generate_embedding_via_gemini_api(): void
    {
        // 1. FALSIFICAMOS a chamada HTTP da Google para retornar um vetor simulado [0.1, 0.2, 0.3]
        Http::fake([
            'generativelanguage.googleapis.com/*/models/*:embedContent*' => Http::response([
                'embedding' => [
                    'values' => [0.1, 0.2, 0.3]
                ]
            ], 200)
        ]);

        // 2. Executamos a chamada no serviço
        $embedding = $this->ragService->generateEmbedding("Frase de exemplo");

        // 3. Validamos se o serviço interpretou a resposta do Gemini e retornou o array correto
        $this->assertEquals([0.1, 0.2, 0.3], $embedding);
    }

    /**
     * Teste 4: Valida se o serviço gera o Insight do Mentor integrando o RAG.
     */
    public function test_it_can_generate_insight_via_gemini_api(): void
    {
        // 1. FALSIFICAMOS a chamada HTTP de geração de texto do Gemini
        Http::fake([
            'generativelanguage.googleapis.com/*/models/*:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "Insight do mentor gerado com base no RAG."]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $userAnswer = "Sinto que estou travado profissionalmente.";
        $contexts = [
            "Trecho do livro: O chamado à aventura sempre tira o herói do comodismo."
        ];

        // 2. Executamos a geração de insight
        $insight = $this->ragService->generateInsight($userAnswer, $contexts);

        // 3. Validamos se o texto do oráculo gerado bate com o mock
        $this->assertEquals("Insight do mentor gerado com base no RAG.", $insight);
    }
}
