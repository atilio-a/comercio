<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Catalogo\AlicuotaIvaController;
use App\Http\Controllers\Catalogo\FotoProductoController;
use App\Http\Controllers\Catalogo\MaestroController;
use App\Http\Controllers\Catalogo\ProductoController;
use App\Http\Controllers\Catalogo\VarianteController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Stock\AjusteStockController;
use App\Http\Controllers\Stock\KardexController;
use App\Http\Controllers\Stock\StockController;
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

    Route::prefix('catalogo')->name('catalogo.')->group(function () {
        Route::get('/maestros/{tabla}', [MaestroController::class, 'index'])->name('maestros.index');
        Route::get('/maestros/{tabla}/create', [MaestroController::class, 'create'])->name('maestros.create');
        Route::post('/maestros/{tabla}', [MaestroController::class, 'store'])->name('maestros.store');
        Route::get('/maestros/{tabla}/{id}/edit', [MaestroController::class, 'edit'])->whereNumber('id')->name('maestros.edit');
        Route::put('/maestros/{tabla}/{id}', [MaestroController::class, 'update'])->whereNumber('id')->name('maestros.update');

        Route::resource('productos', ProductoController::class)->except(['destroy']);

        Route::scopeBindings()->group(function () {
            Route::get('/productos/{producto}/variantes/create', [VarianteController::class, 'create'])->name('productos.variantes.create');
            Route::post('/productos/{producto}/variantes', [VarianteController::class, 'store'])->name('productos.variantes.store');
            Route::get('/productos/{producto}/variantes/{variante}/edit', [VarianteController::class, 'edit'])->name('productos.variantes.edit');
            Route::put('/productos/{producto}/variantes/{variante}', [VarianteController::class, 'update'])->name('productos.variantes.update');

            Route::post('/productos/{producto}/fotos', [FotoProductoController::class, 'store'])->name('productos.fotos.store');
            Route::put('/productos/{producto}/fotos/{foto}/principal', [FotoProductoController::class, 'principal'])->name('productos.fotos.principal');
            Route::put('/productos/{producto}/fotos/{foto}/color', [FotoProductoController::class, 'color'])->name('productos.fotos.color');
            Route::delete('/productos/{producto}/fotos/{foto}', [FotoProductoController::class, 'destroy'])->name('productos.fotos.destroy');
        });

        Route::get('/fotos/{foto}/{tamano}', [FotoProductoController::class, 'mostrar'])
            ->whereIn('tamano', ['original', 'miniatura'])
            ->name('fotos.mostrar');

        Route::resource('alicuotas', AlicuotaIvaController::class)
            ->parameters(['alicuotas' => 'alicuota'])
            ->except(['show', 'destroy']);
    });

    Route::prefix('stock')->name('stock.')->group(function () {
        Route::middleware('can:stock.ver')->group(function () {
            Route::get('/', [StockController::class, 'index'])->name('index');
            Route::get('/variantes/{variante}/kardex', [KardexController::class, 'show'])->name('kardex');
        });

        Route::get('/productos/{producto}/ajuste', [AjusteStockController::class, 'edit'])->name('ajuste.edit');
        Route::put('/productos/{producto}/ajuste', [AjusteStockController::class, 'update'])->name('ajuste.update');
    });

    Route::get('/auditoria', [AuditoriaController::class, 'index'])
        ->middleware('can:auditoria.ver')
        ->name('auditoria.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
