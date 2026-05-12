<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock global del servicio reCAPTCHA: siempre retorna true.
        $this->mock(RecaptchaService::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(true);
        });
    }

    public function test_login_ok_con_credenciales_validas(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'email'       => 'admin@example.com',
            'password'    => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email'           => 'admin@example.com',
            'password'        => 'secret-password',
            'recaptcha_token' => 'fake-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'business_id'],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'user'       => [
                    'id'    => $user->id,
                    'email' => 'admin@example.com',
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => User::class,
            'name'           => 'auth_token',
        ]);
    }

    public function test_login_falla_con_password_invalida(): void
    {
        $business = Business::factory()->create();
        User::factory()->create([
            'business_id' => $business->id,
            'email'       => 'admin@example.com',
            'password'    => 'secret-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email'           => 'admin@example.com',
            'password'        => 'wrong-password',
            'recaptcha_token' => 'fake-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Credenciales inválidas']);
    }

    public function test_login_falla_con_email_inexistente(): void
    {
        $response = $this->postJson('/api/login', [
            'email'           => 'noexiste@example.com',
            'password'        => 'whatever',
            'recaptcha_token' => 'fake-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Credenciales inválidas']);
    }

    public function test_login_falla_validacion_sin_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password'        => 'secret-password',
            'recaptcha_token' => 'fake-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_invalida_token_actual(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['business_id' => $business->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);
    }

    public function test_me_devuelve_usuario_autenticado(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'email'       => 'me@example.com',
        ]);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'roles', 'business'])
            ->assertJson([
                'id'    => $user->id,
                'email' => 'me@example.com',
            ]);
    }

    public function test_me_sin_autenticacion_devuelve_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_verify_password_ok_con_password_correcta(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'password'    => 'secret-password',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/verify-password', [
            'password' => 'secret-password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Contraseña verificada correctamente']);
    }

    public function test_verify_password_falla_con_password_incorrecta(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create([
            'business_id' => $business->id,
            'password'    => 'secret-password',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/verify-password', [
            'password' => 'incorrecta',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Contraseña incorrecta']);
    }
}
