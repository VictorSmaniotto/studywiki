<?php

use App\Livewire\SimuladoPage;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use App\Services\AI\AvaliacaoDissertativaService;
use App\Services\AI\SimuladoGenerator;
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

function questaoMEHibrido(int $paginaId, int $chunkId): array
{
    return [
        'contexto' => 'Contexto sobre compiladores analisam código.',
        'enunciado' => 'O que é análise léxica de compiladores?',
        'formato' => 'direto',
        'alternativas' => [
            'a' => 'Tokenização compiladores analisam',
            'b' => 'B errada',
            'c' => 'C errada',
            'd' => 'D errada',
            'e' => 'E errada',
        ],
        'correta' => 'a',
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
        'comentario_gabarito' => ['a' => 'Correto.', 'b' => 'Errado.', 'c' => 'Errado.', 'd' => 'Errado.', 'e' => 'Errado.'],
    ];
}

function questaoDissertativaHibrido(int $paginaId, int $chunkId): array
{
    return [
        'enunciado' => 'Explique o processo de análise léxica em compiladores.',
        'rubrica' => [
            ['criterio' => 'Define análise léxica corretamente', 'peso' => 0.5],
            ['criterio' => 'Apresenta exemplo de token', 'peso' => 0.5],
        ],
        'gabarito_referencia' => 'Análise léxica converte compiladores código fonte em tokens.',
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
    ];
}

function fakeAvaliacaoResponse(float $notaTotal = 0.8): StructuredResponse
{
    return new StructuredResponse(
        steps: new Collection([]),
        text: '',
        structured: [
            'notas' => [
                ['criterio' => 'Define análise léxica corretamente', 'nota' => 0.9, 'feedback' => 'Correto.'],
                ['criterio' => 'Apresenta exemplo de token', 'nota' => $notaTotal * 2 - 0.9, 'feedback' => 'Parcialmente correto.'],
            ],
            'nota_total' => $notaTotal,
            'feedback_geral' => 'Boa resposta no geral.',
        ],
        finishReason: FinishReason::Stop,
        usage: new Usage(50, 100),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

function criarSetup(): array
{
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'compiladores analisam código fonte léxico tokens',
    ]);

    return compact('disciplina', 'pagina', 'chunk');
}

function criarGeracaoHibrida(int $nMe = 2, int $nDis = 1): Geracao
{
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'conteudo' => 'compiladores analisam']);

    $questoesME = array_fill(0, $nMe, questaoMEHibrido($pagina->id, $chunk->id));
    $questoesDis = array_fill(0, $nDis, questaoDissertativaHibrido($pagina->id, $chunk->id));

    return Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes_me' => $questoesME, 'questoes_dis' => $questoesDis],
        'escopo' => ['disciplina' => 'compiladores', 'tags' => [], 'paginas' => [], 'query' => null],
    ]);
}

// ─── H1: gerar(n_me, n_dis) → payload com questoes_me e questoes_dis ─────

it('H1: gerar com n_me=3 e n_dis=2 produz payload com ambos os arrays', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarSetup();

    $me1 = questaoMEHibrido($pagina->id, $chunk->id);
    $me2 = questaoMEHibrido($pagina->id, $chunk->id);
    $me3 = questaoMEHibrido($pagina->id, $chunk->id);
    $dis1 = questaoDissertativaHibrido($pagina->id, $chunk->id);
    $dis2 = questaoDissertativaHibrido($pagina->id, $chunk->id);

    $payload = ['questoes_me' => [$me1, $me2, $me3], 'questoes_dis' => [$dis1, $dis2]];

    Prism::fake([new StructuredResponse(
        steps: new Collection([]),
        text: json_encode($payload),
        structured: $payload,
        finishReason: FinishReason::Stop,
        usage: new Usage(100, 200),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    )]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        n_me: 3,
        n_dis: 2,
    );

    expect($geracao->status)->toBe('ok')
        ->and($geracao->payload['questoes_me'])->toHaveCount(3)
        ->and($geracao->payload['questoes_dis'])->toHaveCount(2);
});

// ─── H2: dissertativas têm rubrica + gabarito_referencia ─────────────────

it('H2: questões dissertativas têm rubrica com criterio/peso e gabarito_referencia', function () {
    $geracao = criarGeracaoHibrida(1, 2);

    foreach ($geracao->payload['questoes_dis'] as $q) {
        expect($q)->toHaveKey('rubrica')
            ->and($q)->toHaveKey('gabarito_referencia')
            ->and($q)->toHaveKey('enunciado');

        foreach ($q['rubrica'] as $criterio) {
            expect($criterio)->toHaveKey('criterio')
                ->and($criterio)->toHaveKey('peso');
        }
    }
});

// ─── H3: GroundingValidator valida dissertativas ──────────────────────────

it('H3: geração é rejeitada quando dissertativa referencia fonte fantasma', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarSetup();

    $meValida = questaoMEHibrido($pagina->id, $chunk->id);
    $disFantasma = questaoDissertativaHibrido(9999, 9999);

    $payload = ['questoes_me' => [$meValida], 'questoes_dis' => [$disFantasma]];
    $fakeResponse = new StructuredResponse(
        steps: new Collection([]),
        text: '',
        structured: $payload,
        finishReason: FinishReason::Stop,
        usage: new Usage(50, 100),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );

    Prism::fake([$fakeResponse, $fakeResponse]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        n_me: 1,
        n_dis: 1,
    );

    expect($geracao->status)->toBe('rejeitado');
});

