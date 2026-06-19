<?php

use App\Livewire\DisciplinaPage;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\FlashcardsGenerator;
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

function escopoJson(Disciplina $disciplina): array
{
    return ['disciplina' => $disciplina->slug, 'tags' => [], 'paginas' => [], 'query' => null];
}

// ──────────────────────────────────────────────
// U1 — 3 guias sempre visíveis
// ──────────────────────────────────────────────

it('exibe as três guias Resumo, Flashcards e Simulado sempre visíveis', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Resumo')
        ->assertSee('Flashcards')
        ->assertSee('Simulado');
});

it('retorna 404 para disciplina inexistente', function () {
    $this->get(route('disciplina', 'nao-existe'))->assertNotFound();
});

// ──────────────────────────────────────────────
// U2 — histórico com data, status e tokens
// ──────────────────────────────────────────────

it('lista gerações de resumo passadas com status e tokens', function () {
    $disciplina = criarDisciplinaComPaginas();

    Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'custo_tokens' => 4321,
        'escopo' => escopoJson($disciplina),
        'payload' => [],
    ]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('4321')
        ->assertSee('ok');
});

it('lista gerações de flashcards passadas com status e tokens', function () {
    $disciplina = criarDisciplinaComPaginas();

    Geracao::factory()->create([
        'tipo' => 'flashcards',
        'status' => 'ok',
        'custo_tokens' => 2222,
        'escopo' => escopoJson($disciplina),
        'payload' => ['cards' => []],
    ]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('2222');
});

it('lista gerações de simulado passadas com status e tokens', function () {
    $disciplina = criarDisciplinaComPaginas();

    Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'custo_tokens' => 3333,
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes' => []],
    ]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('3333');
});

// ──────────────────────────────────────────────
// U3 — expandir revela conteúdo completo
// ──────────────────────────────────────────────

it('expande geração de resumo para mostrar conteúdo', function () {
    $disciplina = criarDisciplinaComPaginas();

    $ger = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => [
            'titulo' => 'Resumo Test',
            'secoes' => [[
                'heading' => 'Análise Léxica',
                'bullets' => [['texto' => 'Texto exclusivo do expandir', 'fontes' => []]],
            ]],
            'fontes_globais' => [],
        ],
    ]);

    $component = Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug]);
    $component->assertDontSee('Texto exclusivo do expandir');
    $component->call('toggleExpandir', $ger->id)->assertSee('Texto exclusivo do expandir');
});

it('recolhe geração ao chamar toggleExpandir uma segunda vez', function () {
    $disciplina = criarDisciplinaComPaginas();

    $ger = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => [
            'titulo' => 'T',
            'secoes' => [[
                'heading' => 'H',
                'bullets' => [['texto' => 'Conteúdo recolhível', 'fontes' => []]],
            ]],
            'fontes_globais' => [],
        ],
    ]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('toggleExpandir', $ger->id)
        ->assertSee('Conteúdo recolhível')
        ->call('toggleExpandir', $ger->id)
        ->assertDontSee('Conteúdo recolhível');
});

// ──────────────────────────────────────────────
// U4 — botão Gerar novo em cada guia
// ──────────────────────────────────────────────

it('exibe botão Gerar Resumo na guia de resumo', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Gerar Resumo');
});

it('exibe botão Gerar Flashcards na guia de flashcards', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Gerar Flashcards');
});

it('exibe botão Gerar Simulado na guia de simulado', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Gerar Simulado');
});

// ──────────────────────────────────────────────
// U5/U6 — params dificuldade e nQuestoes passados ao SimuladoGenerator
// ──────────────────────────────────────────────

it('passa dificuldade ao SimuladoGenerator', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::on(fn (Escopo $e) => $e->disciplina === $disciplina->slug), 5, 3, 'dificil', Mockery::any(), Mockery::any())
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('dificuldade', 'dificil')
        ->call('gerarSimulado');
});

it('passa nQuestoes ao SimuladoGenerator', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::any(), 10, 3, 'medio', Mockery::any(), Mockery::any())
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('nQuestoes', 10)
        ->call('gerarSimulado');
});

// ──────────────────────────────────────────────
// U7 — nova geração auto-expandida após geração
// ──────────────────────────────────────────────

it('nova geração de resumo aparece auto-expandida após gerarResumo', function () {
    $disciplina = criarDisciplinaComPaginas();
    $pagina = $disciplina->paginas->first();

    $geracao = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => [
            'titulo' => 'Resumo de Compiladores',
            'secoes' => [[
                'heading' => 'Análise Léxica',
                'bullets' => [['texto' => 'Compiladores analisam tokens.', 'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => 1]]]],
            ]],
            'fontes_globais' => [],
        ],
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

it('nova geração de simulado aparece auto-expandida com link Iniciar Simulado', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes' => [['contexto' => 'c', 'enunciado' => 'e', 'formato' => 'direto', 'alternativas' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'], 'correta' => 'a', 'fontes' => [], 'comentario_gabarito' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '']]]],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarSimulado')
        ->assertSee('Iniciar Simulado');
});

// ──────────────────────────────────────────────
// U8 — todas as gerações passadas listadas
// ──────────────────────────────────────────────

