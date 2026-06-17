<?php

use App\Models\Chunk;
use App\Models\Pagina;
use App\Services\Sync\ChunkingService;
use App\Services\Sync\VaultSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

// ─── helpers locais ────────────────────────────────────────────────────────

function md_com_headings(string $titulo, string $corpo): string
{
    return "---\ntitulo: {$titulo}\ntipo: conceito\n---\n\n{$corpo}";
}

function escrever_chunk(string $dir, string $nome, string $conteudo): void
{
    file_put_contents($dir.'/'.$nome, $conteudo);
}

// ─── setup ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->vaultDir = sys_get_temp_dir().'/studywiki_chunk_'.uniqid();
    mkdir($this->vaultDir, 0755, true);
    config(['studywiki.vault_path' => $this->vaultDir]);
    $this->sync = app(VaultSyncService::class);
});

afterEach(function () {
    File::deleteDirectory($this->vaultDir);
});

// ─── AC principal ──────────────────────────────────────────────────────────

it('pagina com 3 headings vira ao menos 3 chunks', function () {
    $corpo = <<<'MD'
        # Compiladores
        Introdução ao tema de compiladores.

        ## Análise Léxica
        A análise léxica transforma o código fonte em tokens.

        ## Análise Sintática
        A análise sintática verifica a estrutura gramatical do programa.
        MD;

    escrever_chunk($this->vaultDir, 'comp.md', md_com_headings('Compiladores', dedent($corpo)));
    $this->sync->sync($this->vaultDir);

    expect(Chunk::count())->toBeGreaterThanOrEqual(3);
});

it('heading_path reflete hierarquia h1 h2 h3', function () {
    $corpo = "# Compiladores\nIntrodução.\n\n## Análise Léxica\nDetalhes.\n\n### Tokens\nO que são tokens.";

    escrever_chunk($this->vaultDir, 'comp.md', md_com_headings('Compiladores', $corpo));
    $this->sync->sync($this->vaultDir);

    $paths = Chunk::orderBy('ordem')->pluck('heading_path')->all();

    expect($paths)->toContain('Compiladores')
        ->toContain('Compiladores > Análise Léxica')
        ->toContain('Compiladores > Análise Léxica > Tokens');
});

it('h2 resets h3 ao mudar de secao', function () {
    $corpo = "# Raiz\nTexto raiz.\n\n## Secao A\nTexto A.\n\n### Sub A\nTexto sub A.\n\n## Secao B\nTexto B.";

    escrever_chunk($this->vaultDir, 'p.md', md_com_headings('Raiz', $corpo));
    $this->sync->sync($this->vaultDir);

    $paths = Chunk::orderBy('ordem')->pluck('heading_path')->all();

    expect($paths)->toContain('Raiz > Secao B')
        ->not->toContain('Raiz > Secao B > Sub A');
});

it('corpo sem headings vira um chunk com heading_path nulo', function () {
    $corpo = 'Este é um parágrafo simples sem nenhum heading.';

    escrever_chunk($this->vaultDir, 'simples.md', md_com_headings('Simples', $corpo));
    $this->sync->sync($this->vaultDir);

    expect(Chunk::count())->toBe(1)
        ->and(Chunk::first()->heading_path)->toBeNull();
});

it('chunks sao re-gerados quando pagina e atualizada', function () {
    $v1 = "# Seção 1\nConteúdo inicial.";
    $v2 = "# Seção 1\nConteúdo inicial.\n\n## Seção 1.1\nNovo conteúdo.\n\n## Seção 1.2\nMais conteúdo.";

    escrever_chunk($this->vaultDir, 'p.md', md_com_headings('P', $v1));
    $this->sync->sync($this->vaultDir);
    $chunksV1 = Chunk::count();

    escrever_chunk($this->vaultDir, 'p.md', md_com_headings('P', $v2));
    $this->sync->sync($this->vaultDir);
    $chunksV2 = Chunk::count();

    expect($chunksV1)->toBe(1)
        ->and($chunksV2)->toBeGreaterThan($chunksV1);
});

it('sync idempotente nao duplica chunks de pagina inalterada', function () {
    $corpo = "# A\nTexto.\n\n## B\nTexto B.";

    escrever_chunk($this->vaultDir, 'p.md', md_com_headings('P', $corpo));
    $this->sync->sync($this->vaultDir);
    $primeira = Chunk::count();

    $this->sync->sync($this->vaultDir);
    $segunda = Chunk::count();

    expect($segunda)->toBe($primeira);
});

it('embedding permanece nulo nos chunks apos sync', function () {
    $corpo = "# A\nTexto.\n\n## B\nTexto B.";

    escrever_chunk($this->vaultDir, 'p.md', md_com_headings('P', $corpo));
    $this->sync->sync($this->vaultDir);

    expect(Chunk::whereNotNull('embedding')->count())->toBe(0);
});

it('pagina sem corpo nao gera chunks', function () {
    escrever_chunk($this->vaultDir, 'vazia.md', "---\ntitulo: Vazia\ntipo: conceito\n---\n\n");
    $this->sync->sync($this->vaultDir);

    expect(Pagina::count())->toBe(1)
        ->and(Chunk::count())->toBe(0);
});

// ─── ChunkingService unit ───────────────────────────────────────────────────

it('secao com conteudo longo e dividida em multiplos chunks', function () {
    $service = new ChunkingService;

    // ~2500 chars ≈ 625 tokens — deve gerar pelo menos 2 chunks
    $paragrafo = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 45);
    $corpo = "# Seção Longa\n\n{$paragrafo}";

    $chunks = $service->chunk($corpo);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect($chunk['heading_path'])->toBe('Seção Longa')
            ->and($chunk['tokens'])->toBeGreaterThan(0);
    }
});

it('chunks de secao longa carregam overlap do chunk anterior', function () {
    $service = new ChunkingService;

    $paragrafo = str_repeat('palavra-unica-longa ', 130); // ~130 words × 20 chars = 2600 chars ≈ 650 tokens
    $chunks = $service->chunk("# H\n\n{$paragrafo}");

    expect(count($chunks))->toBeGreaterThan(1);

    // A última palavra do primeiro chunk deve aparecer no início do segundo
    $wordsFirst = explode(' ', trim($chunks[0]['conteudo']));
    $lastWordFirst = end($wordsFirst);

    expect($chunks[1]['conteudo'])->toContain($lastWordFirst);
});

// ─── helper ────────────────────────────────────────────────────────────────

function dedent(string $text): string
{
    $lines = explode("\n", $text);
    $indent = PHP_INT_MAX;
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $indent = min($indent, strlen($line) - strlen(ltrim($line)));
    }

    return implode("\n", array_map(
        fn (string $l) => substr($l, min($indent, strlen($l))),
        $lines
    ));
}
