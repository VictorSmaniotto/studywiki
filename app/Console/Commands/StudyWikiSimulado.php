<?php

namespace App\Console\Commands;

use App\Models\Disciplina;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StudyWikiSimulado extends Command
{
    protected $signature = 'studywiki:simulado
        {disciplina : Slug ou nome da disciplina}
        {--n=5 : Número de questões}
        {--dif=medio : Dificuldade (facil|medio|dificil)}
        {--gabarito : Mostra gabarito comentado imediatamente}';

    protected $description = 'Gera um simulado ancorado para uma disciplina da vault';

    public function handle(SimuladoGenerator $generator): int
    {
        $input = $this->argument('disciplina');
        $slug = Str::slug($input);

        $disciplina = Disciplina::where('slug', $slug)->first();

        if (! $disciplina) {
            $this->error("Disciplina não encontrada: {$input}");
            $this->line('Disciplinas disponíveis:');
            Disciplina::orderBy('nome')->pluck('nome', 'slug')->each(
                fn ($nome, $s) => $this->line("  {$s} — {$nome}")
            );

            return self::FAILURE;
        }

        $quantidade = max(1, (int) $this->option('n'));
        $dificuldade = $this->option('dif');

        $this->info("Gerando simulado: {$disciplina->nome} · {$quantidade} questões ME · nível {$dificuldade}");
        $this->newLine();

        $escopo = new Escopo(disciplina: $disciplina->slug);
        $geracao = $generator->gerar($escopo, $quantidade, 0, $dificuldade);

        if ($geracao->status === 'rejeitado') {
            $this->error('Simulado rejeitado: não foi possível ancorar as questões nos chunks disponíveis.');
            $this->line("ID da geração: {$geracao->id} (status=rejeitado, verifique os chunks da disciplina)");

            return self::FAILURE;
        }

        $questoes = $geracao->payload['questoes_me'] ?? $geracao->payload['questoes'] ?? [];

        $this->imprimirSimulado($questoes, $disciplina->nome);

        $mostrarGabarito = $this->option('gabarito') || $this->confirm('Mostrar gabarito comentado?', true);

        if ($mostrarGabarito) {
            $this->newLine();
            $this->imprimirGabarito($questoes);
        }

        $this->newLine();
        $this->line("<fg=gray>Geração #{$geracao->id} · {$geracao->custo_tokens} tokens · modelo {$geracao->modelo}</>");

        return self::SUCCESS;
    }

    private function imprimirSimulado(array $questoes, string $nomeDisciplina): void
    {
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line("  SIMULADO — {$nomeDisciplina}");
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        foreach ($questoes as $i => $questao) {
            $num = $i + 1;
            $this->line("<fg=cyan>Questão {$num}</> ({$questao['formato']})");
            $this->newLine();

            if (! empty($questao['contexto'])) {
                $this->line($this->quebrarLinha($questao['contexto'], 70));
                $this->newLine();
            }

            $this->line($this->quebrarLinha($questao['enunciado'], 70));
            $this->newLine();

            foreach (['a', 'b', 'c', 'd', 'e'] as $letra) {
                $texto = $questao['alternativas'][$letra] ?? '';
                $this->line("  {$letra}) {$texto}");
            }

            $this->newLine();
            $this->line(str_repeat('─', 62));
            $this->newLine();
        }
    }

    private function imprimirGabarito(array $questoes): void
    {
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('  GABARITO COMENTADO');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        foreach ($questoes as $i => $questao) {
            $num = $i + 1;
            $correta = strtoupper($questao['correta'] ?? '?');
            $this->line("<fg=cyan>Questão {$num}</>");
            $this->line("Gabarito: <fg=green>{$correta}</>");
            $this->newLine();

            $comentarios = $questao['comentario_gabarito'] ?? [];
            foreach (['a', 'b', 'c', 'd', 'e'] as $letra) {
                $eh_correta = $letra === ($questao['correta'] ?? '');
                $cor = $eh_correta ? 'green' : 'gray';
                $marca = $eh_correta ? '✓' : '✗';
                $comentario = $comentarios[$letra] ?? '';
                $this->line("  <fg={$cor}>{$marca} {$letra}) {$comentario}</>");
            }

            $fontes = $questao['fontes'] ?? [];
            if (! empty($fontes)) {
                $refs = collect($fontes)
                    ->map(fn ($f) => "pág.{$f['pagina_id']}")
                    ->unique()
                    ->implode(', ');
                $this->line("  <fg=gray>Fontes: {$refs}</>");
            }

            $this->newLine();
            $this->line(str_repeat('─', 62));
            $this->newLine();
        }
    }

    private function quebrarLinha(string $texto, int $largura): string
    {
        return wordwrap($texto, $largura, "\n", false);
    }
}
