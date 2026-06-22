<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Services\AI\FlashcardsGenerator;
use App\Services\AI\MapaMentalGenerator;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinaController extends Controller
{
    public function index(): JsonResponse
    {
        $disciplinas = Disciplina::orderBy('nome')->get(['id', 'nome', 'slug']);

        return response()->json($disciplinas);
    }

    public function show(string $slug): JsonResponse
    {
        $disciplina = Disciplina::where('slug', $slug)->firstOrFail();

        return response()->json($disciplina->only(['id', 'nome', 'slug']));
    }

    public function geracoes(string $slug): JsonResponse
    {
        Disciplina::where('slug', $slug)->firstOrFail();

        // T8.1: adicionar ->where('user_id', auth()->id()) quando multi-tenant
        $geracoes = Geracao::whereRaw("escopo->>'disciplina' = ?", [$slug])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($geracoes);
    }

    public function gerar(Request $request, string $slug): JsonResponse
    {
        $disciplina = Disciplina::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'tipo' => ['required', 'in:resumo,flashcards,simulado,mapa_mental'],
            'query' => ['nullable', 'string', 'max:500'],
            'quantidade' => ['nullable', 'integer', 'min:1', 'max:20'],
            'dificuldade' => ['nullable', 'in:facil,medio,dificil'],
            'n_me' => ['nullable', 'integer', 'min:0', 'max:20'],
            'n_dis' => ['nullable', 'integer', 'min:0', 'max:10'],
            'perfil' => ['nullable', 'in:personalizado,universitario,vestibular'],
        ]);

        $escopo = new Escopo(
            disciplina: $disciplina->slug,
            query: $validated['query'] ?? null,
        );

        $geracao = match ($validated['tipo']) {
            'resumo' => app(ResumoGenerator::class)->gerar($escopo),
            'flashcards' => app(FlashcardsGenerator::class)->gerar($escopo, $validated['quantidade'] ?? 10),
            'simulado' => app(SimuladoGenerator::class)->gerar(
                $escopo,
                n_me: $validated['n_me'] ?? 5,
                n_dis: $validated['n_dis'] ?? 0,
                dificuldade: $validated['dificuldade'] ?? 'medio',
                perfil: $validated['perfil'] ?? 'personalizado',
            ),
            'mapa_mental' => app(MapaMentalGenerator::class)->gerar($escopo),
        };

        return response()->json([
            'id' => $geracao->id,
            'tipo' => $geracao->tipo,
            'status' => $geracao->status,
            'payload' => $geracao->payload,
            'custo_tokens' => $geracao->custo_tokens,
            'regeneracoes' => $geracao->regeneracoes,
            'created_at' => $geracao->created_at,
        ], 201);
    }
}
