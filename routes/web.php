<?php

use App\Http\Controllers\SimuladoPdfController;
use App\Livewire\Biblioteca;
use App\Livewire\Chat;
use App\Livewire\DisciplinaPage;
use App\Livewire\Metas;
use App\Livewire\SimuladoPage;
use App\Livewire\Trilha;
use Illuminate\Support\Facades\Route;

Route::get('/', Biblioteca::class)->name('biblioteca');
Route::get('/trilha', Trilha::class)->name('trilha');
Route::get('/disciplina/{slug}', DisciplinaPage::class)->name('disciplina');
Route::get('/simulado/{id}', SimuladoPage::class)->name('simulado');
Route::get('/simulado/{id}/pdf', SimuladoPdfController::class)->name('simulado.pdf');
Route::get('/metas', Metas::class)->name('metas');
Route::get('/chat', Chat::class)->name('chat');
