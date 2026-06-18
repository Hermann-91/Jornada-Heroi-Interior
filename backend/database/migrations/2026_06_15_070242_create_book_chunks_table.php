<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migração (Cria a tabela no SQLite).
     */
    public function up(): void
    {
        Schema::create('book_chunks', function (Blueprint $table) {
            $table->id(); // Identificador único autoincrementado (Chave Primária)
            $table->string('book_title'); // Título do livro (ex: "O Herói de Mil Faces")
            $table->integer('chunk_index'); // Posição/Índice do bloco no livro (0, 1, 2...)
            $table->text('content'); // Conteúdo textual do bloco de leitura
            $table->text('embedding'); // Vetor numérico do bloco salvo em formato JSON
            $table->timestamps(); // Cria as colunas created_at e updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_chunks');
    }
};
