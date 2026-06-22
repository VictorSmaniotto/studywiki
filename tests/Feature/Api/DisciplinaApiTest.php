<?php

use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\User;
use App\Services\AI\ResumoGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apiUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

// --- 401 sem token ---

test('GET /api/disciplinas retorna 401 sem token', function () {
    $this->getJson('/api/disciplinas')->assertStatus(401);
});

test('GET /api/disciplinas/{slug} retorna 401 sem token', function () {
    $this->getJson('/api/disciplinas/foo')->assertStatus(401);
});

test('GET /api/disciplinas/{slug}/geracoes retorna 401 sem token', function () {
    $this->getJson('/api/disciplinas/foo/geracoes')->assertStatus(401);
});

test('POST /api/disciplinas/{slug}/gerar retorna 401 sem token', function () {
    $this->postJson('/api/disciplinas/foo/gerar')->assertStatus(401);
});

// --- index ---

test('GET /api/disciplinas retorna lista de disciplinas', function () {
    Disciplina::factory()->count(3)->create();
    $headers = apiUser();

    $this->getJson('/api/disciplinas', $headers)
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([['id', 'nome', 'slug']]);
});

// --- show ---

test('GET /api/disciplinas/{slug} retorna disciplina existente', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'redes']);
    $headers = apiUser();

    $this->getJson('/api/disciplinas/redes', $headers)
        ->assertOk()
        ->assertJsonFragment(['slug' => 'redes', 'nome' => $disciplina->nome]);
});

test('GET /api/disciplinas/{slug} retorna 404 para slug inexistente', function () {
    $headers = apiUser();

    $this->getJson('/api/disciplinas/nao-existe', $headers)->assertNotFound();
});

// --- geracoes ---

test('GET /api/disciplinas/{slug}/geracoes retorna gerações da disciplina', function () {
    $slug = 'so';
    Disciplina::factory()->create(['slug' => $slug]);
    Geracao::factory()->create(['escopo' => ['disciplina' => $slug]]);
    Geracao::factory()->create(['escopo' => ['disciplina' => 'outra']]);
    $headers = apiUser();

    $response = $this->getJson("/api/disciplinas/{$slug}/geracoes", $headers)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

test('GET /api/disciplinas/{slug}/geracoes retorna 404 para disciplina inexistente', function () {
    $headers = apiUser();

    $this->getJson('/api/disciplinas/nao-existe/geracoes', $headers)->assertNotFound();
});

// --- gerar ---

test('POST /api/disciplinas/{slug}/gerar valida campo tipo', function () {
    Disciplina::factory()->create(['slug' => 'redes']);
    $headers = apiUser();

    $this->postJson('/api/disciplinas/redes/gerar', ['tipo' => 'invalido'], $headers)
        ->assertUnprocessable();
});

test('POST /api/disciplinas/{slug}/gerar chama generator e retorna geracao', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'redes']);
    $headers = apiUser();

    $geracaoFake = Geracao::factory()->make([
        'id' => 42,
        'tipo' => 'resumo',
        'status' => 'ok',
        'payload' => ['bullets' => []],
        'custo_tokens' => 100,
        'regeneracoes' => 0,
        'created_at' => now(),
    ]);
    $geracaoFake->id = 42;
    $geracaoFake->exists = true;

    $mock = Mockery::mock(ResumoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracaoFake);
    app()->instance(ResumoGenerator::class, $mock);

    $this->postJson('/api/disciplinas/redes/gerar', ['tipo' => 'resumo'], $headers)
        ->assertCreated()
        ->assertJsonStructure(['id', 'tipo', 'status', 'payload', 'custo_tokens', 'regeneracoes', 'created_at']);
});

test('POST /api/disciplinas/{slug}/gerar retorna 404 para disciplina inexistente', function () {
    $headers = apiUser();

    $this->postJson('/api/disciplinas/nao-existe/gerar', ['tipo' => 'resumo'], $headers)
        ->assertNotFound();
});
