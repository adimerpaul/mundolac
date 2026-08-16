<?php

use App\Http\Controllers\CompraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserController::class, 'login']);
Route::get('/configuracion', [ConfiguracionController::class, 'show']);

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

    Route::get('/ventas', [VentaController::class, 'index']);
    Route::post('/ventas', [VentaController::class, 'store']);
    Route::get('/ventas-resumen', [VentaController::class, 'summary']);
    Route::get('/dashboard', [VentaController::class, 'dashboard']);
    Route::get('/ventas-exportar/excel', [VentaController::class, 'exportExcel']);
    Route::get('/ventas-exportar/pdf', [VentaController::class, 'exportPdf']);
    Route::get('/ventas/{venta}', [VentaController::class, 'show']);
    Route::put('/ventas/{venta}/anular', [VentaController::class, 'cancel']);
    Route::put('/ventas/{venta}/tipo-pago', [VentaController::class, 'changePayment']);

    Route::get('/compras', [CompraController::class, 'index']);
    Route::get('/compras-resumen', [CompraController::class, 'summary']);
    Route::post('/compras', [CompraController::class, 'store']);
    Route::get('/compras/{compra}', [CompraController::class, 'show']);
    Route::put('/compras/{compra}/anular', [CompraController::class, 'cancel']);
    Route::get('/proveedores', [CompraController::class, 'proveedores']);
    Route::post('/proveedores', [CompraController::class, 'storeProveedor']);
    Route::put('/proveedores/{proveedor}', [CompraController::class, 'updateProveedor']);
    Route::delete('/proveedores/{proveedor}', [CompraController::class, 'destroyProveedor']);
    Route::get('/vencimientos', [CompraController::class, 'vencimientos']);
    Route::put('/configuracion', [ConfiguracionController::class, 'update']);
    Route::post('/configuracion/logo', [ConfiguracionController::class, 'uploadLogo']);
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show']);
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update']);
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy']);
    Route::post('/clientes/{cliente}/foto', [ClienteController::class, 'uploadPhoto']);
    Route::get('/reportes/productos-vendidos', [ReporteController::class, 'productosVendidos']);
    Route::get('/reportes/productos-vendidos/excel', [ReporteController::class, 'productosVendidosExcel']);
    Route::get('/pedidos-clientes', [PedidoController::class, 'clients']);
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::post('/pedidos', [PedidoController::class, 'store']);
    Route::get('/pedidos-exportar/excel', [PedidoController::class, 'exportExcel']);
    Route::get('/pedidos-exportar/pdf', [PedidoController::class, 'exportPdf']);
    Route::get('/pedidos-imprimir', [PedidoController::class, 'printData']);
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
    Route::put('/pedidos/{pedido}/estado', [PedidoController::class, 'status']);
});
