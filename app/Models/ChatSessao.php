<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSessao extends Model
{
    protected $table = 'chat_sessoes';

    protected $fillable = ['titulo', 'historico'];

    protected function casts(): array
    {
        return [
            'historico' => 'array',
        ];
    }
}
