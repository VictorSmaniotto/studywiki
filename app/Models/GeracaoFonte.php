<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeracaoFonte extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['geracao_id', 'pagina_id', 'chunk_id'];

    public function geracao(): BelongsTo
    {
        return $this->belongsTo(Geracao::class);
    }

    public function pagina(): BelongsTo
    {
        return $this->belongsTo(Pagina::class);
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }
}
