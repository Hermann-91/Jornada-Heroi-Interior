<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJourney extends Model
{
    /**
     * Colunas que o Laravel tem permissão para salvar em massa (Mass Assignment).
     */
    protected $fillable = [
        'user_id',
        'current_day',
        'step',
        'phase',
        'question',
        'user_answer',
        'mentor_insight',
    ];

    /**
     * Casts dos atributos para tipos nativos do PHP.
     */
    protected $casts = [
        'mentor_insight' => 'array',
        'step' => 'integer',
    ];

    /**
     * Relação reversa apontando para o herói.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
