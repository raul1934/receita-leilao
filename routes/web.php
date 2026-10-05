<?php

use App\Http\Controllers\EditalController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MudancaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EditalController::class, 'index'])->name('editais.index');
Route::post('/editais', [EditalController::class, 'store'])->name('editais.store');
Route::get('/editais/{edital}', [EditalController::class, 'show'])->name('editais.show');
Route::get('/editais/{edital}/lotes/{lote:numero}', [LoteController::class, 'show'])->name('lotes.show');
Route::get('/mudancas', [MudancaController::class, 'index'])->name('mudancas.index');
Route::get('/favoritos', [FavoritoController::class, 'index'])->name('favoritos.index');
Route::post('/editais/{edital}/lotes/{lote:numero}/favorito', [FavoritoController::class, 'alternar'])->name('favoritos.alternar');
