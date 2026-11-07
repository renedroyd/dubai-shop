<?php

namespace Tests\Feature;

use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Pos\PosCashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosCashSessionTest extends TestCase
{
    use RefreshDatabase;

    private function grantWarehouseAccess(User $user): void
    {
        $user->update(['admin_access_enabled' => true]);
        $user->warehouseAccesses()->create([
            'warehouse_id' => Warehouse::primary()->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);
    }

    private function terminal(): PosTerminal
    {
        $location = PosLocation::query()->create([
            'code' => 'STORE-01',
            'name' => 'Tienda principal',
            'is_active' => true,
        ]);

        return PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'POS-01',
            'name' => 'Caja principal',
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_open_record_movements_and_close_a_session_with_reconciliation(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $service = app(PosCashSessionService::class);
        $session = $service->open($this->terminal(), $user->id, 'CUP', 10000, 'Fondo inicial');

        $service->recordCashMovement($session, $user->id, 'cash_in', 2500, 'Cambio recibido');
        $service->recordCashMovement($session, $user->id, 'cash_out', 1000, 'Compra de materiales');

        $closed = $service->close($session, $user->id, 11300, 'Cierre de turno');

        $this->assertSame(PosCashSession::STATUS_CLOSED, $closed->status);
        $this->assertSame(11500, $closed->expected_cash_amount);
        $this->assertSame(11300, $closed->counted_cash_amount);
        $this->assertSame(-200, $closed->difference_amount);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_terminal_cannot_be_deactivated_while_its_cash_session_is_open(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $terminal = $this->terminal();
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 0);

        $this->expectException(ValidationException::class);
        $terminal->update(['is_active' => false]);
    }

    public function test_terminal_cannot_have_two_open_cash_sessions(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $terminal = $this->terminal();
        $service = app(PosCashSessionService::class);
        $service->open($terminal, $user->id, 'CUP', 0);

        $this->expectException(ValidationException::class);
        $service->open($terminal, $user->id, 'CUP', 0);
    }

    public function test_cash_withdrawal_cannot_exceed_the_available_register_balance(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $service = app(PosCashSessionService::class);
        $session = $service->open($this->terminal(), $user->id, 'CUP', 1000);

        $this->expectException(ValidationException::class);
        $service->recordCashMovement($session, $user->id, 'cash_out', 1001, 'Retiro no permitido');
    }

    public function test_closed_session_rejects_cash_movements_and_cannot_be_closed_twice(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $service = app(PosCashSessionService::class);
        $session = $service->open($this->terminal(), $user->id, 'USD', 0);
        $closed = $service->close($session, $user->id, 0);

        try {
            $service->recordCashMovement($closed, $user->id, 'cash_in', 100, 'Fondo adicional');
            $this->fail('Expected a validation exception for a closed session.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $service->close($closed, $user->id, 0);
    }
}