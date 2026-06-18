<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class User extends Model
{
    protected $fillable = [
        'name',
    ];
 
    /**
     * Relação de um usuário com o histórico de suas etapas na jornada.
     */
    public function journeys(): HasMany
    {
        return $this->hasMany(UserJourney::class);
    }
}
