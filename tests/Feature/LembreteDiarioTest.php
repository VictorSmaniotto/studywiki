<?php

use App\Mail\LembreteDiario;
use App\Models\Flashcard;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    User::factory()->create(['email' => 'aluno@test.com']);
});

// L1 — envia email quando há flashcards vencidos
it('envia lembrete quando há flashcards vencidos', function () {
    Flashcard::factory()->create([
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);

    $this->artisan('studywiki:lembrete')->assertSuccessful();

    Mail::assertSent(LembreteDiario::class, function (LembreteDiario $mail) {
        return $mail->hasTo('aluno@test.com')
            && $mail->flashcardsPendentes === 1;
    });
});

// L2 — envia email quando streak em risco (última sessão foi ontem)
it('envia lembrete quando streak está em risco', function () {
    Setting::set('streak_count', '5');
    Setting::set('streak_last_date', Carbon::yesterday()->toDateString());

    $this->artisan('studywiki:lembrete')->assertSuccessful();

    Mail::assertSent(LembreteDiario::class, function (LembreteDiario $mail) {
        return $mail->hasTo('aluno@test.com')
            && $mail->streakAtual === 5;
    });
});

// L3 — NÃO envia quando lembrete_ativo = '0'
it('não envia lembrete quando desativado via settings', function () {
    Setting::set('lembrete_ativo', '0');

    Flashcard::factory()->create([
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);

    $this->artisan('studywiki:lembrete')->assertSuccessful();

    Mail::assertNotSent(LembreteDiario::class);
});

// L4 — NÃO envia quando nenhuma condição ativa
it('não envia lembrete quando sem flashcards e sem streak em risco', function () {
    // sem flashcards; streak_last_date = hoje (não em risco)
    Setting::set('streak_count', '3');
    Setting::set('streak_last_date', Carbon::today()->toDateString());

    $this->artisan('studywiki:lembrete')->assertSuccessful();

    Mail::assertNotSent(LembreteDiario::class);
});

// L5 — conteúdo correto: múltiplos flashcards + streak
it('email carrega contagem correta de flashcards e streak', function () {
    Flashcard::factory()->count(3)->create([
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);
    Setting::set('streak_count', '7');
    Setting::set('streak_last_date', Carbon::yesterday()->toDateString());

    $this->artisan('studywiki:lembrete')->assertSuccessful();

    Mail::assertSent(LembreteDiario::class, function (LembreteDiario $mail) {
        return $mail->flashcardsPendentes === 3
            && $mail->streakAtual === 7;
    });
});
