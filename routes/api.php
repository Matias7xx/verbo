<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OitivaApiController;

// O sistema externo deve enviar um Token Sanctum no Header Authorization
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/criar-sala', [OitivaApiController::class, 'store']);
    Route::post('/renovar-link/{uuid}', [OitivaApiController::class, 'refreshLink']);
    Route::post('/gerar-link-assistir/{uuid}', [OitivaApiController::class, 'generateWatchLink']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