it('mostra múltiplas gerações de resumo sem perder nenhuma', function () {
    $disciplina = criarDisciplinaComPaginas();

    Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'ok', 'custo_tokens' => 111, 'escopo' => escopoJson($disciplina), 'payload' => []]);
    Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'ok', 'custo_tokens' => 222, 'escopo' => escopoJson($disciplina), 'payload' => []]);
    Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'rejeitado', 'custo_tokens' => 333, 'escopo' => escopoJson($disciplina), 'payload' => []]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('111')
        ->assertSee('222')
        ->assertSee('333');
});

it('não lista gerações de outra disciplina', function () {
    $d1 = Disciplina::factory()->create(['nome' => 'Redes', 'slug' => 'redes']);
    $d2 = criarDisciplinaComPaginas();

    Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'ok', 'custo_tokens' => 9999, 'escopo' => ['disciplina' => $d1->slug, 'tags' => [], 'paginas' => [], 'query' => null], 'payload' => []]);

    Livewire::test(DisciplinaPage::class, ['slug' => $d2->slug])
        ->assertDontSee('9999');
});

// ──────────────────────────────────────────────
// Erro de geração
// ──────────────────────────────────────────────

it('exibe erro de resumo quando geração é rejeitada', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracaoRejeitada = Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'rejeitado',
        'escopo' => escopoJson($disciplina),
        'payload' => [],
    ]);

    $mock = $this->mock(ResumoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracaoRejeitada);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarResumo')
        ->assertSee('Geração rejeitada');
});

it('exibe erro de flashcards quando geração é rejeitada', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracaoRejeitada = Geracao::factory()->create([
        'tipo' => 'flashcards',
        'status' => 'rejeitado',
        'escopo' => escopoJson($disciplina),
        'payload' => [],
    ]);

    $mock = $this->mock(FlashcardsGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracaoRejeitada);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarFlashcards')
        ->assertSee('Geração rejeitada');
});

it('exibe erro de simulado quando geração é rejeitada', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracaoRejeitada = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'rejeitado',
        'escopo' => escopoJson($disciplina),
        'payload' => [],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')->once()->andReturn($geracaoRejeitada);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarSimulado')
        ->assertSee('Geração rejeitada');
});

// ──────────────────────────────────────────────
// P1 — select de perfil visível no formulário
// ──────────────────────────────────────────────

it('exibe seleção de perfil com opções Universitário e Vestibular', function () {
    $disciplina = criarDisciplinaComPaginas();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Universitário')
        ->assertSee('Vestibular');
});

// ──────────────────────────────────────────────
// P2 — perfil Universitário preenche defaults
// ──────────────────────────────────────────────

it('aplica defaults de Universitário ao selecionar perfil', function () {
    $disciplina = criarDisciplinaComPaginas();

    $component = Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('perfil', 'universitario');

    expect($component->get('nQuestoes'))->toBe(3)
        ->and($component->get('nDissertativas'))->toBe(3)
        ->and($component->get('dificuldade'))->toBe('medio');
});

// ──────────────────────────────────────────────
// P3 — perfil Vestibular preenche defaults
// ──────────────────────────────────────────────

it('aplica defaults de Vestibular ao selecionar perfil', function () {
    $disciplina = criarDisciplinaComPaginas();

    $component = Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('perfil', 'vestibular');

    expect($component->get('nQuestoes'))->toBe(10)
        ->and($component->get('nDissertativas'))->toBe(10)
        ->and($component->get('dificuldade'))->toBe('dificil');
});

// ──────────────────────────────────────────────
// P4 — usuário pode sobrescrever N após perfil
// ──────────────────────────────────────────────

it('permite sobrescrever nQuestoes após selecionar perfil universitário', function () {
    $disciplina = criarDisciplinaComPaginas();

    $component = Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('perfil', 'universitario')
        ->set('nQuestoes', 7);

    expect($component->get('nQuestoes'))->toBe(7)
        ->and($component->get('nDissertativas'))->toBe(3);
});

// ──────────────────────────────────────────────
// P5 — perfil e tempo estimado passados ao generator
// ──────────────────────────────────────────────

it('passa perfil e tempo estimado ao gerar simulado universitário', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes_me' => [], 'questoes_dis' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::any(), 3, 3, 'medio', 'universitario', 36 * 60)
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('perfil', 'universitario')
        ->call('gerarSimulado');
});

it('passa perfil e tempo estimado ao gerar simulado vestibular', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes_me' => [], 'questoes_dis' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::any(), 10, 10, 'dificil', 'vestibular', 120 * 60)
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->set('perfil', 'vestibular')
        ->call('gerarSimulado');
});

it('passa perfil personalizado com tempo zero quando sem perfil especial', function () {
    $disciplina = criarDisciplinaComPaginas();

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => escopoJson($disciplina),
        'payload' => ['questoes_me' => [], 'questoes_dis' => []],
    ]);

    $mock = $this->mock(SimuladoGenerator::class);
    $mock->shouldReceive('gerar')
        ->once()
        ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), 'personalizado', 0)
        ->andReturn($geracao);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('gerarSimulado');
});
