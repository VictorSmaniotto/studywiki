<?php

use App\Livewire\SimuladoPage;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function questaoFake(string $correta = 'a'): array
{
    return [
        'contexto' => 'Contexto sobre compiladores.',
        'enunciado' => 'O que é análise léxica?',
        'formato' => 'direto',
        'alternativas' => ['a' => 'Tokenização', 'b' => 'Parsing', 'c' => 'Codegen', 'd' => 'Otimização', 'e' => 'Linking'],
        'correta' => $correta,
        'fontes' => [],
        'comentario_gabarito' => ['a' => 'Correto.', 'b' => 'Errado.', 'c' => 'Errado.', 'd' => 'Errado.', 'e' => 'Errado.'],
    ];
}

function criarSimulado(int $numQuestoes = 2): Geracao
{
    $questoes = array_map(fn ($i) => questaoFake($i === 0 ? 'a' : 'b'), range(0, $numQuestoes - 1));

    return Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes' => $questoes],
        'escopo' => ['disciplina' => 'compiladores', 'tags' => [], 'paginas' => []],
    ]);
}

// ─── Renderização ─────────────────────────────────────────────────────────

it('renderiza as questões do simulado', function () {
    $geracao = criarSimulado(2);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->assertSee('Questão 1')
        ->assertSee('Questão 2')
        ->assertSee('O que é análise léxica?')
        ->assertSee('Enviar respostas');
});

it('retorna 404 para simulado inexistente', function () {
    $this->get(route('simulado', 9999))->assertNotFound();
});

it('retorna 404 para geracao que não é simulado', function () {
    $geracao = Geracao::factory()->create(['tipo' => 'resumo', 'status' => 'ok', 'payload' => []]);
    $this->get(route('simulado', $geracao->id))->assertNotFound();
});

// ─── AC: salva N de M e mostra gabarito ───────────────────────────────────

it('salva resposta e exibe resultado N de M após envio', function () {
    $geracao = criarSimulado(2);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a', '1' => 'b'])
        ->call('enviar')
        ->assertSee('2 de 2');

    expect(RespostaSimulado::where('geracao_id', $geracao->id)->count())->toBe(1);
    expect(RespostaSimulado::where('geracao_id', $geracao->id)->first()->acertos)->toBe(2);
});

it('conta acertos corretamente com respostas parcialmente erradas', function () {
    $geracao = criarSimulado(2);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a', '1' => 'c'])
        ->call('enviar')
        ->assertSee('1 de 2');

    expect(RespostaSimulado::where('geracao_id', $geracao->id)->first()->acertos)->toBe(1);
});

it('exibe gabarito comentado após envio', function () {
    $geracao = criarSimulado(1);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a'])
        ->call('enviar')
        ->assertSee('Correto.')
        ->assertSee('Errado.');
});

it('não persiste segunda resposta se já foi enviado', function () {
    $geracao = criarSimulado(1);

    $component = Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a'])
        ->call('enviar');

    $component->call('enviar');

    expect(RespostaSimulado::where('geracao_id', $geracao->id)->count())->toBe(1);
});

// ─── Fontes visíveis após envio ───────────────────────────────────────────

it('exibe fonte da questão no gabarito quando pagina existe', function () {
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id, 'titulo' => 'Análise Léxica']);

    $questao = questaoFake('a');
    $questao['fontes'] = [['pagina_id' => $pagina->id, 'chunk_id' => 1]];

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes' => [$questao]],
        'escopo' => ['disciplina' => 'compiladores', 'tags' => [], 'paginas' => []],
    ]);

    GeracaoFonte::create(['geracao_id' => $geracao->id, 'pagina_id' => $pagina->id]);

    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->set('respostas', ['0' => 'a'])
        ->call('enviar')
        ->assertSee('Análise Léxica');
});
