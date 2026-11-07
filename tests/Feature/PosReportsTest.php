<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\PaymentMethod;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Pos\PosCashSessionService;
use App\Services\Pos\PosReportService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosReportsTest extends TestCase
{
    use RefreshDatabase;

    private function reportingCashier(): User
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'POS reporting',
            'slug' => 'pos-reporting-'.uniqid(),
            'permissions' => ['panel.access', 'pos_reports.view'],
        ]);
        $user->roles()->attach($role);
        $user->warehouseAccesses()->create([
            'warehouse_id' => Warehouse::primary()->id,
            'permissions' => ['view', 'pos.operate', 'reports.view'],
        ]);

        return $user;
    }

    private function saleFor(User $user): array
    {
        $location = PosLocation::query()->create([
            'code' => 'REPORT-LOC-'.uniqid(),
            'name' => 'Tienda reportes',
            'is_active' => true,
        ]);
        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'REPORT-POS-'.uniqid(),
            'name' => 'Caja reportes',
            'is_active' => true,
        ]);
        $session = app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);
        $product = Product::query()->create([
            'name' => 'Producto de informe',
            'slug' => 'producto-informe-'.uniqid(),
            'sku' => 'REPORT-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
        $inventory = app(InventoryService::class)->setOnHand($product, 5, reason: 'Carga inicial');
        $inventory->update(['acquisition_cost_amount' => 500]);
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();
        $order = app(PosSaleService::class)->sell(
            $session,
            $user->id,
            [['product_id' => $product->id, 'quantity' => 1]],
            $method->id,
        );

        return [$session, $order, $product];
    }

    public function test_reports_calculate_sales_gross_margin_tender_and_cash_difference_by_currency(): void
    {
        $user = $this->reportingCashier();
        [$session, $order, $product] = $this->saleFor($user);
        app(PosCashSessionService::class)->close($session, $user->id, 6200);

        $report = app(PosReportService::class)->build($user, [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'warehouse_id' => Warehouse::primary()->id,
            'terminal_id' => null,
            'cashier_id' => null,
            'currency' => 'CUP',
        ]);

        $this->assertSame(1, (int) $report['sales']->first()->orders_count);
        $this->assertSame(1250, (int) $report['sales']->first()->total_amount);
        $this->assertSame(750, (int) $report['grossMargin']->first()->margin_amount);
        $this->assertSame(1250, (int) $report['payments']->first()->amount);
        $this->assertSame(-50, (int) $report['cashDiscrepancies']->first()->difference_amount);
        $this->assertSame('Producto de informe', $report['salesByProduct']->first()->product_name);
        $this->assertSame($order->id, $order->fresh()->id);
    }

    public function test_report_page_exports_filtered_sales_as_csv(): void
    {
        $user = $this->reportingCashier();
        $this->saleFor($user);

        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\PosReports::class)
            ->call('exportCsv', now()->toDateString(), now()->toDateString(), Warehouse::primary()->id, null, null, 'CUP')
            ->assertFileDownloaded(fn (string $filename) => str_starts_with($filename, 'informe-pos-') && str_ends_with($filename, '.csv'));
    }
}
