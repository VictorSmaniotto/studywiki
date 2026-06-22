<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Services\SpacedRepetitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    public function __construct(private SpacedRepetitionService $srs) {}

    public function vencidos(): JsonResponse
    {
        // T8.1: adicionar whereHas('geracao', fn($q) => $q->where('user_id', auth()->id())) quando multi-tenant
        $flashcards = Flashcard::where('proxima_revisao', '<=', now()->toDateString())
            ->with('geracao:id,escopo')
            ->get(['id', 'geracao_id', 'frente', 'verso', 'fontes', 'proxima_revisao', 'intervalo', 'facilidade', 'repeticoes']);

        return response()->json($flashcards);
    }

    public function revisar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'lembrei' => ['required', 'boolean'],
        ]);

        // T8.1: trocar por auth()->user()->flashcards()->findOrFail($id) quando multi-tenant
        $flashcard = Flashcard::findOrFail($id);

        $this->srs->revisar($flashcard, $validated['lembrei']);

        $flashcard->refresh();

        return response()->json([
            'id' => $flashcard->id,
            'proxima_revisao' => $flashcard->proxima_revisao->toDateString(),
            'intervalo' => $flashcard->intervalo,
            'facilidade' => $flashcard->facilidade,
            'repeticoes' => $flashcard->repeticoes,
        ]);
    }
}
