<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedUser;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, CreatesAuthenticatedUser;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsAdmin();
        $this->category = Category::factory()->create(['business_id' => $this->business->id]);
    }

    public function test_listar_productos(): void
    {
        Product::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'  => [['id', 'name', 'sku', 'stock', 'sale_price']],
                'total',
                'current_page',
            ])
            ->assertJsonPath('total', 3);
    }

    public function test_crear_producto_ok(): void
    {
        $payload = [
            'category_id'    => $this->category->id,
            'name'           => 'Coca-Cola 500ml',
            'sku'            => 'CC-500',
            'purchase_price' => 1500,
            'sale_price'     => 2500,
            'stock'          => 50,
            'min_stock'      => 10,
            'is_active'      => true,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'sku', 'sale_price', 'stock', 'category'])
            ->assertJson([
                'name' => 'Coca-Cola 500ml',
                'sku'  => 'CC-500',
            ]);

        $this->assertDatabaseHas('products', [
            'business_id' => $this->business->id,
            'sku'         => 'CC-500',
            'name'        => 'Coca-Cola 500ml',
            'stock'       => 50,
        ]);
    }

    public function test_crear_producto_falla_con_sku_duplicado(): void
    {
        Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'sku'         => 'DUP-001',
        ]);

        $response = $this->postJson('/api/products', [
            'category_id'    => $this->category->id,
            'name'           => 'Otro producto',
            'sku'            => 'DUP-001',
            'purchase_price' => 100,
            'sale_price'     => 200,
            'stock'          => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_crear_producto_falla_validacion(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Sin SKU ni precios',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'sku', 'purchase_price', 'sale_price']);
    }

    public function test_mostrar_producto(): void
    {
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id'   => $product->id,
                'name' => $product->name,
                'sku'  => $product->sku,
            ])
            ->assertJsonStructure(['category']);
    }

    public function test_actualizar_producto_ok(): void
    {
        $product = Product::factory()->create([
            'business_id'    => $this->business->id,
            'category_id'    => $this->category->id,
            'name'           => 'Original',
            'sku'            => 'OLD-001',
            'purchase_price' => 100,
            'sale_price'     => 200,
            'stock'          => 20,
        ]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'category_id'    => $this->category->id,
            'name'           => 'Actualizado',
            'sku'            => 'OLD-001',
            'purchase_price' => 150,
            'sale_price'     => 300,
            'min_stock'      => 5,
            'is_active'      => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id'   => $product->id,
                'name' => 'Actualizado',
            ]);

        $this->assertDatabaseHas('products', [
            'id'         => $product->id,
            'name'       => 'Actualizado',
            'sale_price' => 300,
        ]);
    }

    public function test_actualizar_producto_no_modifica_stock_directamente(): void
    {
        // El endpoint update no acepta 'stock' (debe ir vía inventory-movements)
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'stock'       => 20,
        ]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'category_id'    => $this->category->id,
            'name'           => $product->name,
            'sku'            => $product->sku,
            'purchase_price' => $product->purchase_price,
            'sale_price'     => $product->sale_price,
            'stock'          => 9999, // se ignora
            'min_stock'      => 5,
            'is_active'      => true,
        ]);

        $response->assertStatus(200);
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_actualizar_producto_sku_duplicado_falla(): void
    {
        $existing = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'sku'         => 'TAKEN-001',
        ]);

        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'sku'         => 'MINE-001',
        ]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'category_id'    => $this->category->id,
            'name'           => $product->name,
            'sku'            => 'TAKEN-001',
            'purchase_price' => 100,
            'sale_price'     => 200,
            'min_stock'      => 0,
            'is_active'      => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_eliminar_producto_sin_stock(): void
    {
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'stock'       => 0,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Producto eliminado correctamente.']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_eliminar_producto_con_stock_falla(): void
    {
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'stock'       => 5,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
