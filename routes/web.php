<?php

use App\Livewire\Biblioteca;
use App\Livewire\DisciplinaPage;
use App\Livewire\SimuladoPage;
use Illuminate\Support\Facades\Route;

Route::get('/', Biblioteca::class)->name('biblioteca');
Route::get('/disciplina/{slug}', DisciplinaPage::class)->name('disciplina');
Route::get('/simulado/{id}', SimuladoPage::class)->name('simulado');