// ─── H4: AvaliacaoDissertativaService retorna notas por critério ──────────

it('H4: AvaliacaoDissertativaService retorna nota_total e notas por critério', function () {
    $questaoDis = questaoDissertativaHibrido(1, 1);

    Prism::fake([fakeAvaliacaoResponse(0.75)]);

    $service = app(AvaliacaoDissertativaService::class);
    $resultado = $service->avaliar($questaoDis, 'Análise léxica é a tokenização do código fonte.');

    expect($resultado)->toHaveKey('nota_total')
        ->and($resultado)->toHaveKey('notas')
        ->and($resultado)->toHaveKey('feedback_geral')
        ->and($resultado['nota_total'])->toBe(0.75)
        ->and($resultado['notas'])->toBeArray()
        ->and($resultado['notas'][0])->toHaveKeys(['criterio', 'nota', 'feedback']);
});

// ─── H5: SimuladoPage renderiza ME (radio) + dissertativas (textarea) ─────

it('H5: SimuladoPage exibe questões ME com radio e dissertativas com textarea', function () {
    $geracao = criarGeracaoHibrida(2, 1);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->assertSee('Múltipla Escolha')
        ->assertSee('Dissertativas')
        ->assertSee('Questão 1')
        ->assertSee('Dissertativa 1')
        ->assertSee('Sua resposta')
        ->assertSee('Critérios de avaliação');
});

// ─── H6: gabarito visível após conclusão com notas dissertativas ──────────

it('H6: após enviar, gabarito ME e rubrica com notas dissertativas ficam visíveis', function () {
    $geracao = criarGeracaoHibrida(1, 1);

    $avaliacaoMock = $this->mock(AvaliacaoDissertativaService::class);
    $avaliacaoMock->shouldReceive('avaliar')->once()->andReturn([
        'notas' => [
            ['criterio' => 'Define análise léxica corretamente', 'nota' => 0.9, 'feedback' => 'Excelente definição.'],
        ],
        'nota_total' => 0.9,
        'feedback_geral' => 'Muito boa resposta.',
    ]);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a'])
        ->set('respostasDissertativas', ['0' => 'Análise léxica tokeniza o código.'])
        ->call('enviar')
        ->assertSee('Correto.')
        ->assertSee('Avaliação por critério')
        ->assertSee('Define análise léxica corretamente')
        ->assertSee('Muito boa resposta.');
});

// ─── H7: RespostaSimulado salva acertos ME + notas dissertativas ──────────

it('H7: RespostaSimulado persiste acertos ME e notas_dissertativas', function () {
    $geracao = criarGeracaoHibrida(2, 1);

    $avaliacaoMock = $this->mock(AvaliacaoDissertativaService::class);
    $avaliacaoMock->shouldReceive('avaliar')->once()->andReturn([
        'notas' => [['criterio' => 'Critério X', 'nota' => 0.8, 'feedback' => 'ok']],
        'nota_total' => 0.8,
        'feedback_geral' => 'Bom.',
    ]);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a', '1' => 'a'])
        ->set('respostasDissertativas', ['0' => 'Minha resposta dissertativa.'])
        ->call('enviar');

    $resposta = RespostaSimulado::where('geracao_id', $geracao->id)->first();

    expect($resposta)->not->toBeNull()
        ->and($resposta->acertos)->toBe(2)
        ->and($resposta->total)->toBe(2)
        ->and($resposta->notas_dissertativas)->toBeArray()
        ->and($resposta->notas_dissertativas[0]['nota_total'])->toBe(0.8);
});

// ─── H8: notas_dissertativas persistem e são recuperáveis ────────────────

it('H8: notas_dissertativas são persistidas e recuperáveis do banco', function () {
    $geracao = criarGeracaoHibrida(1, 2);

    RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => ['0' => 'a'],
        'acertos' => 1,
        'total' => 1,
        'respostas_dissertativas' => ['0' => 'Resp 1', '1' => 'Resp 2'],
        'notas_dissertativas' => [
            ['nota_total' => 0.9, 'notas' => [['criterio' => 'C1', 'nota' => 0.9, 'feedback' => 'ok']], 'feedback_geral' => 'Ótimo.'],
            ['nota_total' => 0.5, 'notas' => [['criterio' => 'C1', 'nota' => 0.5, 'feedback' => 'regular']], 'feedback_geral' => 'Regular.'],
        ],
    ]);

    $recuperado = RespostaSimulado::where('geracao_id', $geracao->id)->first();

    expect($recuperado->notas_dissertativas)->toBeArray()
        ->and($recuperado->notas_dissertativas)->toHaveCount(2)
        ->and($recuperado->notas_dissertativas[0]['nota_total'])->toBe(0.9)
        ->and($recuperado->notas_dissertativas[1]['nota_total'])->toBe(0.5)
        ->and($recuperado->respostas_dissertativas)->toHaveCount(2);
});
