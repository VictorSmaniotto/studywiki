<?php

namespace App\Http\Controllers;

use App\Services\SimuladoPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimuladoPdfController extends Controller
{
    public function __invoke(Request $request, int $id, SimuladoPdfService $service): Response
    {
        $secoes = $service->normalizarSecoes((array) $request->input('secoes', ['prova_branca']));

        return $service->montar($id, $secoes)->download($service->nomeArquivo($id));
    }
}
