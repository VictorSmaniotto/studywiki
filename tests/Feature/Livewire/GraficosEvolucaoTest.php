<?php

use App\Filament\Pages\DesempenhoDashboard;
use App\Livewire\DisciplinaPage;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use App\Services\EvolucaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function criarSimuladoComResposta(
    string $slug,
    array $questoesME = [],
    array $respostas = [],
    int $acertos = 0,
    int $total = 0,
    ?array $notasDis = null,
    ?int $tempoRealizado = null,
    ?int $tempoEstimado = null,
): RespostaSimulado {
    $escopo = ['disciplina' => $slug, 'tempo_estimado_segundos' => $tempoEstimado ?? 0];

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => $escopo,
        'payload' => ['questoes_me' => $questoesME, 'questoes_dis' => []],
    ]);

    return RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => $respostas,
        'acertos' => $acertos,
        'total' => $total,
        'notas_dissertativas' => $notasDis,
        'tempo_realizado_segundos' => $tempoRealizado,
    ]);
}

// ──────────────────────────────────────────────
// G1 — scoresPorSessao
// ──────────────────────────────────────────────

it('G1: scoresPorSessao retorna score ME por sessão cronológica', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'redes']);

    criarSimuladoComResposta('redes', acertos: 4, total: 5);
    criarSimuladoComResposta('redes', acertos: 3, total: 5);

    $dados = app(EvolucaoService::class)->scoresPorSessao('redes');

    expect($dados)->toHaveCount(2)
        ->and($dados[0])->toHaveKeys(['data', 'score_me', 'score_dis'])
        ->and($dados[0]['score_me'])->toBe(80.0)
        ->and($dados[1]['score_me'])->toBe(60.0);
});

it('G1: scoresPorSessao inclui score dissertativo quando há notas', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'so']);

    criarSimuladoComResposta('so', acertos: 2, total: 2, notasDis: [
        ['notas' => [['criterio' => 'A', 'nota' => 0.8, 'feedback' => '']], 'nota_total' => 0.8, 'feedback_geral' => ''],
        ['notas' => [['criterio' => 'A', 'nota' => 0.6, 'feedback' => '']], 'nota_total' => 0.6, 'feedback_geral' => ''],
    ]);

    $dados = app(EvolucaoService::class)->scoresPorSessao('so');

    expect($dados)->toHaveCount(1)
        ->and($dados[0]['score_dis'])->toBe(70.0);
});

it('G1: scoresPorSessao retorna vazio quando disciplina não tem simulado respondido', function () {
    Disciplina::factory()->create(['slug' => 'bd']);

    $dados = app(EvolucaoService::class)->scoresPorSessao('bd');

    expect($dados)->toBeEmpty();
});

// ──────────────────────────────────────────────
// G2 — errosPorTopico
// ──────────────────────────────────────────────

it('G2: errosPorTopico conta erros de ME por heading do chunk', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Análise Léxica']);

    $questoesME = [
        [
            'enunciado' => 'Q1',
            'correta' => 'a',
            'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
            'alternativas' => ['a' => 'X', 'b' => 'Y', 'c' => 'Z', 'd' => 'W', 'e' => 'V'],
            'contexto' => '',
            'formato' => 'direto',
            'comentario_gabarito' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => ''],
        ],
    ];

    criarSimuladoComResposta(
        'compiladores',
        questoesME: $questoesME,
        respostas: ['0' => 'b'], // errou — correta era 'a'
        acertos: 0,
        total: 1,
    );

    $erros = app(EvolucaoService::class)->errosPorTopico('compiladores');

    expect($erros)->toHaveCount(1)
        ->and($erros[0]['heading'])->toBe('Análise Léxica')
        ->and($erros[0]['erros'])->toBe(1);
});

it('G2: errosPorTopico retorna vazio quando sem erros registrados', function () {
    Disciplina::factory()->create(['slug' => 'lp']);

    $erros = app(EvolucaoService::class)->errosPorTopico('lp');

    expect($erros)->toBeEmpty();
});

// ──────────────────────────────────────────────
// G3 — tempoVsEstimado
// ──────────────────────────────────────────────

it('G3: tempoVsEstimado retorna minutos realizados e estimados', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'ia']);

    criarSimuladoComResposta(
        'ia',
        acertos: 3,
        total: 5,
        tempoRealizado: 1800,
        tempoEstimado: 2160,
    );

    $dados = app(EvolucaoService::class)->tempoVsEstimado('ia');

    expect($dados)->toHaveCount(1)
        ->and($dados[0])->toHaveKeys(['data', 'realizado_min', 'estimado_min'])
        ->and($dados[0]['realizado_min'])->toBe(30.0)
        ->and($dados[0]['estimado_min'])->toBe(36.0);
});

it('G3: tempoVsEstimado ignora respostas sem tempo registrado', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'ed']);

    criarSimuladoComResposta('ed', acertos: 2, total: 4, tempoRealizado: null);

    $dados = app(EvolucaoService::class)->tempoVsEstimado('ed');

    expect($dados)->toBeEmpty();
});

// ──────────────────────────────────────────────
// G4 — distribuicaoQuestoes
// ──────────────────────────────────────────────

