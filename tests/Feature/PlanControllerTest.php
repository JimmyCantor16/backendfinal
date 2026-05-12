<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Las rutas /subscriptions y /plans viven en routes/subscription.php,
        // que será incluido al final de routes/api.php por el operador. Para
        // el test cargamos el archivo dentro del grupo 'api' con prefijo /api,
        // reproduciendo lo que hará el `require` en producción.
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/subscription.php'));
    }

    /**
     * Crea planes de prueba directamente (sin pasar por el seeder, para
     * mantener el test focal y rápido).
     */
    protected function seedPlans(): array
    {
        return [
            Plan::create([
                'name' => 'Free', 'slug' => 'free', 'price_cents' => 0,
                'currency' => 'usd', 'interval' => 'month',
                'stripe_price_id' => null, 'features' => ['max_users' => 2],
                'active' => true,
            ]),
            Plan::create([
                'name' => 'Pro', 'slug' => 'pro', 'price_cents' => 2900,
                'currency' => 'usd', 'interval' => 'month',
                'stripe_price_id' => null, 'features' => ['max_users' => 20],
                'active' => true,
            ]),
            Plan::create([
                'name' => 'Hidden', 'slug' => 'hidden', 'price_cents' => 100,
                'currency' => 'usd', 'interval' => 'month',
                'stripe_price_id' => null, 'features' => null,
                'active' => false, // no debe aparecer en index
            ]),
        ];
    }

    public function test_index_lista_solo_planes_activos_ordenados_por_precio(): void
    {
        $this->seedPlans();

        $response = $this->getJson('/api/plans');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data);

        // Orden por price_cents asc -> Free primero, Pro después
        $this->assertSame('free', $data[0]['slug']);
        $this->assertSame('pro',  $data[1]['slug']);

        // El plan inactivo no aparece
        $slugs = collect($data)->pluck('slug')->all();
        $this->assertNotContains('hidden', $slugs);
    }

    public function test_index_es_publico_sin_auth(): void
    {
        $this->seedPlans();

        // Sin Sanctum::actingAs -> sigue funcionando
        $response = $this->getJson('/api/plans');
        $response->assertStatus(200);
    }

    public function test_show_devuelve_plan_por_id(): void
    {
        [$free, $pro] = $this->seedPlans();

        $response = $this->getJson("/api/plans/{$pro->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id'          => $pro->id,
                'slug'        => 'pro',
                'name'        => 'Pro',
                'price_cents' => 2900,
                'currency'    => 'usd',
                'interval'    => 'month',
                'active'      => true,
            ]);
    }

    public function test_show_404_si_no_existe(): void
    {
        $response = $this->getJson('/api/plans/999999');
        $response->assertStatus(404);
    }

    public function test_features_se_devuelve_como_array(): void
    {
        $plan = Plan::create([
            'name' => 'Test', 'slug' => 'test', 'price_cents' => 0,
            'currency' => 'usd', 'interval' => 'month',
            'stripe_price_id' => null,
            'features' => ['max_users' => 5, 'support' => 'email'],
            'active' => true,
        ]);

        $response = $this->getJson("/api/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJson([
                'features' => ['max_users' => 5, 'support' => 'email'],
            ]);
    }
}
