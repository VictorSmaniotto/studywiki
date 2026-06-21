<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disciplina extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'slug'];

    public function paginas(): HasMany
    {
        return $this->hasMany(Pagina::class);
    }

    public function temas(): BelongsToMany
    {
        return $this->belongsToMany(Tema::class, 'disciplina_tema');
    }
}