it('G4: distribuicaoQuestoes soma ME e dissertativas de todos os simulados', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'arq']);

    $questoesME = array_fill(0, 3, ['correta' => 'a', 'enunciado' => 'Q', 'fontes' => [], 'alternativas' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => ''], 'contexto' => '', 'formato' => 'direto', 'comentario_gabarito' => ['a' => '', 'b' => '', 'c' => '', 'd' => '', 'e' => '']]);

    Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'arq'],
        'payload' => ['questoes_me' => $questoesME, 'questoes_dis' => [['enunciado' => 'D1', 'rubrica' => [], 'gabarito_referencia' => '', 'fontes' => []]]],
    ]);

    $dist = app(EvolucaoService::class)->distribuicaoQuestoes('arq');

    expect($dist['me'])->toBe(3)
        ->and($dist['dissertativas'])->toBe(1);
});

it('G4: distribuicaoQuestoes retorna zeros quando sem simulados', function () {
    Disciplina::factory()->create(['slug' => 'mat']);

    $dist = app(EvolucaoService::class)->distribuicaoQuestoes('mat');

    expect($dist['me'])->toBe(0)
        ->and($dist['dissertativas'])->toBe(0);
});

// ──────────────────────────────────────────────
// G5 — criteriosMaisPerdidos
// ──────────────────────────────────────────────

it('G5: criteriosMaisPerdidos agrega média de pontos perdidos por critério', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'prog']);

    criarSimuladoComResposta('prog', acertos: 0, total: 0, notasDis: [
        [
            'nota_total' => 0.5,
            'feedback_geral' => '',
            'notas' => [
                ['criterio' => 'Clareza', 'nota' => 0.5, 'feedback' => ''],
                ['criterio' => 'Completude', 'nota' => 0.8, 'feedback' => ''],
            ],
        ],
    ]);

    criarSimuladoComResposta('prog', acertos: 0, total: 0, notasDis: [
        [
            'nota_total' => 0.3,
            'feedback_geral' => '',
            'notas' => [
                ['criterio' => 'Clareza', 'nota' => 0.3, 'feedback' => ''],
                ['criterio' => 'Completude', 'nota' => 1.0, 'feedback' => ''],
            ],
        ],
    ]);

    $criterios = app(EvolucaoService::class)->criteriosMaisPerdidos('prog');

    $clareza = collect($criterios)->firstWhere('criterio', 'Clareza');
    $completude = collect($criterios)->firstWhere('criterio', 'Completude');

    // Clareza: média perdida = ((1-0.5) + (1-0.3)) / 2 = (0.5+0.7)/2 = 0.6 → 60%
    expect($clareza)->not->toBeNull()
        ->and($clareza['media_perdido'])->toBe(60.0);

    // Completude: ((1-0.8) + (1-1.0))/2 = (0.2+0)/2 = 0.1 → 10%
    expect($completude)->not->toBeNull()
        ->and($completude['media_perdido'])->toBe(10.0);

    // Ordenado por mais perdido primeiro
    expect($criterios[0]['criterio'])->toBe('Clareza');
});

it('G5: criteriosMaisPerdidos retorna vazio quando sem dissertativas avaliadas', function () {
    Disciplina::factory()->create(['slug' => 'fis']);

    $criterios = app(EvolucaoService::class)->criteriosMaisPerdidos('fis');

    expect($criterios)->toBeEmpty();
});

// ──────────────────────────────────────────────
// G6 — aba Evolução na DisciplinaPage
// ──────────────────────────────────────────────

it('G6: DisciplinaPage exibe a aba Evolução', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'evo-test']);
    Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Evolução');
});

// ──────────────────────────────────────────────
// G7 — gráficos globais no Filament
// ──────────────────────────────────────────────

it('G7: DesempenhoDashboard expõe getDadosGraficosGlobais com estrutura correta', function () {
    Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'x'],
        'payload' => [],
        'custo_tokens' => 100,
    ]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosGraficosGlobais();

    expect($dados)->toHaveKeys(['scores_por_disciplina', 'criterios_perdidos'])
        ->and($dados['scores_por_disciplina'])->toBeArray()
        ->and($dados['criterios_perdidos'])->toBeArray();
});

it('G7: getDadosGraficosGlobais inclui disciplinas com simulados respondidos', function () {
    $g = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'quimica'],
        'payload' => [],
        'custo_tokens' => 100,
    ]);
    RespostaSimulado::create([
        'geracao_id' => $g->id,
        'respostas' => [],
        'acertos' => 7,
        'total' => 10,
    ]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosGraficosGlobais();

    $quimica = collect($dados['scores_por_disciplina'])->firstWhere('disciplina', 'quimica');

    expect($quimica)->not->toBeNull()
        ->and($quimica['media_score'])->toBe(70.0);
});

// ──────────────────────────────────────────────
// G8 — Chart.js presente no bundle
// ──────────────────────────────────────────────

it('G8: app.js importa Chart.js e o expõe globalmente', function () {
    $appJs = file_get_contents(base_path('resources/js/app.js'));

    expect($appJs)
        ->toContain('chart.js')
        ->toContain('window.Chart');
});

// ──────────────────────────────────────────────
// G9 — estado vazio
// ──────────────────────────────────────────────

it('G9: aba Evolução exibe estado vazio quando sem dados de simulado', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'vazia']);
    Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    Livewire::test(DisciplinaPage::class, ['slug' => $disciplina->slug])
        ->assertSee('Sem dados de evolução ainda');
});
