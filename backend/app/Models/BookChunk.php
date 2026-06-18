<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChunk extends Model
{
    /**
     * Define as colunas que podem ser gravadas de forma segura (Mass Assignment).
     */
    protected $fillable = [
        'book_title',
        'chunk_index',
        'content',
        'embedding',
    ];

    /**
     * Conversão de atributos (Casts).
     * Converte o campo 'embedding' de string JSON (no SQLite) para array PHP.
     */
    protected $casts = [
        'embedding' => 'array',
    ];
}
