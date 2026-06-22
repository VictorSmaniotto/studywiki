<?php

use App\Livewire\DisciplinaPage;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use App\Services\LacunaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function criarSimuladoLacuna(
    string $slug,
    array $questoesME,
    array $respostas,
    int $acertos = 0,
    int $total = 0,
): RespostaSimulado {
    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => $slug],
        'payload' => ['questoes_me' => $questoesME, 'questoes_dis' => []],
    ]);

    return RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => $respostas,
        'acertos' => $acertos,
        'total' => $total,
    ]);
}

function questaoME(int $paginaId, int $chunkId, string $correta = 'a'): array
{
    return [
        'enunciado' => 'Enunciado teste',
        'contexto' => 'Contexto',
        'formato' => 'direto',
        'correta' => $correta,
        'alternativas' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
        'comentario_gabarito' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => ''],
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
    ];
}

// ──────────────────────────────────────────────
// L1 — menos de 2 simulados respondidos
// ──────────────────────────────────────────────

it('L1: detectar retorna vazio com menos de 2 simulados respondidos', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l1']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Tópico A']);

    // só 1 simulado respondido
    criarSimuladoLacuna('lac-l1', [questaoME($pagina->id, $chunk->id)], ['0' => 'b'], 0, 1);

    $lacunas = app(LacunaService::class)->detectar($disciplina);

    expect($lacunas)->toBeEmpty();
});

// ──────────────────────────────────────────────
// L2 — 2+ simulados sem erros
// ──────────────────────────────────────────────

it('L2: detectar retorna vazio quando não há erros nos simulados', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l2']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Tópico A']);

    $q = questaoME($pagina->id, $chunk->id, 'a');

    criarSimuladoLacuna('lac-l2', [$q], ['0' => 'a'], 1, 1); // acertou
    criarSimuladoLacuna('lac-l2', [$q], ['0' => 'a'], 1, 1); // acertou

    $lacunas = app(LacunaService::class)->detectar($disciplina);

    expect($lacunas)->toBeEmpty();
});

// ──────────────────────────────────────────────
// L3 — taxa de erro calculada e ordenada
// ──────────────────────────────────────────────

it('L3: detectar retorna tópicos com taxa_erro calculada e ordenada desc', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l3']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunkA = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Álgebra']);
    $chunkB = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Cálculo']);

    $qA = questaoME($pagina->id, $chunkA->id, 'a');
    $qB = questaoME($pagina->id, $chunkB->id, 'a');

    // Simulado 1: erra Álgebra, acerta Cálculo
    criarSimuladoLacuna('lac-l3', [$qA, $qB], ['0' => 'b', '1' => 'a'], 1, 2);
    // Simulado 2: erra Álgebra, acerta Cálculo
    criarSimuladoLacuna('lac-l3', [$qA, $qB], ['0' => 'c', '1' => 'a'], 1, 2);

    $lacunas = app(LacunaService::class)->detectar($disciplina);

    expect($lacunas)->not->toBeEmpty()
        ->and($lacunas[0]['heading'])->toBe('Álgebra')
        ->and($lacunas[0]['taxa_erro'])->toBe(100.0)
        ->and($lacunas[0]['erros'])->toBe(2)
        ->and($lacunas[0]['total'])->toBe(2);
});

// ──────────────────────────────────────────────
// L4 — limitado a 3 tópicos
// ──────────────────────────────────────────────

it('L4: detectar limita o resultado a 3 tópicos mesmo com mais de 3 headings com erro', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l4']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    $chunks = collect(['T1', 'T2', 'T3', 'T4'])->map(
        fn ($h) => Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => $h])
    );

    $questoes = $chunks->map(fn ($c) => questaoME($pagina->id, $c->id, 'a'))->values()->all();
    $respostas = ['0' => 'b', '1' => 'b', '2' => 'b', '3' => 'b']; // erra tudo

    criarSimuladoLacuna('lac-l4', $questoes, $respostas, 0, 4);
    criarSimuladoLacuna('lac-l4', $questoes, $respostas, 0, 4);

    $lacunas = app(LacunaService::class)->detectar($disciplina);

    expect($lacunas)->toHaveCount(3);
});

// ──────────────────────────────────────────────
// L5 — DisciplinaPage exibe card quando há lacunas
// ──────────────────────────────────────────────

it('L5: DisciplinaPage exibe card Pontos fracos quando há lacunas detectadas', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l5']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Redes Neurais']);

    $q = questaoME($pagina->id, $chunk->id, 'a');

    criarSimuladoLacuna('lac-l5', [$q], ['0' => 'b'], 0, 1);
    criarSimuladoLacuna('lac-l5', [$q], ['0' => 'c'], 0, 1);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Pontos fracos')
        ->assertSee('Redes Neurais')
        ->assertSee('Revisar');
});

// ──────────────────────────────────────────────
// L6 — revisarTopico pré-preenche queryResumo
// ──────────────────────────────────────────────

it('L6: revisarTopico seta queryResumo com o tópico informado', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'lac-l6']);
    Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->call('revisarTopico', 'Análise Semântica')
        ->assertSet('queryResumo', 'Análise Semântica');
});
