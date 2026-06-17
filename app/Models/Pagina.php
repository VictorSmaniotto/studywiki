<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagina extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'disciplina_id',
        'tipo',
        'titulo',
        'slug',
        'path_relativo',
        'frontmatter',
        'corpo',
        'hash',
        'atualizado_na_vault',
    ];

    protected $casts = [
        'frontmatter' => 'array',
        'atualizado_na_vault' => 'datetime',
    ];

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function geracaoFontes(): HasMany
    {
        return $this->hasMany(GeracaoFonte::class);
    }
}
