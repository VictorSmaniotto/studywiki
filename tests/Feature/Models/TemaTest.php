<?php

use App\Filament\Resources\TemaResource;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Models\Tema;
use App\Services\AI\MapaMentalGenerator;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── T1: Tema model e pivot ───────────────────────────────────────────────

it('T1 — Tema factory cria registro com nome e slug', function () {
    $tema = Tema::factory()->create(['nome' => 'Programação Orientada a Objetos', 'slug' => 'poo']);

    expect($tema->nome)->toBe('Programação Orientada a Objetos');
    expect($tema->slug)->toBe('poo');
});

it('T1 — Tema tem relação many-to-many com Disciplina', function () {
    $tema = Tema::factory()->create();
    $d1 = Disciplina::factory()->create();
    $d2 = Disciplina::factory()->create();

    $tema->disciplinas()->attach([$d1->id, $d2->id]);

    expect($tema->disciplinas()->count())->toBe(2);
    expect($d1->temas()->count())->toBe(1);
});

it('T1 — pivot disciplina_tema respeita cascadeOnDelete', function () {
    $tema = Tema::factory()->create();
    $disciplina = Disciplina::factory()->create();
    $tema->disciplinas()->attach($disciplina->id);

    $disciplina->delete();

    expect(DB::table('disciplina_tema')->where('tema_id', $tema->id)->count())->toBe(0);
});

// ─── T2: Escopo aceita temaId ────────────────────────────────────────────

it('T2 — Escopo com temaId não é vazio', function () {
    $escopo = new Escopo(temaId: 1);

    expect($escopo->vazio())->toBeFalse();
    expect($escopo->temaId)->toBe(1);
});

it('T2 — Escopo sem temaId é vazio', function () {
    expect((new Escopo)->vazio())->toBeTrue();
});

// ─── T3: TemaResource existe no Filament ─────────────────────────────────

it('T3 — TemaResource registrado e acessível', function () {
    expect(class_exists(TemaResource::class))->toBeTrue();
    expect(TemaResource::getModel())->toBe(Tema::class);
});

// ─── T4: retrieval cross-disciplina via temaId ───────────────────────────

it('T4 — forScope com temaId retorna chunks de múltiplas disciplinas', function () {
    $tema = Tema::factory()->create(['slug' => 'poo']);

    $d1 = Disciplina::factory()->create(['slug' => 'redes']);
    $d2 = Disciplina::factory()->create(['slug' => 'so']);
    $tema->disciplinas()->attach([$d1->id, $d2->id]);

    $p1 = Pagina::factory()->create(['disciplina_id' => $d1->id]);
    $p2 = Pagina::factory()->create(['disciplina_id' => $d2->id]);

    Chunk::factory()->create(['pagina_id' => $p1->id, 'conteudo' => 'conteúdo de redes']);
    Chunk::factory()->create(['pagina_id' => $p2->id, 'conteudo' => 'conteúdo de SO']);

    $chunks = app(RetrievalService::class)->forScope(new Escopo(temaId: $tema->id));

    $paginaIds = collect($chunks)->pluck('pagina_id')->unique()->sort()->values()->all();
    expect($paginaIds)->toContain($p1->id);
    expect($paginaIds)->toContain($p2->id);
});

it('T4 — forScope com temaId não retorna chunks de disciplina fora do tema', function () {
    $tema = Tema::factory()->create();
    $dentroDotema = Disciplina::factory()->create();
    $foraDotema = Disciplina::factory()->create();
    $tema->disciplinas()->attach($dentroDotema->id);

    $pDentro = Pagina::factory()->create(['disciplina_id' => $dentroDotema->id]);
    $pFora = Pagina::factory()->create(['disciplina_id' => $foraDotema->id]);

    Chunk::factory()->create(['pagina_id' => $pDentro->id]);
    Chunk::factory()->create(['pagina_id' => $pFora->id]);

    $chunks = app(RetrievalService::class)->forScope(new Escopo(temaId: $tema->id));

    $paginaIds = collect($chunks)->pluck('pagina_id')->all();
    expect($paginaIds)->toContain($pDentro->id);
    expect($paginaIds)->not->toContain($pFora->id);
});

it('T4 — Geracao persistida salva tema_id no escopo JSON', function () {
    $tema = Tema::factory()->create();
    $disciplina = Disciplina::factory()->create(['slug' => 'redes-t4']);
    $tema->disciplinas()->attach($disciplina->id);

    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'protocolos TCP IP modelo camadas transporte roteamento',
    ]);

    Prism::fake([
        new StructuredResponse(
            steps: new Collection([]),
            text: '{}',
            structured: [
                'titulo' => 'Redes',
                'nos' => [[
                    'texto' => 'protocolos TCP IP modelo camadas',
                    'nivel' => 1,
                    'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
                ]],
            ],
            finishReason: FinishReason::Stop,
            usage: new Usage(100, 200),
            meta: new Meta('anthropic', 'claude-sonnet-4-6'),
            additionalContent: [],
        ),
    ]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(temaId: $tema->id)
    );

    expect($geracao->escopo['tema_id'])->toBe($tema->id);
});
