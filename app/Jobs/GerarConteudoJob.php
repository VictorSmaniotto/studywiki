<?php

namespace App\Jobs;

use App\Services\AI\FlashcardsGenerator;
use App\Services\AI\MapaMentalGenerator;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GerarConteudoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $tipo,
        private readonly string $disciplinaSlug,
        private readonly ?string $query = null,
        private readonly int $nQuestoes = 5,
        private readonly int $nDissertativas = 3,
        private readonly string $dificuldade = 'medio',
        private readonly string $perfil = 'personalizado',
        private readonly int $tempoEstimado = 0,
    ) {}

    public function handle(): void
    {
        $escopo = new Escopo(disciplina: $this->disciplinaSlug, query: $this->query);

        match ($this->tipo) {
            'resumo' => app(ResumoGenerator::class)->gerar($escopo),
            'flashcards' => app(FlashcardsGenerator::class)->gerar($escopo),
            'simulado' => app(SimuladoGenerator::class)->gerar(
                $escopo,
                $this->nQuestoes,
                $this->nDissertativas,
                $this->dificuldade,
                $this->perfil,
                $this->tempoEstimado,
            ),
            'mapa_mental' => app(MapaMentalGenerator::class)->gerar($escopo),
            default => throw new \InvalidArgumentException("Tipo desconhecido: {$this->tipo}"),
        };
    }
}
