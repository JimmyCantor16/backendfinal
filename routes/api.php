<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // SOLO ADMIN
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });

    // Catálogos
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('clients', ClientController::class);

    // Productos
    Route::apiResource('products', ProductController::class);

    // Órdenes de compra
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'store', 'show']);
    Route::patch('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);
    Route::patch('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

    // Facturas
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

    // Movimientos de inventario
    Route::apiResource('inventory-movements', InventoryMovementController::class)->only(['index', 'store']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'summary']);
});
