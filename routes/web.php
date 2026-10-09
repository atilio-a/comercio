<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SucursalActivaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/sucursal', [SucursalActivaController::class, 'edit'])->name('sucursal-activa.edit');
    Route::put('/sucursal', [SucursalActivaController::class, 'update'])->name('sucursal-activa.update');

    Route::middleware('can:parametros.editar')->group(function () {
        Route::get('/parametros', [ParametroController::class, 'edit'])->name('parametros.edit');
        Route::put('/parametros', [ParametroController::class, 'update'])->name('parametros.update');
    });

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['show', 'destroy']);

    Route::get('/auditoria', [AuditoriaController::class, 'index'])
        ->middleware('can:auditoria.ver')
        ->name('auditoria.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
