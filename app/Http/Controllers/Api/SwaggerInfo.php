<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Jamz API",
 *     description="Documentación OpenAPI para el backend Jamz (POS, facturación, inventario y administración multi-negocio).",
 *     @OA\Contact(email="support@jamz.local")
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Jamz API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum bearer token."
 * )
 *
 * @OA\Tag(name="Auth", description="Autenticación y sesión de usuario (login, logout, me, verifyPassword).")
 * @OA\Tag(name="Products", description="Gestión de productos (catálogo, stock, precios).")
 * @OA\Tag(name="Orders", description="POS — Órdenes de venta (abrir, ítems, cerrar, cancelar).")
 * @OA\Tag(name="Invoices", description="Facturación de ventas.")
 * @OA\Tag(name="CashRegister", description="Apertura, cierre y reporte de caja registradora.")
 * @OA\Tag(name="Business", description="Negocios del usuario (multi-tenant): listar, crear, actualizar y cambiar negocio activo.")
 * @OA\Tag(name="Dashboard", description="Resumen ejecutivo de ventas, pedidos y stock.")
 * @OA\Tag(name="Categories", description="Categorías de productos. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Suppliers", description="Proveedores. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Clients", description="Clientes. (Anotaciones completas pendientes).")
 * @OA\Tag(name="PurchaseOrders", description="Órdenes de compra. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Inventory", description="Movimientos de inventario. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Reports", description="Reportes operativos. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Audit", description="Bitácora de auditoría. (Anotaciones completas pendientes).")
 * @OA\Tag(name="Users", description="Gestión de usuarios. (Anotaciones completas pendientes).")
 * @OA\Tag(name="BusinessSettings", description="Configuración por negocio. (Anotaciones completas pendientes).")
 */
class SwaggerInfo
{
    // Archivo solo para anotaciones globales OpenAPI. No contiene lógica.
}
