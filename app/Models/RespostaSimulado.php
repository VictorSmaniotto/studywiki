<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespostaSimulado extends Model
{
    use HasFactory;

    protected $fillable = ['geracao_id', 'respostas', 'acertos', 'total'];

    protected $casts = [
        'respostas' => 'array',
        'acertos' => 'integer',
        'total' => 'integer',
    ];

    public function geracao(): BelongsTo
    {
        return $this->belongsTo(Geracao::class);
    }
}
