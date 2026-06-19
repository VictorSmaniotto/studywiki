<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\Pagina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function simuladoResponse(int $paginaId, int $chunkId, string $texto = 'compiladores analisam léxico'): StructuredResponse
{
    $questao = [
        'contexto' => "Contexto sobre {$texto}.",
        'enunciado' => "O que é {$texto}?",
        'formato' => 'direto',
        'alternativas' => [
            'a' => "Definição correta sobre {$texto} compiladores analisam",
            'b' => 'Alternativa errada B',
            'c' => 'Alternativa errada C',
            'd' => 'Alternativa errada D',
            'e' => 'Alternativa errada E',
        ],
        'correta' => 'a',
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
        'comentario_gabarito' => [
            'a' => 'Correto, está nos chunks.',
            'b' => 'Incorreto.',
            'c' => 'Incorreto.',
            'd' => 'Incorreto.',
            'e' => 'Incorreto.',
        ],
    ];

    return new StructuredResponse(
        steps: new Collection([]),
        text: json_encode(['questoes_me' => [$questao], 'questoes_dis' => []]),
        structured: ['questoes_me' => [$questao], 'questoes_dis' => []],
        finishReason: FinishReason::Stop,
        usage: new Usage(100, 200),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

function criarDisciplinaComChunk(string $slug = 'compiladores', string $conteudo = 'compiladores analisam léxico código'): array
{
    $disciplina = Disciplina::factory()->create(['slug' => $slug, 'nome' => 'Compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'conteudo' => $conteudo]);

    return compact('disciplina', 'pagina', 'chunk');
}

// ─── AC: roda ponta a ponta e imprime simulado ─────────────────────────────

it('roda com sucesso e imprime as questões', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarDisciplinaComChunk();
    Prism::fake([simuladoResponse($pagina->id, $chunk->id)]);

    $this->artisan('studywiki:simulado', ['disciplina' => $disciplina->slug, '--n' => 1])
        ->expectsOutputToContain('Gerando simulado')
        ->expectsOutputToContain('Questão 1')
        ->expectsQuestion('Mostrar gabarito comentado?', false)
        ->assertExitCode(0);
});

it('imprime gabarito comentado com flag --gabarito', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarDisciplinaComChunk();
    Prism::fake([simuladoResponse($pagina->id, $chunk->id)]);

    $this->artisan('studywiki:simulado', [
        'disciplina' => $disciplina->slug,
        '--n' => 1,
        '--gabarito' => true,
    ])
        ->expectsOutputToContain('Questão 1')
        ->expectsOutputToContain('GABARITO COMENTADO')
        ->expectsOutputToContain('Gabarito:')
        ->assertExitCode(0);
});

it('persiste Geracao com status ok ao finalizar', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarDisciplinaComChunk();
    Prism::fake([simuladoResponse($pagina->id, $chunk->id)]);

    $this->artisan('studywiki:simulado', ['disciplina' => $disciplina->slug, '--n' => 1])
        ->expectsQuestion('Mostrar gabarito comentado?', false)
        ->assertExitCode(0);

    expect(Geracao::where('tipo', 'simulado')->where('status', 'ok')->count())->toBe(1);
});

// ─── AC: disciplina não encontrada → FAILURE ──────────────────────────────

it('falha com mensagem de erro quando disciplina nao existe', function () {
    $this->artisan('studywiki:simulado', ['disciplina' => 'inexistente'])
        ->expectsOutputToContain('Disciplina não encontrada')
        ->assertExitCode(1);
});

it('lista disciplinas disponíveis quando disciplina nao encontrada', function () {
    criarDisciplinaComChunk('compiladores', 'compiladores analisam léxico');

    $this->artisan('studywiki:simulado', ['disciplina' => 'inexistente'])
        ->expectsOutputToContain('compiladores')
        ->assertExitCode(1);
});

// ─── AC: simulado rejeitado → FAILURE ────────────────────────────────────

it('falha com mensagem quando geracao e rejeitada', function () {
    ['disciplina' => $disciplina] = criarDisciplinaComChunk();

    // Duas respostas com fonte fantasma → rejeitado
    $questaoFantasma = [
        'contexto' => 'Contexto.',
        'enunciado' => 'Questão?',
        'formato' => 'direto',
        'alternativas' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
        'correta' => 'a',
        'fontes' => [['pagina_id' => 9999, 'chunk_id' => 9999]],
        'comentario_gabarito' => ['a' => 'ok', 'b' => 'x', 'c' => 'x', 'd' => 'x', 'e' => 'x'],
    ];

    $responseFantasma = new StructuredResponse(
        steps: new Collection([]),
        text: '',
        structured: ['questoes_me' => [$questaoFantasma], 'questoes_dis' => []],
        finishReason: FinishReason::Stop,
        usage: new Usage(50, 50),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );

    Prism::fake([$responseFantasma, $responseFantasma]);

    $this->artisan('studywiki:simulado', ['disciplina' => $disciplina->slug, '--n' => 1])
        ->expectsOutputToContain('Simulado rejeitado')
        ->assertExitCode(1);
});

// ─── AC: opções --n e --dif ────────────────────────────────────────────────

it('aceita opcao --dif', function () {
    ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk] = criarDisciplinaComChunk();
    Prism::fake([simuladoResponse($pagina->id, $chunk->id)]);

    $this->artisan('studywiki:simulado', [
        'disciplina' => $disciplina->slug,
        '--n' => 1,
        '--dif' => 'dificil',
    ])
        ->expectsOutputToContain('dificil')
        ->expectsQuestion('Mostrar gabarito comentado?', false)
        ->assertExitCode(0);
});
