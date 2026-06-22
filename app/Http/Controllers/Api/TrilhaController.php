<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrilhaService;
use Illuminate\Http\JsonResponse;

class TrilhaController extends Controller
{
    public function __construct(private TrilhaService $trilha) {}

    public function index(): JsonResponse
    {
        $flashcardsVencidos = $this->trilha->flashcardsVencidos();
        $topicosPrioritarios = $this->trilha->topicosPrioritarios();
        $streak = $this->trilha->streakAtual();

        return response()->json([
            'streak' => $streak,
            'flashcards_vencidos' => $flashcardsVencidos->map(fn ($f) => [
                'id' => $f->id,
                'frente' => $f->frente,
                'verso' => $f->verso,
                'proxima_revisao' => $f->proxima_revisao->toDateString(),
            ]),
            'topicos_prioritarios' => $topicosPrioritarios,
        ]);
    }
}
