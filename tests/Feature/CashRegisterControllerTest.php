<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAuthenticatedUser;
use Tests\TestCase;

class CashRegisterControllerTest extends TestCase
{
    use RefreshDatabase, CreatesAuthenticatedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsAdmin();
    }

    public function test_abrir_caja_ok(): void
    {
        $response = $this->postJson('/api/cash-registers/open', [
            'opening_amount' => 50000,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'user_id', 'opening_amount', 'status', 'opened_at'])
            ->assertJson([
                'status'  => 'open',
                'user_id' => $this->user->id,
            ]);

        $this->assertDatabaseHas('cash_registers', [
            'business_id'    => $this->business->id,
            'user_id'        => $this->user->id,
            'opening_amount' => 50000,
            'status'         => 'open',
        ]);
    }

    public function test_abrir_segunda_caja_falla(): void
    {
        // Primera caja
        $this->openCashRegisterFor($this->user, 30000);

        $response = $this->postJson('/api/cash-registers/open', [
            'opening_amount' => 50000,
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_abrir_caja_falla_validacion_sin_opening_amount(): void
    {
        $response = $this->postJson('/api/cash-registers/open', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_amount']);
    }

    public function test_obtener_caja_actual(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user, 20000);

        $response = $this->getJson('/api/cash-registers/current');

        $response->assertStatus(200)
            ->assertJson([
                'id'      => $cashRegister->id,
                'status'  => 'open',
                'user_id' => $this->user->id,
            ])
            ->assertJsonStructure(['id', 'opening_amount', 'status', 'orders_closed']);
    }

    public function test_obtener_caja_actual_404_si_no_hay_caja_abierta(): void
    {
        $response = $this->getJson('/api/cash-registers/current');

        $response->assertStatus(404)
            ->assertJson(['message' => 'No tienes una caja abierta.']);
    }

    public function test_cerrar_caja_ok_y_calcula_totales(): void
    {
        $cashRegister = $this->openCashRegisterFor($this->user, 10000);

        $response = $this->postJson("/api/cash-registers/{$cashRegister->id}/close");

        $response->assertStatus(200)
            ->assertJson([
                'id'     => $cashRegister->id,
                'status' => 'closed',
            ])
            ->assertJsonStructure(['id', 'closing_amount', 'total_sales', 'closed_at']);

        $this->assertDatabaseHas('cash_registers', [
            'id'             => $cashRegister->id,
            'status'         => 'closed',
            'closing_amount' => 10000,
        ]);
    }

    public function test_cerrar_caja_ya_cerrada_falla(): void
    {
        $cashRegister = CashRegister::factory()->closed()->create([
            'business_id' => $this->business->id,
            'user_id'     => $this->user->id,
        ]);

        $response = $this->postJson("/api/cash-registers/{$cashRegister->id}/close");

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_ver_reporte_caja_cerrada(): void
    {
        $cashRegister = CashRegister::factory()->closed()->create([
            'business_id'    => $this->business->id,
            'user_id'        => $this->user->id,
            'opening_amount' => 10000,
            'closing_amount' => 25000,
            'total_cash'     => 15000,
            'total_card'     => 0,
            'total_sales'    => 15000,
        ]);

        $response = $this->getJson("/api/cash-registers/{$cashRegister->id}/report");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cash_register',
                'resumen' => [
                    'total_ordenes',
                    'ordenes_cerradas',
                    'ordenes_canceladas',
                    'total_cash',
                    'total_card',
                    'total_ventas',
                    'monto_apertura',
                    'monto_cierre',
                ],
                'ordenes',
            ]);
    }
}
