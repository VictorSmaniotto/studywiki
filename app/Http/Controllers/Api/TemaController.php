<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tema;
use Illuminate\Http\JsonResponse;

class TemaController extends Controller
{
    public function index(): JsonResponse
    {
        $temas = Tema::with('disciplinas:id,nome,slug')->get(['id', 'nome', 'slug']);

        return response()->json($temas);
    }
}
