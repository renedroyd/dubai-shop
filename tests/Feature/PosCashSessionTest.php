<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Pos\PosCashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function test_terminal_assignment_restricts_which_cashier_can_open_a_register(): void
    {
        $assigned = User::factory()->create();
        $unassigned = User::factory()->create();
        $this->grantWarehouseAccess($assigned);
        $this->grantWarehouseAccess($unassigned);
        $terminal = $this->terminal();
        $terminal->update(['assigned_cashier_ids' => [$assigned->id]]);

        $this->expectException(AuthorizationException::class);
        app(PosCashSessionService::class)->open($terminal, $unassigned->id, 'CUP', 0);
    }

    public function test_cash_limit_rejects_manual_cash_in_that_would_exceed_terminal_limit(): void
    {
        $user = User::factory()->create();
        $this->grantWarehouseAccess($user);
        $terminal = $this->terminal();
        $terminal->update(['cash_limit_amount' => 6000]);
        $session = app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PosCashSessionService::class)->recordCashMovement($session, $user->id, 'cash_in', 1001, 'Entrada que supera límite');
    }

    public function test_supervised_close_requires_a_different_authorized_approver(): void
    {
        $cashier = User::factory()->create();
        $supervisor = User::factory()->create();
        $this->grantWarehouseAccess($cashier);
        $this->grantWarehouseAccess($supervisor);

        $role = AdminRole::query()->create([
            'name' => 'Supervisor de caja',
            'slug' => 'cash-close-supervisor-'.uniqid(),
            'permissions' => ['pos_cash_sessions.approve_close'],
        ]);
        $cashier->roles()->attach($role);
        $supervisor->roles()->attach($role);

        $terminal = $this->terminal();
        $terminal->update(['supervised_closing' => true]);
        $service = app(PosCashSessionService::class);
        $session = $service->open($terminal, $cashier->id, 'CUP', 500, 'Fondo', 'Turno mañana');

        $requested = $service->close($session, $cashier->id, 450, 'Cierre de turno');

        $this->assertNotNull($requested->close_requested_at);
        $this->assertNull($requested->closed_at);
        $this->assertSame('Turno mañana', $requested->shift_label);
        $this->assertFalse($requested->isOpen());

        try {
            $service->recordCashMovement($requested, $cashier->id, 'cash_in', 100, 'No debe registrarse');
            $this->fail('A session awaiting close approval must reject cash movements.');
        } catch (\\Illuminate\\Validation\\ValidationException) {
            $this->assertDatabaseCount('pos_cash_movements', 0);
        }

        try {
            $service->approveClose($requested, $cashier->id);
            $this->fail('The cashier who requested the close must not approve it.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertNull($requested->fresh()->closed_at);
        }

        $closed = $service->approveClose($requested, $supervisor->id);

        $this->assertSame(PosCashSession::STATUS_CLOSED, $closed->status);
        $this->assertSame($cashier->id, $closed->closed_by);
        $this->assertSame($supervisor->id, $closed->close_approved_by);
        $this->assertSame(-50, $closed->difference_amount);
    }

}