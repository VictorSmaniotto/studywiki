<?php

use App\Models\Disciplina;
use App\Models\Pagina;
use App\Models\Tag;
use App\Services\Sync\VaultSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->vaultDir = sys_get_temp_dir().'/studywiki_test_'.uniqid();
    mkdir($this->vaultDir, 0755, true);
    config(['studywiki.vault_path' => $this->vaultDir]);
});

afterEach(function () {
    File::deleteDirectory($this->vaultDir);
});

// ─── helpers ───────────────────────────────────────────────────────────────

function md_file(string $titulo, string $corpo = 'corpo', array $extra = []): string
{
    $fm = array_merge(['titulo' => $titulo, 'tipo' => 'conceito'], $extra);

    $yaml = '';
    foreach ($fm as $k => $v) {
        if (is_array($v)) {
            $yaml .= "$k:\n".implode('', array_map(fn ($i) => "  - $i\n", $v));
        } else {
            $yaml .= "$k: $v\n";
        }
    }

    return "---\n{$yaml}---\n\n{$corpo}";
}

function escrever(string $dir, string $nome, string $conteudo): void
{
    file_put_contents($dir.'/'.$nome, $conteudo);
}

// ─── testes ────────────────────────────────────────────────────────────────

it('cria paginas na primeira sincronizacao', function () {
    escrever($this->vaultDir, 'a.md', md_file('A'));
    escrever($this->vaultDir, 'b.md', md_file('B'));

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::count())->toBe(2);
});

it('rodar sync 2x nao duplica paginas', function () {
    escrever($this->vaultDir, 'a.md', md_file('Conceito A'));

    $this->artisan('studywiki:sync')->assertSuccessful();
    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::count())->toBe(1);
});

it('alterar 1 arquivo re-processa so ele e ignora os demais', function () {
    escrever($this->vaultDir, 'arquivo-1.md', md_file('A1', 'original'));
    escrever($this->vaultDir, 'arquivo-2.md', md_file('A2', 'inalterado'));

    $syncService = app(VaultSyncService::class);
    $syncService->sync($this->vaultDir);

    escrever($this->vaultDir, 'arquivo-1.md', md_file('A1', 'modificado'));

    $stats = $syncService->sync($this->vaultDir);

    expect($stats['atualizadas'])->toBe(1)
        ->and($stats['ignoradas'])->toBe(1)
        ->and(Pagina::where('path_relativo', 'arquivo-1.md')->value('corpo'))->toBe('modificado')
        ->and(Pagina::where('path_relativo', 'arquivo-2.md')->value('corpo'))->toBe('inalterado');
});

it('arquivo removido da vault recebe soft delete', function () {
    escrever($this->vaultDir, 'fica.md', md_file('Fica'));
    escrever($this->vaultDir, 'sai.md', md_file('Sai'));

    $this->artisan('studywiki:sync')->assertSuccessful();
    expect(Pagina::count())->toBe(2);

    unlink($this->vaultDir.'/sai.md');

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::count())->toBe(1)
        ->and(Pagina::withTrashed()->count())->toBe(2);
});

it('nunca escreve na vault', function () {
    $arquivo = $this->vaultDir.'/readonly.md';
    escrever($this->vaultDir, 'readonly.md', md_file('ReadOnly', 'corpo imutável'));

    $hashAntes = md5_file($arquivo);

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(md5_file($arquivo))->toBe($hashAntes);
});

it('extrai disciplina do frontmatter e cria se nao existir', function () {
    escrever($this->vaultDir, 'p.md', md_file('P', 'corpo', ['disciplina' => 'Engenharia de Software']));

    $this->artisan('studywiki:sync')->assertSuccessful();

    $disciplina = Disciplina::first();
    expect($disciplina->nome)->toBe('Engenharia de Software');

    $pagina = Pagina::first();
    expect($pagina->disciplina_id)->toBe($disciplina->id);
});

it('mesma disciplina nao e duplicada em sync repetido', function () {
    escrever($this->vaultDir, 'p1.md', md_file('P1', 'corpo', ['disciplina' => 'Redes']));
    escrever($this->vaultDir, 'p2.md', md_file('P2', 'corpo', ['disciplina' => 'Redes']));

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Disciplina::count())->toBe(1);
});

it('sincroniza tags do frontmatter na pivot', function () {
    escrever($this->vaultDir, 'p.md', md_file('P', 'corpo', ['tags' => ['oop', 'solid']]));

    $this->artisan('studywiki:sync')->assertSuccessful();

    $pagina = Pagina::with('tags')->first();
    $nomes = $pagina->tags->pluck('nome')->sort()->values()->all();

    expect($nomes)->toBe(['oop', 'solid']);
});

it('arquivo em subdiretorio tem path_relativo correto', function () {
    mkdir($this->vaultDir.'/sub', 0755, true);
    escrever($this->vaultDir, 'sub/conceito.md', md_file('Conceito'));

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::value('path_relativo'))->toBe('sub/conceito.md');
});

it('tipo invalido no frontmatter cai para conceito', function () {
    escrever($this->vaultDir, 'p.md', md_file('P', 'corpo', ['tipo' => 'inexistente']));

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::value('tipo'))->toBe('conceito');
});

it('tags com mesmo slug nao geram violacao de unique constraint', function () {
    // 'pseudocódigo' e 'pseudocodigo' geram o mesmo slug
    escrever($this->vaultDir, 'p1.md', md_file('P1', 'corpo', ['tags' => ['pseudocódigo']]));
    escrever($this->vaultDir, 'p2.md', md_file('P2', 'corpo', ['tags' => ['pseudocodigo']]));

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::count())->toBe(2)
        ->and(Tag::count())->toBe(1);
});

it('arquivo com frontmatter yaml invalido e importado sem travar', function () {
    $conteudo = "---\nO usuário traz as fontes e faz as perguntas. Você escreve e mantém toda a wiki.\n---\n\nCorpo normal aqui.";
    escrever($this->vaultDir, 'malformed.md', $conteudo);

    $this->artisan('studywiki:sync')->assertSuccessful();

    expect(Pagina::count())->toBe(1)
        ->and(Pagina::value('titulo'))->toBe('malformed')
        ->and(Pagina::value('tipo'))->toBe('conceito');
});
