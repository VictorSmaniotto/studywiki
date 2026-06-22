<?php

use App\Models\Flashcard;
use App\Models\Geracao;
use App\Models\User;
use App\Services\SpacedRepetitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function flashcardApiUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

// --- 401 ---

test('GET /api/flashcards/vencidos retorna 401 sem token', function () {
    $this->getJson('/api/flashcards/vencidos')->assertStatus(401);
});

test('POST /api/flashcards/{id}/revisar retorna 401 sem token', function () {
    $this->postJson('/api/flashcards/1/revisar')->assertStatus(401);
});

// --- vencidos ---

test('GET /api/flashcards/vencidos retorna apenas flashcards com proxima_revisao <= hoje', function () {
    $geracao = Geracao::factory()->create();
    Flashcard::factory()->create(['geracao_id' => $geracao->id, 'proxima_revisao' => Carbon::yesterday()]);
    Flashcard::factory()->create(['geracao_id' => $geracao->id, 'proxima_revisao' => Carbon::tomorrow()]);
    $headers = flashcardApiUser();

    $response = $this->getJson('/api/flashcards/vencidos', $headers)
        ->assertOk();

    expect($response->json())->toHaveCount(1);
});

test('GET /api/flashcards/vencidos retorna estrutura correta', function () {
    $geracao = Geracao::factory()->create();
    Flashcard::factory()->create(['geracao_id' => $geracao->id, 'proxima_revisao' => Carbon::today()]);
    $headers = flashcardApiUser();

    $this->getJson('/api/flashcards/vencidos', $headers)
        ->assertOk()
        ->assertJsonStructure([['id', 'geracao_id', 'frente', 'verso', 'fontes', 'proxima_revisao']]);
});

// --- revisar ---

test('POST /api/flashcards/{id}/revisar chama SRS e retorna dados atualizados', function () {
    $geracao = Geracao::factory()->create();
    $flashcard = Flashcard::factory()->create(['geracao_id' => $geracao->id]);
    $headers = flashcardApiUser();

    $mock = Mockery::mock(SpacedRepetitionService::class);
    $mock->shouldReceive('revisar')->once()->with(
        Mockery::on(fn ($f) => $f->id === $flashcard->id),
        true
    );
    app()->instance(SpacedRepetitionService::class, $mock);

    $this->postJson("/api/flashcards/{$flashcard->id}/revisar", ['lembrei' => true], $headers)
        ->assertOk()
        ->assertJsonStructure(['id', 'proxima_revisao', 'intervalo', 'facilidade', 'repeticoes']);
});

test('POST /api/flashcards/{id}/revisar valida campo lembrei', function () {
    $geracao = Geracao::factory()->create();
    $flashcard = Flashcard::factory()->create(['geracao_id' => $geracao->id]);
    $headers = flashcardApiUser();

    $this->postJson("/api/flashcards/{$flashcard->id}/revisar", [], $headers)
        ->assertUnprocessable();
});

test('POST /api/flashcards/{id}/revisar retorna 404 para flashcard inexistente', function () {
    $headers = flashcardApiUser();

    $this->postJson('/api/flashcards/9999/revisar', ['lembrei' => false], $headers)
        ->assertNotFound();
});
