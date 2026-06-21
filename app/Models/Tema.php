<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tema extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'slug'];

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'disciplina_tema');
    }
}
