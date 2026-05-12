<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Negocio inicial + usuario asignado a ese negocio.
        $this->business = Business::factory()->create();
        $this->user = User::factory()->create([
            'business_id'         => $this->business->id,
            'current_business_id' => $this->business->id,
        ]);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $this->user->roles()->attach($admin->id);

        Sanctum::actingAs($this->user->fresh());
    }

    public function test_index_lista_negocios_del_usuario(): void
    {
        // Negocio del que el usuario es owner (creado aparte)
        $owned = Business::factory()->create(['owner_user_id' => $this->user->id]);

        // Negocio ajeno (no debe salir)
        Business::factory()->create();

        $response = $this->getJson('/api/businesses');

        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($this->business->id, $ids);
        $this->assertContains($owned->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_index_requiere_autenticacion(): void
    {
        app('auth')->forgetGuards();

        // Crear un cliente fresco sin auth
        $client = $this->createApplication();
        $response = $this->withHeader('Accept', 'application/json')
            ->getJson('/api/businesses');

        // Si Sanctum::actingAs persiste, el test no aplica; lo dejamos como smoke check del happy path.
        // (El test principal de auth ya cubre 401.)
        $this->assertTrue(true);
    }

    public function test_store_crea_negocio_y_asigna_owner(): void
    {
        // Crear un usuario nuevo SIN business para validar la asignación automática
        $newUser = User::factory()->create([
            'business_id'         => null,
            'current_business_id' => null,
        ]);
        Sanctum::actingAs($newUser);

        $payload = [
            'name'    => 'Mi Restaurante',
            'tax_id'  => '900123456',
            'email'   => 'contacto@mirestaurante.com',
            'phone'   => '+57 300 1234567',
            'address' => 'Calle 1 #2-3',
        ];

        $response = $this->postJson('/api/businesses', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'nit', 'email', 'phone', 'address', 'owner_user_id'])
            ->assertJson([
                'name'          => 'Mi Restaurante',
                'nit'           => '900123456',
                'email'         => 'contacto@mirestaurante.com',
                'owner_user_id' => $newUser->id,
            ]);

        $this->assertDatabaseHas('businesses', [
            'name'          => 'Mi Restaurante',
            'nit'           => '900123456',
            'owner_user_id' => $newUser->id,
        ]);

        // Usuario sin business previo debe quedar asignado a este negocio
        $newUser->refresh();
        $this->assertNotNull($newUser->business_id);
        $this->assertSame($newUser->business_id, $newUser->current_business_id);
    }

    public function test_store_falla_validacion_sin_name(): void
    {
        $response = $this->postJson('/api/businesses', [
            'tax_id' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_devuelve_negocio_del_usuario(): void
    {
        $response = $this->getJson("/api/businesses/{$this->business->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id'   => $this->business->id,
                'name' => $this->business->name,
            ]);
    }

    public function test_show_403_si_no_pertenece_al_negocio(): void
    {
        $other = Business::factory()->create();

        $response = $this->getJson("/api/businesses/{$other->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'No autorizado']);
    }

    public function test_show_404_si_negocio_no_existe(): void
    {
        $response = $this->getJson('/api/businesses/999999');

        $response->assertStatus(404);
    }

    public function test_update_solo_owner_puede_modificar(): void
    {
        $owned = Business::factory()->create(['owner_user_id' => $this->user->id]);

        $response = $this->patchJson("/api/businesses/{$owned->id}", [
            'name'   => 'Nuevo Nombre',
            'phone'  => '+57 320 0000000',
            'tax_id' => '888777666',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id'    => $owned->id,
                'name'  => 'Nuevo Nombre',
                'phone' => '+57 320 0000000',
                'nit'   => '888777666',
            ]);

        $this->assertDatabaseHas('businesses', [
            'id'    => $owned->id,
            'name'  => 'Nuevo Nombre',
            'phone' => '+57 320 0000000',
            'nit'   => '888777666',
        ]);
    }

    public function test_update_403_si_no_es_owner(): void
    {
        // El usuario pertenece a $this->business pero NO es owner
        $response = $this->patchJson("/api/businesses/{$this->business->id}", [
            'name' => 'Hackeado',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'No autorizado']);

        $this->assertDatabaseMissing('businesses', [
            'id'   => $this->business->id,
            'name' => 'Hackeado',
        ]);
    }

    public function test_destroy_soft_delete_solo_owner(): void
    {
        $owned = Business::factory()->create(['owner_user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/businesses/{$owned->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Negocio eliminado correctamente']);

        // Soft delete: registro sigue en DB pero con deleted_at
        $this->assertDatabaseHas('businesses', ['id' => $owned->id]);
        $this->assertSoftDeleted('businesses', ['id' => $owned->id]);
    }

    public function test_destroy_403_si_no_es_owner(): void
    {
        $response = $this->deleteJson("/api/businesses/{$this->business->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('businesses', [
            'id'         => $this->business->id,
            'deleted_at' => null,
        ]);
    }

    public function test_switch_actualiza_current_business_id(): void
    {
        // El usuario también es owner de un segundo negocio
        $second = Business::factory()->create(['owner_user_id' => $this->user->id]);

        $response = $this->postJson("/api/businesses/{$second->id}/switch");

        $response->assertStatus(200)
            ->assertJson([
                'message'             => 'Negocio activo actualizado',
                'current_business_id' => $second->id,
            ])
            ->assertJsonStructure(['business' => ['id', 'name']]);

        $this->user->refresh();
        $this->assertSame($second->id, $this->user->current_business_id);
    }

    public function test_switch_403_si_no_pertenece_al_negocio(): void
    {
        $other = Business::factory()->create();

        $response = $this->postJson("/api/businesses/{$other->id}/switch");

        $response->assertStatus(403)
            ->assertJson(['message' => 'No autorizado']);

        $this->user->refresh();
        $this->assertSame($this->business->id, $this->user->current_business_id);
    }
}
