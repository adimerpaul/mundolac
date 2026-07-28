<?php

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::put('/cambiar-password', [UserController::class, 'changePassword']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{id}/avatar', [UserController::class, 'uploadAvatar']);
    Route::get('/permissions', [UserController::class, 'permissions']);
    Route::get('/users/{id}/permissions', [UserController::class, 'userPermissions']);
    Route::put('/users/{id}/permissions', [UserController::class, 'updateUserPermissions']);

    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos-catalogos', [ProductoController::class, 'catalogos']);
    Route::post('/categorias', [ProductoController::class, 'storeCategoria']);
    Route::put('/categorias/{categoria}', [ProductoController::class, 'updateCategoria']);
    Route::delete('/categorias/{categoria}', [ProductoController::class, 'destroyCategoria']);
    Route::patch('/productos/{producto}/codigo-barras', [ProductoController::class, 'updateBarcode']);
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::put('/productos/{producto}', [ProductoController::class, 'update']);
    Route::post('/productos/{producto}/foto', [ProductoController::class, 'uploadPhoto']);
    Route::post('/productos/{producto}/foto-url', [ProductoController::class, 'uploadPhotoFromUrl']);
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy']);
});
