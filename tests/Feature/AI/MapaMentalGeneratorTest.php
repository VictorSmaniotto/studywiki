<?php

use App\Livewire\DisciplinaPage;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\MapaMentalGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function fakeMapaResponse(array $structured, int $inputTokens = 100, int $outputTokens = 200): StructuredResponse
{
    return new StructuredResponse(
        steps: new Collection([]),
        text: json_encode($structured),
        structured: $structured,
        finishReason: FinishReason::Stop,
        usage: new Usage($inputTokens, $outputTokens),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

function mapaAncorado(int $paginaId, int $chunkId): array
{
    return [
        'titulo' => 'Redes de Computadores',
        'nos' => [
            [
                'texto' => 'Protocolos TCP IP modelo camadas',
                'nivel' => 1,
                'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
            ],
            [
                'texto' => 'Camada transporte UDP TCP',
                'nivel' => 2,
                'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
            ],
        ],
    ];
}

function criarContextoMapa(
    string $disciplinaSlug = 'redes',
    string $conteudo = 'protocolos TCP IP modelo camadas transporte UDP roteamento pacotes'
): array {
    $disciplina = Disciplina::factory()->create(['nome' => 'Redes', 'slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => $conteudo,
    ]);

    return [$disciplina, $pagina, $chunk];
}

// ─── MM1: gerador produz código Mermaid válido ───────────────────────────

it('MM1 — gera mapa mental com código Mermaid válido', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    Prism::fake([fakeMapaResponse(mapaAncorado($pagina->id, $chunk->id))]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect($geracao->status)->toBe('ok');
    expect($geracao->payload['titulo'])->toBe('Redes de Computadores');
    expect($geracao->payload['nos'])->toHaveCount(2);

    $mermaid = MapaMentalGenerator::gerarMermaidCode(
        $geracao->payload['titulo'],
        $geracao->payload['nos']
    );
    expect($mermaid)->toContain('mindmap');
    expect($mermaid)->toContain('root((');
    expect($mermaid)->toContain('Redes de Computadores');
});

// ─── MM2: GroundingValidator valida cada nó ──────────────────────────────

it('MM2 — rejeita mapa quando nó não está ancorado nos chunks', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    $payloadSemAncoragem = [
        'titulo' => 'Tema',
        'nos' => [
            [
                'texto' => 'Conceito inventado sem relação',
                'nivel' => 1,
                'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
            ],
        ],
    ];

    Prism::fake([
        fakeMapaResponse($payloadSemAncoragem),
        fakeMapaResponse($payloadSemAncoragem),
    ]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect($geracao->status)->toBe('rejeitado');
});

it('MM2 — rejeita mapa quando nó cita pagina_id fantasma', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    $payloadFonteFalsa = [
        'titulo' => 'Tema',
        'nos' => [
            [
                'texto' => 'Protocolos TCP IP',
                'nivel' => 1,
                'fontes' => [['pagina_id' => 99999, 'chunk_id' => 99999]],
            ],
        ],
    ];

    Prism::fake([
        fakeMapaResponse($payloadFonteFalsa),
        fakeMapaResponse($payloadFonteFalsa),
    ]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect($geracao->status)->toBe('rejeitado');
});

// ─── MM3: tipo='mapa_mental' persistido ──────────────────────────────────

it('MM3 — persiste Geracao com tipo mapa_mental', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    Prism::fake([fakeMapaResponse(mapaAncorado($pagina->id, $chunk->id))]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect($geracao->tipo)->toBe('mapa_mental');
    expect(Geracao::where('tipo', 'mapa_mental')->count())->toBe(1);
});

it('MM3 — persiste GeracaoFonte quando status ok', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    Prism::fake([fakeMapaResponse(mapaAncorado($pagina->id, $chunk->id))]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBeGreaterThan(0);
});

// ─── MM3: escopo salvo com disciplina ────────────────────────────────────

it('MM3 — escopo salvo contém slug da disciplina', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    Prism::fake([fakeMapaResponse(mapaAncorado($pagina->id, $chunk->id))]);

    $geracao = app(MapaMentalGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug)
    );

    expect($geracao->escopo['disciplina'])->toBe($disciplina->slug);
});

// ─── MM4: DisciplinaPage tem método gerarMapaMental ──────────────────────

it('MM4 — DisciplinaPage expõe gerarMapaMental', function () {
    expect(method_exists(DisciplinaPage::class, 'gerarMapaMental'))->toBeTrue();
});

// ─── MM5: view contém elemento .mermaid e botão Gerar Mapa Mental ────────

it('MM5 — view disciplina contém classe mermaid e botão de geração', function () {
    $disciplina = Disciplina::factory()->create();

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Mapa Mental')
        ->assertSee('Gerar Mapa Mental');
});

// ─── MM6: histórico de mapas mentais aparece na view ─────────────────────

it('MM6 — lista gerações de mapa_mental no histórico', function () {
    [$disciplina, $pagina, $chunk] = criarContextoMapa();

    Geracao::factory()->create([
        'tipo' => 'mapa_mental',
        'status' => 'ok',
        'custo_tokens' => 777,
        'escopo' => ['disciplina' => $disciplina->slug, 'tags' => [], 'paginas' => [], 'query' => null, 'tema_id' => null],
        'payload' => ['titulo' => 'Mapa Redes', 'nos' => []],
    ]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('777')
        ->assertSee('Mapa Redes');
});

// ─── gerarMermaidCode: função utilitária ─────────────────────────────────

it('gerarMermaidCode produz sintaxe mindmap correta', function () {
    $nos = [
        ['texto' => 'Subtema A', 'nivel' => 1],
        ['texto' => 'Detalhe A1', 'nivel' => 2],
        ['texto' => 'Subtema B', 'nivel' => 1],
    ];

    $code = MapaMentalGenerator::gerarMermaidCode('Tema Central', $nos);

    expect($code)->toStartWith('mindmap');
    expect($code)->toContain('root((Tema Central))');
    expect($code)->toContain('Subtema A');
    expect($code)->toContain('Detalhe A1');
    expect($code)->toContain('Subtema B');
});
