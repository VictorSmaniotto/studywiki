<?php

use App\Http\Controllers\Api\DisciplinaController;
use App\Http\Controllers\Api\FlashcardController;
use App\Http\Controllers\Api\TemaController;
use App\Http\Controllers\Api\TrilhaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/disciplinas', [DisciplinaController::class, 'index']);
    Route::get('/disciplinas/{slug}', [DisciplinaController::class, 'show']);
    Route::get('/disciplinas/{slug}/geracoes', [DisciplinaController::class, 'geracoes']);
    Route::post('/disciplinas/{slug}/gerar', [DisciplinaController::class, 'gerar']);

    Route::get('/flashcards/vencidos', [FlashcardController::class, 'vencidos']);
    Route::post('/flashcards/{id}/revisar', [FlashcardController::class, 'revisar']);

    Route::get('/trilha', [TrilhaController::class, 'index']);

    Route::get('/temas', [TemaController::class, 'index']);
});
