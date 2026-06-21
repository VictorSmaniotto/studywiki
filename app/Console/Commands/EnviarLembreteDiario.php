<?php

namespace App\Console\Commands;

use App\Mail\LembreteDiario;
use App\Models\Setting;
use App\Models\User;
use App\Services\TrilhaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarLembreteDiario extends Command
{
    protected $signature = 'studywiki:lembrete';

    protected $description = 'Envia lembrete diário de estudos (flashcards vencidos + streak em risco)';

    public function handle(TrilhaService $trilha): int
    {
        if (Setting::get('lembrete_ativo', '1') !== '1') {
            $this->info('Lembretes desativados via settings.');

            return self::SUCCESS;
        }

        $user = User::first();
        if (! $user) {
            $this->warn('Nenhum usuário encontrado.');

            return self::SUCCESS;
        }

        $flashcardsPendentes = $trilha->flashcardsVencidos()->count();
        $streak = $trilha->streakAtual();
        $streakEmRisco = $streak > 0 && Setting::get('streak_last_date') === now()->subDay()->toDateString();

        if ($flashcardsPendentes === 0 && ! $streakEmRisco) {
            $this->info('Nenhuma condição ativa para lembrete.');

            return self::SUCCESS;
        }

        Mail::to($user->email)->send(new LembreteDiario($flashcardsPendentes, $streak));

        $this->info("Lembrete enviado para {$user->email} ({$flashcardsPendentes} flashcards, streak {$streak}).");

        return self::SUCCESS;
    }
}
