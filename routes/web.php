<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OitivaController;
use App\Http\Controllers\PublicOitivaController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/* Route::get('/laravel', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
}); */

// Rota de Acesso à Sala (Validada por Assinatura)
Route::get('/sala-gravacao/{oitiva}', [PublicOitivaController::class, 'sala'])
    ->name('public.oitiva.sala')
    ->middleware('signed');

// Rota de Upload dos Chunks (Validada por Assinatura)
// Importante: O frontend deve manter a query string ?signature=... no post
Route::post('/sala-gravacao/{oitiva}/upload', [PublicOitivaController::class, 'upload'])
    ->name('public.oitiva.upload')
    ->middleware('signed');

// Rota para assistir
Route::get('/assistir/{oitiva}', [PublicOitivaController::class, 'assistir'])
    ->name('public.oitiva.assistir')
    ->middleware('signed');

//Rota para servir a transcrição
Route::get('/assistir/{oitiva}/transcricao', [PublicOitivaController::class, 'transcricao'])
    ->name('public.oitiva.transcricao');

// Rotas de Download
Route::post('/assistir/{oitiva}/iniciar-download', [PublicOitivaController::class, 'iniciarDownload'])
    ->name('public.oitiva.iniciar-download');

Route::get('/assistir/{oitiva}/status-download', [PublicOitivaController::class, 'statusDownload'])
    ->name('public.oitiva.status-download');

Route::get('/assistir/{oitiva}/download-zip', [PublicOitivaController::class, 'downloadZip'])
    ->name('public.oitiva.download-zip');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/oitivas/{oitiva}/upload', [OitivaController::class, 'uploadVideoChunk'])->name('oitivas.upload');

    // Rotas padrão (Create, Store, Show, Index)
    Route::resource('oitivas', OitivaController::class);

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
