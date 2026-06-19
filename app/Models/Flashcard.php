<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'geracao_id',
        'frente',
        'verso',
        'fontes',
        'proxima_revisao',
        'intervalo',
        'facilidade',
        'repeticoes',
    ];

    protected $casts = [
        'fontes' => 'array',
        'proxima_revisao' => 'date',
        'intervalo' => 'integer',
        'facilidade' => 'float',
        'repeticoes' => 'integer',
    ];

    public function geracao(): BelongsTo
    {
        return $this->belongsTo(Geracao::class);
    }

    public function devePraticar(): bool
    {
        return $this->proxima_revisao->lte(Carbon::today());
    }
}
