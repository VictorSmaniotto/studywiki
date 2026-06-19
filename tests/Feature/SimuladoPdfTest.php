<?php

use App\Livewire\DisciplinaPage;
use App\Livewire\SimuladoPage;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function simPdfQuestao(string $correta = 'a'): array
{
    return [
        'contexto' => 'Contexto de teste.',
        'enunciado' => 'Enunciado de teste?',
        'formato' => 'direto',
        'alternativas' => ['a' => 'Alt A', 'b' => 'Alt B', 'c' => 'Alt C', 'd' => 'Alt D', 'e' => 'Alt E'],
        'correta' => $correta,
        'fontes' => [],
        'comentario_gabarito' => ['a' => 'Comentário A', 'b' => 'Comentário B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
    ];
}

function simPdfDissertativa(): array
{
    return [
        'enunciado' => 'Explique o conceito.',
        'rubrica' => [['criterio' => 'Clareza', 'peso' => 50], ['criterio' => 'Precisão', 'peso' => 50]],
        'gabarito_referencia' => 'O conceito é X porque Y.',
        'fontes' => [],
    ];
}

function criarGeracaoParaPdf(int $nMe = 2, int $nDis = 0): Geracao
{
    $questoesME = $nMe > 0 ? array_map(fn ($i) => simPdfQuestao($i === 0 ? 'a' : 'b'), range(0, $nMe - 1)) : [];
    $questoesDis = $nDis > 0 ? array_map(fn () => simPdfDissertativa(), range(0, $nDis - 1)) : [];

    return Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes_me' => $questoesME, 'questoes_dis' => $questoesDis],
        'escopo' => [
            'disciplina' => 'compiladores',
            'perfil' => 'universitario',
            'tempo_estimado_segundos' => 2160,
        ],
    ]);
}

// ─── E8: DomPDF, rota e content-type ─────────────────────────────────────────

it('E8: rota simulado.pdf retorna application/pdf para simulado ok', function () {
    $geracao = criarGeracaoParaPdf();

    $this->get(route('simulado.pdf', $geracao->id).'?secoes[]=prova_branca')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('E8: retorna 404 para simulado inexistente', function () {
    $this->get(route('simulado.pdf', 9999))->assertNotFound();
});

it('E8: retorna 404 para geracao que não é simulado', function () {
    $geracao = Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'ok', 'payload' => []]);
    $this->get(route('simulado.pdf', $geracao->id))->assertNotFound();
});

it('E8: retorna 404 para simulado com status rejeitado', function () {
    $geracao = Geracao::factory()->create(['tipo' => 'simulado', 'status' => 'rejeitado', 'payload' => []]);
    $this->get(route('simulado.pdf', $geracao->id))->assertNotFound();
});

// ─── E2: Prova em branco ──────────────────────────────────────────────────────

it('E2: PDF prova_branca renderiza enunciados sem gabarito', function () {
    $geracao = criarGeracaoParaPdf();

    // Testa a view diretamente para verificar conteúdo
    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['prova_branca'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => 'universitario',
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('Prova em Branco')
        ->toContain('Enunciado de teste?')
        ->toContain('Alt A')
        ->not->toContain('Gabarito Comentado')
        ->not->toContain('Comentário A');
});

// ─── E3: Gabarito comentado ───────────────────────────────────────────────────

it('E3: PDF gabarito mostra resposta correta e comentários', function () {
    $geracao = criarGeracaoParaPdf();

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['gabarito'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('Gabarito Comentado')
        ->toContain('Gabarito: A')
        ->toContain('Comentário A');
});

it('E3: PDF gabarito com dissertativa inclui gabarito de referência', function () {
    $geracao = criarGeracaoParaPdf(nMe: 1, nDis: 1);

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['gabarito'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => $geracao->payload['questoes_dis'],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('O conceito é X porque Y.')
        ->toContain('Clareza');
});

// ─── E4: Minhas respostas ─────────────────────────────────────────────────────

it('E4: PDF respostas mostra pontuação e resposta do aluno', function () {
    $geracao = criarGeracaoParaPdf(nMe: 2);

    $resposta = RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => ['0' => 'a', '1' => 'c'],
        'acertos' => 1,
        'total' => 2,
        'respostas_dissertativas' => null,
        'notas_dissertativas' => null,
        'tempo_realizado_segundos' => null,
    ]);

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['respostas'],
        'resposta' => $resposta,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('Minhas Respostas e Resultado')
        ->toContain('1.0 / 2')
        ->toContain('50%');
});

it('E4: seção respostas não aparece se resposta não existe', function () {
    $geracao = criarGeracaoParaPdf();

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['respostas'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)->not->toContain('Minhas Respostas e Resultado');
});

// ─── E7: Cabeçalho, questões numeradas, fontes no rodapé ─────────────────────

it('E7: PDF contém cabeçalho com disciplina e perfil', function () {
    $geracao = criarGeracaoParaPdf();

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['prova_branca'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => 'universitario',
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('Compiladores')
        ->toContain('Universitario')
        ->toContain('page-header');
});

it('E7: PDF contém rodapé com fontes', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id, 'titulo' => 'Análise Léxica']);
    $geracao = criarGeracaoParaPdf();
    $fonte = GeracaoFonte::create(['geracao_id' => $geracao->id, 'pagina_id' => $pagina->id]);

    $geracao->load('fontes.pagina');
    $fontesPaginas = $geracao->fontes->keyBy('pagina_id');

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['prova_branca'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => $fontesPaginas,
    ])->render();

    expect($html)
        ->toContain('page-footer')
        ->toContain('Análise Léxica');
});

it('E7: questões são numeradas sequencialmente', function () {
    $geracao = criarGeracaoParaPdf(nMe: 3);

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['prova_branca'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('1.')
        ->toContain('2.')
        ->toContain('3.');
});

// ─── E1: Modal com checkboxes nas views Livewire ──────────────────────────────

it('E1/E5: SimuladoPage exibe botão PDF antes do envio', function () {
    $geracao = criarGeracaoParaPdf();

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->assertSee('PDF')
        ->assertSeeHtml('prova_branca');
});

it('E1/E6: SimuladoPage exibe botão PDF após envio com checkbox respostas habilitado', function () {
    $geracao = criarGeracaoParaPdf();

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a', '1' => 'b'])
        ->call('enviar')
        ->assertSee('PDF')
        ->assertSeeHtml('respostas');
});

it('E1/E5: DisciplinaPage exibe botão PDF para simulado gerado', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores', 'nome' => 'Compiladores']);
    $geracao = criarGeracaoParaPdf();

    Livewire::test(DisciplinaPage::class, ['slug' => 'compiladores'])
        ->call('toggleExpandir', $geracao->id)
        ->assertSee('PDF');
});

// ─── Múltiplas seções no mesmo PDF ───────────────────────────────────────────

it('PDF pode conter prova_branca e gabarito juntos', function () {
    $geracao = criarGeracaoParaPdf();

    $html = view('pdf.simulado', [
        'geracao' => $geracao,
        'secoes' => ['prova_branca', 'gabarito'],
        'resposta' => null,
        'disciplina' => 'compiladores',
        'perfil' => null,
        'questoesME' => $geracao->payload['questoes_me'],
        'questoesDis' => [],
        'fontesPaginas' => collect(),
    ])->render();

    expect($html)
        ->toContain('Prova em Branco')
        ->toContain('Gabarito Comentado');
});

it('secoes inválidas são ignoradas e prova_branca é usada como fallback', function () {
    $geracao = criarGeracaoParaPdf();

    $this->get(route('simulado.pdf', $geracao->id).'?secoes[]=invalida')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
