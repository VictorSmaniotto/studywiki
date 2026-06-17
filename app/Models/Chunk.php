<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'pagina_id',
        'ordem',
        'conteudo',
        'heading_path',
        'tokens',
        'embedding',
        'embedding_model',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'tokens' => 'integer',
    ];

    public function pagina(): BelongsTo
    {
        return $this->belongsTo(Pagina::class);
    }
}
