<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetaService
{
    public function progressoSemana(): array
    {
        $inicio = Carbon::now()->startOfWeek()->toDateTimeString();
        $fim = Carbon::now()->endOfWeek()->toDateTimeString();

        return [
            'simulados' => [
                'meta' => (int) Setting::get('meta_simulados', '0'),
                'atual' => $this->simuladosConcluidos($inicio, $fim),
            ],
            'flashcards' => [
                'meta' => (int) Setting::get('meta_flashcards', '0'),
                'atual' => $this->flashcardsRevisados($inicio, $fim),
            ],
            'geracoes' => [
                'meta' => (int) Setting::get('meta_geracoes', '0'),
                'atual' => $this->geracoesCriadas($inicio, $fim),
            ],
        ];
    }

    public function salvarMetas(int $simulados, int $flashcards, int $geracoes): void
    {
        Setting::set('meta_simulados', (string) max(0, $simulados));
        Setting::set('meta_flashcards', (string) max(0, $flashcards));
        Setting::set('meta_geracoes', (string) max(0, $geracoes));
    }

    private function simuladosConcluidos(string $inicio, string $fim): int
    {
        return DB::table('resposta_simulados')
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();
    }

    private function flashcardsRevisados(string $inicio, string $fim): int
    {
        return DB::table('flashcards')
            ->whereBetween('updated_at', [$inicio, $fim])
            ->whereColumn('updated_at', '>', 'created_at')
            ->count();
    }

    private function geracoesCriadas(string $inicio, string $fim): int
    {
        return DB::table('geracoes')
            ->where('status', 'ok')
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();
    }
}
