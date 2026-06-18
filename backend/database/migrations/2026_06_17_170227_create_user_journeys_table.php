<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela user_journeys no SQLite.
     */
    public function up(): void
    {
        Schema::create('user_journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // FK de Usuário com exclusão em cascata
            $table->integer('current_day'); // Salva o dia da resposta (Dia 1 a 8)
            $table->string('phase'); // Macrofase (Ex: "Partida", "Iniciação", "Retorno")
            $table->text('question'); // O texto da pergunta que o usuário respondeu
            $table->text('user_answer'); // A reflexão literal escrita pelo usuário
            $table->text('mentor_insight'); // O conselho/insight do RAG + Gemma retornado
            $table->timestamps(); // Registra datas de criação e alteração
        });
    }

    /**
     * Apaga a tabela caso o desenvolvedor dê "rollback".
     */
    public function down(): void
    {
        Schema::dropIfExists('user_journeys');
    }
};
