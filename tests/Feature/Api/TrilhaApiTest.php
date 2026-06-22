<?php

use App\Models\User;
use App\Services\TrilhaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trilhaApiUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

test('GET /api/trilha retorna 401 sem token', function () {
    $this->getJson('/api/trilha')->assertStatus(401);
});

test('GET /api/trilha retorna estrutura correta', function () {
    $mock = Mockery::mock(TrilhaService::class);
    $mock->shouldReceive('flashcardsVencidos')->once()->andReturn(new Collection([]));
    $mock->shouldReceive('topicosPrioritarios')->once()->andReturn([]);
    $mock->shouldReceive('streakAtual')->once()->andReturn(5);
    app()->instance(TrilhaService::class, $mock);

    $headers = trilhaApiUser();

    $this->getJson('/api/trilha', $headers)
        ->assertOk()
        ->assertJsonStructure(['streak', 'flashcards_vencidos', 'topicos_prioritarios'])
        ->assertJsonFragment(['streak' => 5]);
});
