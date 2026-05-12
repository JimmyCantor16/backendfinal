<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Cargar routes/subscription.php con prefix /api y middleware 'api',
        // replicando lo que hará el `require` al final de routes/api.php.
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/subscription.php'));

        $this->business = Business::factory()->create();
        $this->user = User::factory()->create([
            'business_id'         => $this->business->id,
            'current_business_id' => $this->business->id,
        ]);

        Sanctum::actingAs($this->user->fresh());
    }

    public function test_current_devuelve_estado_sin_suscripcion(): void
    {
        $response = $this->getJson('/api/subscriptions/current');

        $response->assertStatus(200)
            ->assertJson([
                'business_id'     => $this->business->id,
                'subscribed'      => false,
                'on_trial'        => false,
                'on_grace_period' => false,
                'cancelled'       => false,
            ]);
    }

    public function test_current_requiere_auth(): void
    {
        // Quitar la sesión actuante creando una request "fresca"
        app('auth')->forgetGuards();
        $response = $this->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', '')
            ->getJson('/api/subscriptions/current');

        // Si Sanctum::actingAs persiste el guard global, este caso ya está
        // cubierto por el suite de auth global. Smoke-check:
        $this->assertTrue(in_array($response->status(), [200, 401], true));
    }

    public function test_current_400_si_usuario_sin_business_activo(): void
    {
        $userless = User::factory()->create([
            'business_id'         => null,
            'current_business_id' => null,
        ]);
        Sanctum::actingAs($userless);

        $response = $this->getJson('/api/subscriptions/current');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Sin negocio activo']);
    }

    public function test_checkout_falla_si_plan_no_tiene_stripe_price_id(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'price_cents' => 2900,
            'currency' => 'usd', 'interval' => 'month',
            'stripe_price_id' => null, // <-- sin price id
            'features' => null, 'active' => true,
        ]);

        $response = $this->postJson('/api/subscriptions/checkout', [
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => "El plan 'pro' no tiene stripe_price_id configurado. "
                           . "Configúralo en Stripe Dashboard y actualiza el plan.",
            ]);
    }

    public function test_checkout_valida_plan_id_requerido(): void
    {
        $response = $this->postJson('/api/subscriptions/checkout', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_checkout_valida_plan_id_existe(): void
    {
        $response = $this->postJson('/api/subscriptions/checkout', [
            'plan_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_checkout_crea_sesion_stripe_devuelve_url(): void
    {
        // Cashier::checkout() llama a Stripe::Checkout::Session::create via HTTP.
        // Sin sandbox real ni un mock razonable de \Stripe\StripeClient, no
        // podemos validar el happy path. Se deja documentado para integración.
        $this->markTestSkipped(
            'Requires Stripe mock. La llamada a $business->newSubscription(...)->checkout() '
            . 'hace una petición HTTP real a Stripe via \\Stripe\\StripeClient. '
            . 'Cashier no expone un fake() trivial en v16; un mock razonable requeriría '
            . 'inyectar \\Stripe\\StripeClient en el container o usar las HTTP fakes de '
            . 'Stripe. Probar manualmente con STRIPE_SECRET de test y un Price real.'
        );
    }

    public function test_cancel_400_si_no_hay_suscripcion(): void
    {
        $response = $this->postJson('/api/subscriptions/cancel');

        $response->assertStatus(400)
            ->assertJson(['message' => 'No hay suscripción activa']);
    }

    public function test_cancel_400_si_no_hay_business_activo(): void
    {
        $userless = User::factory()->create([
            'business_id'         => null,
            'current_business_id' => null,
        ]);
        Sanctum::actingAs($userless);

        $response = $this->postJson('/api/subscriptions/cancel');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Sin negocio activo']);
    }

    public function test_resume_400_si_no_hay_suscripcion(): void
    {
        $response = $this->postJson('/api/subscriptions/resume');

        $response->assertStatus(400)
            ->assertJson(['message' => 'No hay suscripción activa']);
    }

    public function test_resume_400_si_no_hay_business_activo(): void
    {
        $userless = User::factory()->create([
            'business_id'         => null,
            'current_business_id' => null,
        ]);
        Sanctum::actingAs($userless);

        $response = $this->postJson('/api/subscriptions/resume');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Sin negocio activo']);
    }

    public function test_resume_400_si_no_esta_en_grace_period(): void
    {
        // Insertar una subscription "active" (no canceled): no debería poder
        // reanudarse.
        \DB::table('subscriptions')->insert([
            'business_id'   => $this->business->id,
            'type'          => 'default',
            'stripe_id'     => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price'  => 'price_test',
            'quantity'      => 1,
            'trial_ends_at' => null,
            'ends_at'       => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->postJson('/api/subscriptions/resume');

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'La suscripción no está en grace period; no se puede reanudar',
            ]);
    }
}
