<?php

use App\Livewire\Biblioteca;
use App\Models\Disciplina;
use App\Models\Pagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renderiza a página com lista de disciplinas do sync', function () {
    Disciplina::factory()->has(Pagina::factory()->count(3))->create(['nome' => 'Compiladores', 'slug' => 'compiladores']);
    Disciplina::factory()->has(Pagina::factory()->count(1))->create(['nome' => 'Banco de Dados', 'slug' => 'banco-de-dados']);

    Livewire::test(Biblioteca::class)
        ->assertSee('Compiladores')
        ->assertSee('Banco de Dados')
        ->assertSee('3 páginas')
        ->assertSee('1 página');
});

it('filtra disciplinas pelo campo busca', function () {
    Disciplina::factory()->create(['nome' => 'Compiladores', 'slug' => 'compiladores']);
    Disciplina::factory()->create(['nome' => 'Banco de Dados', 'slug' => 'banco-de-dados']);

    Livewire::test(Biblioteca::class)
        ->set('busca', 'Compil')
        ->assertSee('Compiladores')
        ->assertDontSee('Banco de Dados');
});

it('exibe mensagem quando não há disciplinas', function () {
    Livewire::test(Biblioteca::class)
        ->assertSee('studywiki:sync');
});

it('exibe mensagem quando busca não encontra resultado', function () {
    Disciplina::factory()->create(['nome' => 'Compiladores', 'slug' => 'compiladores']);

    Livewire::test(Biblioteca::class)
        ->set('busca', 'xyz-inexistente')
        ->assertSee('xyz-inexistente');
});
