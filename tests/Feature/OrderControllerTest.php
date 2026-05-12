<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedUser;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase, CreatesAuthenticatedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsAdmin();
    }

    private function makeProduct(int $stock = 10, float $price = 100.0): Product
    {
        $category = Category::factory()->create(['business_id' => $this->business->id]);

        return Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'stock'       => $stock,
            'sale_price'  => $price,
        ]);
    }

    public function test_crear_orden_requiere_caja_abierta(): void
    {
        // Sin caja abierta -> falla
        $response = $this->postJson('/api/orders');

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_crear_orden_ok_con_caja_abierta(): void
    {
        $this->openCashRegisterFor($this->user);

        $response = $this->postJson('/api/orders');

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'order_number', 'status', 'total', 'cash_register_id']);

        $this->assertDatabaseHas('orders', [
            'business_id' => $this->business->id,
            'user_id'     => $this->user->id,
            'status'      => 'open',
        ]);
    }

    public function test_agregar_item_a_orden_descuenta_stock(): void
    {
        $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 10, price: 200);

        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $this->user->fresh()->id,
            'opened_at'        => now(),
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'product_id', 'quantity', 'unit_price', 'subtotal']);

        $this->assertDatabaseHas('order_items', [
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 3,
            'subtotal'   => 600.00,
        ]);

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_agregar_item_falla_si_stock_insuficiente(): void
    {
        $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 2);

        $order = Order::create([
            'business_id'  => $this->business->id,
            'user_id'      => $this->user->id,
            'order_number' => 'ORD-0001',
            'status'       => 'open',
            'total'        => 0,
            'opened_at'    => now(),
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_remover_item_restaura_stock(): void
    {
        $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 10, price: 100);

        $order = Order::create([
            'business_id'  => $this->business->id,
            'user_id'      => $this->user->id,
            'order_number' => 'ORD-0001',
            'status'       => 'open',
            'total'        => 0,
            'opened_at'    => now(),
        ]);

        $addResponse = $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 4,
        ]);
        $itemId = $addResponse->json('id');

        $this->assertSame(6, $product->fresh()->stock);

        $response = $this->deleteJson("/api/orders/{$order->id}/remove-item/{$itemId}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Item eliminado y stock restaurado.']);

        $this->assertDatabaseMissing('order_items', ['id' => $itemId]);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_cerrar_orden_con_pago_efectivo(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 10, price: 100);

        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);

        $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 2,
        ])->assertStatus(201);

        $response = $this->postJson("/api/orders/{$order->id}/close", [
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'         => 'closed',
                'payment_method' => 'cash',
            ]);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'status'         => 'closed',
            'payment_method' => 'cash',
        ]);

        $cashRegister->refresh();
        $this->assertEquals(200.00, (float) $cashRegister->total_cash);
        $this->assertEquals(200.00, (float) $cashRegister->total_sales);
    }

    public function test_cerrar_orden_con_pago_tarjeta_actualiza_total_card(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 10, price: 150);

        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);

        $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 2,
        ])->assertStatus(201);

        $this->postJson("/api/orders/{$order->id}/close", [
            'payment_method' => 'card',
        ])->assertStatus(200);

        $cashRegister->refresh();
        $this->assertEquals(300.00, (float) $cashRegister->total_card);
        $this->assertEquals(0.00, (float) $cashRegister->total_cash);
        $this->assertEquals(300.00, (float) $cashRegister->total_sales);
    }

    public function test_cerrar_orden_sin_items_falla(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);
        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/close", [
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_cerrar_orden_payment_method_invalido(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);
        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/close", [
            'payment_method' => 'bitcoin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_cancelar_orden_restaura_stock(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);
        $product = $this->makeProduct(stock: 10, price: 50);

        $order = Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);

        $this->postJson("/api/orders/{$order->id}/add-item", [
            'product_id' => $product->id,
            'quantity'   => 3,
        ])->assertStatus(201);

        $this->assertSame(7, $product->fresh()->stock);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['status' => 'cancelled']);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_listar_ordenes_abiertas(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user);

        // Crear 2 órdenes abiertas y 1 cerrada
        Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0001',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);
        Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0002',
            'status'           => 'open',
            'total'            => 0,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
        ]);
        Order::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'order_number'     => 'ORD-0003',
            'status'           => 'closed',
            'total'            => 100,
            'cash_register_id' => $cashRegister->id,
            'opened_at'        => now(),
            'closed_at'        => now(),
        ]);

        $response = $this->getJson('/api/orders/open');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_orders_endpoints_requieren_autenticacion(): void
    {
        // Drop sanctum auth
        app('auth')->forgetGuards();
        $this->refreshApplication();

        $response = $this->getJson('/api/orders/open');
        $response->assertStatus(401);
    }
}
