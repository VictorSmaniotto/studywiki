<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespostaSimulado extends Model
{
    use HasFactory;

    protected $fillable = ['geracao_id', 'respostas', 'acertos', 'total', 'respostas_dissertativas', 'notas_dissertativas', 'tempo_realizado_segundos'];

    protected $casts = [
        'respostas' => 'array',
        'respostas_dissertativas' => 'array',
        'notas_dissertativas' => 'array',
        'acertos' => 'integer',
        'total' => 'integer',
        'tempo_realizado_segundos' => 'integer',
    ];

    public function geracao(): BelongsTo
    {
        return $this->belongsTo(Geracao::class);
    }
}
