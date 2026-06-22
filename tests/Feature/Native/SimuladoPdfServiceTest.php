<?php

use App\Livewire\SimuladoPage;
use App\Models\Geracao;
use App\Services\SimuladoPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function geracaoSimuladoPdf(): Geracao
{
    return Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'payload' => ['questoes_me' => [[
            'contexto' => 'Ctx.',
            'enunciado' => 'Enunciado?',
            'formato' => 'direto',
            'alternativas' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
            'correta' => 'a',
            'fontes' => [],
            'comentario_gabarito' => ['a' => 'CA', 'b' => 'CB', 'c' => 'CC', 'd' => 'CD', 'e' => 'CE'],
        ]], 'questoes_dis' => []],
        'escopo' => ['disciplina' => 'compiladores'],
    ]);
}

it('normaliza seções inválidas para prova_branca', function () {
    $service = app(SimuladoPdfService::class);

    expect($service->normalizarSecoes(['invalida', 'xpto']))->toBe(['prova_branca'])
        ->and($service->normalizarSecoes([]))->toBe(['prova_branca'])
        ->and($service->normalizarSecoes(['gabarito', 'invalida']))->toBe(['gabarito']);
});

it('monta o PDF e gera nome de arquivo determinístico', function () {
    $geracao = geracaoSimuladoPdf();
    $service = app(SimuladoPdfService::class);

    $pdf = $service->montar($geracao->id, ['prova_branca']);

    expect($service->montar($geracao->id, ['prova_branca'])->output())->toStartWith('%PDF')
        ->and($service->nomeArquivo($geracao->id))->toBe('simulado-'.$geracao->id.'-'.now()->format('Ymd').'.pdf');
})->skip(fn () => ! class_exists(Pdf::class), 'DomPDF ausente');

it('salvarPdfNativo é no-op fora do runtime nativo', function () {
    $geracao = geracaoSimuladoPdf();

    // config('nativephp-internal.running') é falsy nos testes → retorna sem erro.
    Livewire::test(SimuladoPage::class, ['id' => $geracao->id])
        ->call('salvarPdfNativo', ['prova_branca'])
        ->assertOk();
});
