<?php

use App\Livewire\DisciplinaPage;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function criarDisciplinaComPaginas(): Disciplina
{
    $disciplina = Disciplina::factory()->create(['nome' => 'Compiladores', 'slug' => 'compiladores']);
    Pagina::factory()->count(2)->create(['disciplina_id' => $disciplina->id]);

    return $disciplina;
}

it('renderiza a tela de disciplina com páginas', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Compiladores')
        ->assertSee('Gerar Resumo')
        ->assertSee('Gerar Flashcards')
        ->assertSee('Gerar Simulado');
});

it('retorna 404 para disciplina inexistente', function () {
    $this->get(route('disciplina', 'nao-existe'))->assertNotFound();
});

it('dispara ResumoGenerator e exibe resultado ao clicar em Gerar Resumo', function () {
    $disciplina = criarDisciplinaComPaginas();
    $pagina = $disciplina->paginas->first();

    $geracao = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'payload' => [
            'titulo' => 'Resumo de Compiladores',
            'secoes' => [
                [
                    'heading' => 'Análise Léxica',
                    'bullets' => [
                        [
                            'texto' => 'Compiladores analisam tokens.',
                            'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => 1]],
                        ],
                    ],
                ],
            ],
            'fontes_globais' => [],
        ],
        'escopo' => ['disciplina' => $disciplina->slug, 'tags' => [], 'paginas' => []],
    ]);

    GeracaoFonte::create(['geracao_id' => $geracao->id, 'pagina_id' => $pagina->id]);

    $mock = $this->mock(ResumoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::on(fn (Escopo $e) => $e->disciplina === $disciplina->slug))
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarResumo')
        ->assertSee('Resumo de Compiladores')
        ->assertSee('Análise Léxica')
        ->assertSee('Compiladores analisam tokens.');
});

it('exibe erro quando geração é rejeitada', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracaoRejeitada = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'rejeitado',
        'payload' => [],
        'escopo' => ['disciplina' => $disciplina->slug, 'tags' => [], 'paginas' => []],
    ]);

    $mock = $this->mock(ResumoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracaoRejeitada);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarResumo')
        ->assertSee('Geração rejeitada');
});

it('dispara SimuladoGenerator e exibe link ao simulado', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes' => [['contexto' => 'c', 'enunciado' => 'e', 'formato' => 'direto', 'alternativas' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'], 'correta' => 'a', 'fontes' => [], 'comentario_gabarito' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '']]]],
        'escopo' => ['disciplina' => $disciplina->slug, 'tags' => [], 'paginas' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarSimulado')
        ->assertSee('Iniciar Simulado');
});
