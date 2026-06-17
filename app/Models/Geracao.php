<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geracao extends Model
{
    use HasFactory;

    protected $table = 'geracoes';

    protected $fillable = [
        'tipo',
        'escopo',
        'status',
        'payload',
        'custo_tokens',
        'modelo',
    ];

    protected $casts = [
        'escopo' => 'array',
        'payload' => 'array',
        'custo_tokens' => 'integer',
    ];

    public function fontes(): HasMany
    {
        return $this->hasMany(GeracaoFonte::class);
    }

    public function paginas()
    {
        return $this->belongsToMany(Pagina::class, 'geracao_fontes');
    }
}
