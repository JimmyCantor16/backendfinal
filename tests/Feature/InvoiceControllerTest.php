<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedUser;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
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

    private function makeClient(): Client
    {
        return Client::factory()->create(['business_id' => $this->business->id]);
    }

    public function test_crear_factura_con_items_descuenta_stock_y_calcula_iva(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(stock: 10, price: 100);

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'items'     => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'notes'     => 'Factura de prueba',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'invoice_number', 'subtotal', 'iva_rate', 'iva', 'total',
                'items' => [['id', 'product_id', 'quantity', 'subtotal']],
            ]);

        $this->assertDatabaseHas('invoices', [
            'business_id' => $this->business->id,
            'client_id'   => $client->id,
            'subtotal'    => 200.00,
            'iva'         => 38.00,
            'total'       => 238.00,
            'status'      => 'completed',
        ]);

        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_crear_factura_falla_si_stock_insuficiente(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(stock: 2);

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'items'     => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseMissing('invoices', [
            'business_id' => $this->business->id,
            'client_id'   => $client->id,
        ]);
    }

    public function test_crear_factura_falla_validacion_sin_items(): void
    {
        $client = $this->makeClient();

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'items'     => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_listar_facturas(): void
    {
        $client = $this->makeClient();

        Invoice::create([
            'business_id'    => $this->business->id,
            'client_id'      => $client->id,
            'user_id'        => $this->user->id,
            'invoice_number' => 'FAC-0001',
            'subtotal'       => 100,
            'iva_rate'       => 19,
            'iva'            => 19,
            'total'          => 119,
            'payment_method' => 'cash',
            'status'         => 'completed',
        ]);

        Invoice::create([
            'business_id'    => $this->business->id,
            'client_id'      => $client->id,
            'user_id'        => $this->user->id,
            'invoice_number' => 'FAC-0002',
            'subtotal'       => 200,
            'iva_rate'       => 19,
            'iva'            => 38,
            'total'          => 238,
            'payment_method' => 'cash',
            'status'         => 'completed',
        ]);

        $response = $this->getJson('/api/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'invoice_number', 'total']],
                'current_page',
                'per_page',
                'total',
            ])
            ->assertJsonPath('total', 2);
    }

    public function test_mostrar_factura_por_id(): void
    {
        $client = $this->makeClient();

        $invoice = Invoice::create([
            'business_id'    => $this->business->id,
            'client_id'      => $client->id,
            'user_id'        => $this->user->id,
            'invoice_number' => 'FAC-9999',
            'subtotal'       => 500,
            'iva_rate'       => 19,
            'iva'            => 95,
            'total'          => 595,
            'payment_method' => 'cash',
            'status'         => 'completed',
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id'             => $invoice->id,
                'invoice_number' => 'FAC-9999',
            ])
            ->assertJsonStructure(['client', 'user', 'items']);
    }

    public function test_cancelar_factura_restaura_stock(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(stock: 10, price: 50);

        $createResponse = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'items'     => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ]);
        $createResponse->assertStatus(201);

        $invoiceId = $createResponse->json('id');
        $this->assertSame(6, $product->fresh()->stock);

        $response = $this->patchJson("/api/invoices/{$invoiceId}/cancel");

        $response->assertStatus(200)
            ->assertJson(['status' => 'cancelled']);

        $this->assertDatabaseHas('invoices', [
            'id'     => $invoiceId,
            'status' => 'cancelled',
        ]);

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_cancelar_factura_ya_cancelada_falla(): void
    {
        $client = $this->makeClient();

        $invoice = Invoice::create([
            'business_id'    => $this->business->id,
            'client_id'      => $client->id,
            'user_id'        => $this->user->id,
            'invoice_number' => 'FAC-0001',
            'subtotal'       => 100,
            'iva_rate'       => 19,
            'iva'            => 19,
            'total'          => 119,
            'payment_method' => 'cash',
            'status'         => 'cancelled',
        ]);

        $response = $this->patchJson("/api/invoices/{$invoice->id}/cancel");

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }
}
