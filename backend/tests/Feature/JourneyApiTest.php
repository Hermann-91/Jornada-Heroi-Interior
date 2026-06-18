<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\BookChunk;
use App\Models\UserJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class JourneyApiTest extends TestCase
{
    // A trait RefreshDatabase reconstrói e limpa o banco de dados em memória RAM antes de cada teste
    use RefreshDatabase;

    /**
     * Teste 1: Valida a rota de iniciar jornada.
     */
    public function test_user_can_start_the_journey(): void
    {
        $user = User::create(['name' => 'Herói Teste']);

        // 1. Envia uma requisição HTTP POST para a rota
        $response = $this->postJson('/api/journey/start', ['user_id' => $user->id]);

        // 2. Asserções (Validações da resposta)
        $response->assertStatus(200); // Espera Status HTTP 200 OK
        $response->assertJsonStructure([
            'message',
            'current_day',
            'phase',
            'question'
        ]);
        $response->assertJsonPath('current_day', 1); // Garante que inicia no dia 1
    }

    /**
     * Teste 2: Valida se a rota de responder pergunta executa o RAG e a IA.
     */
    public function test_user_can_respond_and_receive_mentor_insight(): void
    {
        $user = User::create(['name' => 'Herói Teste']);

        // 1. Populamos o banco de dados temporário com 2 chunks de livros falsos
        BookChunk::create([
            'book_title' => 'Livro Teste.pdf',
            'chunk_index' => 0,
            'content' => 'Trecho sobre coragem e a caverna do medo.',
            'embedding' => [0.9, 0.1, 0.0] // Vetor de teste
        ]);

        BookChunk::create([
            'book_title' => 'Livro Teste.pdf',
            'chunk_index' => 1,
            'content' => 'Trecho sobre rotinas e hábitos.',
            'embedding' => [0.1, 0.9, 0.0]
        ]);

        // 2. Falsificamos as chamadas externas HTTP da API do Gemini (Mocking)
        Http::fake([
            // Mock do Embedding da resposta do usuário
            'generativelanguage.googleapis.com/*/models/*:embedContent*' => Http::response([
                'embedding' => ['values' => [0.85, 0.15, 0.0]] // Vetor da resposta do usuário
            ], 200),
            
            // Mock do Geração de Insight do mentor
            'generativelanguage.googleapis.com/*/models/*:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "Insight do mentor integrado com Campbell."]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        // 3. Envia a resposta do usuário para a API (POST /api/journey/respond)
        $response = $this->postJson('/api/journey/respond', [
            'user_id' => $user->id,
            'answer' => 'Estou enfrentando meus maiores medos profissionais hoje.'
        ]);

        // 4. Asserções do retorno JSON da API
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'journey_record',
            'next_day',
            'next_question',
            'next_phase',
            'finished'
        ]);
        $response->assertJsonPath('next_day', 2); // Espera avançar para o dia 2
        $response->assertJsonPath('finished', false);

        // 5. Asserção de Banco de Dados: Garante que a resposta e o insight foram salvos fisicamente
        $this->assertDatabaseHas('user_journeys', [
            'user_id' => $user->id,
            'current_day' => 1,
            'user_answer' => 'Estou enfrentando meus maiores medos profissionais hoje.',
        ]);

        $journey = UserJourney::first();
        $this->assertEquals('Insight do mentor integrado com Campbell.', $journey->mentor_insight['insight']);
    }

    /**
     * Teste 3: Valida se a API rejeita respostas muito curtas (Validação de Entrada).
     */
    public function test_api_validates_required_and_minimum_length_answer(): void
    {
        // Envia resposta em branco
        $responseEmpty = $this->postJson('/api/journey/respond', [
            'answer' => ''
        ]);
        $responseEmpty->assertStatus(422); // 422 Unprocessable Content

        // Envia resposta menor que 10 caracteres
        $responseShort = $this->postJson('/api/journey/respond', [
            'answer' => 'Curta'
        ]);
        $responseShort->assertStatus(422);
    }
}
