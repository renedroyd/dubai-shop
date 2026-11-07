<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PosCashSession;
use App\Models\PosRefund;
use App\Models\PosTerminal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PosReportService
{
    public function build(User $user, array $filters): array
    {
        $warehouseIds = $user->warehouseIdsWithPermission('reports.view');
        if ($warehouseIds === []) {
            abort(403);
        }

        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $terminalId = $filters['terminal_id'] ?? null;
        $cashierId = $filters['cashier_id'] ?? null;
        $currency = $filters['currency'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $brandId = $filters['brand_id'] ?? null;
        $warehouseId = $filters['warehouse_id'] ?? null;

        $orders = fn (Carbon $start, Carbon $end): Builder => Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $warehouseIds)
            ->whereBetween('placed_at', [$start, $end])
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->when($warehouseId, fn (Builder $query) => $query->where('pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn (Builder $query) => $query->where('pos_terminal_id', $terminalId))
            ->when($cashierId, fn (Builder $query) => $query->where('cashier_id', $cashierId))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->when($categoryId, fn (Builder $query) => $query->whereHas('items.product', fn (Builder $product) => $product->where('category_id', $categoryId)))
            ->when($brandId, fn (Builder $query) => $query->whereHas('items.product', fn (Builder $product) => $product->where('brand_id', $brandId)));

        $sales = $orders($from, $to)
            ->selectRaw('currency, COUNT(*) as orders_count, SUM(subtotal_amount) as subtotal_amount, SUM(discount_amount) as discounts_amount, SUM(total_amount) as total_amount')
            ->groupBy('currency')->orderBy('currency')->get();

        $salesByWarehouse = $orders($from, $to)
            ->selectRaw('pos_warehouse_id, currency, COUNT(*) as orders_count, SUM(total_amount) as total_amount, SUM(discount_amount) as discounts_amount')
            ->groupBy('pos_warehouse_id', 'currency')->with('posWarehouse:id,name')->orderBy('pos_warehouse_id')->get();

        $salesByTerminal = $orders($from, $to)
            ->selectRaw('pos_terminal_id, pos_warehouse_id, currency, COUNT(*) as orders_count, SUM(total_amount) as total_amount')
            ->groupBy('pos_terminal_id', 'pos_warehouse_id', 'currency')
            ->with(['posTerminal:id,name,code', 'posWarehouse:id,name'])
            ->orderBy('pos_warehouse_id')->orderBy('pos_terminal_id')->get();

        $salesByCashier = $orders($from, $to)
            ->selectRaw('cashier_id, currency, COUNT(*) as orders_count, SUM(total_amount) as total_amount')
            ->groupBy('cashier_id', 'currency')->with('cashier:id,name')->orderBy('cashier_id')->get();

        $itemBase = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereIn('orders.payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->when($warehouseId, fn ($query) => $query->where('orders.pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn ($query) => $query->where('orders.pos_terminal_id', $terminalId))
            ->when($cashierId, fn ($query) => $query->where('orders.cashier_id', $cashierId))
            ->when($currency, fn ($query) => $query->where('orders.currency', $currency))
            ->when($categoryId, fn ($query) => $query->where('products.category_id', $categoryId))
            ->when($brandId, fn ($query) => $query->where('products.brand_id', $brandId));

        $salesByProduct = (clone $itemBase)
            ->selectRaw('order_items.product_name, order_items.variant_name, order_items.sku, order_items.currency, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total_amount) as sales_amount')
            ->groupBy('order_items.product_name', 'order_items.variant_name', 'order_items.sku', 'order_items.currency')
            ->orderByDesc('sales_amount')->limit(20)->get();

        $salesByCategory = (clone $itemBase)
            ->selectRaw("COALESCE(categories.name, 'Sin categoría') as category_name, order_items.currency, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total_amount) as sales_amount")
            ->groupBy('categories.name', 'order_items.currency')->orderByDesc('sales_amount')->get();

        $salesByBrand = (clone $itemBase)
            ->selectRaw("COALESCE(brands.name, 'Sin marca') as brand_name, order_items.currency, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total_amount) as sales_amount")
            ->groupBy('brands.name', 'order_items.currency')->orderByDesc('sales_amount')->get();

        $payments = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->where('payments.status', Payment::STATUS_PAID)
            ->when($warehouseId, fn (Builder $query) => $query->where('orders.pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn (Builder $query) => $query->where('orders.pos_terminal_id', $terminalId))
            ->when($cashierId, fn (Builder $query) => $query->where('orders.cashier_id', $cashierId))
            ->when($currency, fn (Builder $query) => $query->where('payments.currency', $currency))
            ->selectRaw("COALESCE(payment_methods.name, payments.driver) as method_name, payments.driver, payments.currency, COUNT(payments.id) as payments_count, SUM(payments.amount) as amount")
            ->groupBy('payment_methods.name', 'payments.driver', 'payments.currency')
            ->orderBy('payments.currency')->orderByDesc('amount')->get();

        $refunds = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->where('pos_refunds.status', 'completed')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('pos_refunds.refunded_at', [$from, $to])
            ->when($warehouseId, fn (Builder $query) => $query->where('orders.pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn (Builder $query) => $query->where('orders.pos_terminal_id', $terminalId))
            ->when($cashierId, fn (Builder $query) => $query->where('orders.cashier_id', $cashierId))
            ->when($currency, fn (Builder $query) => $query->where('pos_refunds.currency', $currency))
            ->selectRaw('pos_refunds.currency, COUNT(pos_refunds.id) as refunds_count, SUM(pos_refunds.amount) as amount')
            ->groupBy('pos_refunds.currency')->orderBy('pos_refunds.currency')->get();

        $refundsByWarehouse = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->where('pos_refunds.status', 'completed')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('pos_refunds.refunded_at', [$from, $to])
            ->when($warehouseId, fn (Builder $query) => $query->where('orders.pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn (Builder $query) => $query->where('orders.pos_terminal_id', $terminalId))
            ->when($cashierId, fn (Builder $query) => $query->where('orders.cashier_id', $cashierId))
            ->when($currency, fn (Builder $query) => $query->where('pos_refunds.currency', $currency))
            ->selectRaw('orders.pos_warehouse_id, pos_refunds.currency, COUNT(pos_refunds.id) as refunds_count, SUM(pos_refunds.amount) as amount')
            ->groupBy('orders.pos_warehouse_id', 'pos_refunds.currency')->with('order.posWarehouse:id,name')->get();

        $sessions = PosCashSession::query()
            ->whereHas('terminal', fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->whereBetween('opened_at', [$from, $to])
            ->when($warehouseId, fn (Builder $query) => $query->whereHas('terminal', fn (Builder $terminal) => $terminal->where('warehouse_id', $warehouseId)))
            ->when($terminalId, fn (Builder $query) => $query->where('pos_terminal_id', $terminalId))
            ->when($cashierId, fn (Builder $query) => $query->where('opened_by', $cashierId))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->with(['terminal.warehouse:id,name', 'opener:id,name', 'closer:id,name'])
            ->orderByDesc('opened_at')->get();

        $cashDiscrepancies = $sessions->whereNotNull('difference_amount')
            ->groupBy('currency')->map(fn ($rows, $code) => (object) [
                'currency' => $code,
                'sessions_count' => $rows->count(),
                'difference_amount' => $rows->sum('difference_amount'),
                'absolute_difference_amount' => $rows->sum(fn ($session) => abs((int) $session->difference_amount)),
            ])->values();

        $itemDiscounts = DB::table('order_items')
            ->selectRaw('order_id, SUM(discount_amount) as item_discounts')
            ->groupBy('order_id');

        $globalDiscounts = DB::table('orders')
            ->leftJoinSub($itemDiscounts, 'item_discounts', 'item_discounts.order_id', '=', 'orders.id')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereIn('orders.payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->when($warehouseId, fn ($query) => $query->where('orders.pos_warehouse_id', $warehouseId))
            ->when($terminalId, fn ($query) => $query->where('orders.pos_terminal_id', $terminalId))
            ->when($cashierId, fn ($query) => $query->where('orders.cashier_id', $cashierId))
            ->when($currency, fn ($query) => $query->where('orders.currency', $currency))
            ->when($categoryId, fn ($query) => $query->whereExists(function ($sub) use ($categoryId) {
                $sub->selectRaw('1')->from('order_items')->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('order_items.order_id', 'orders.id')->where('products.category_id', $categoryId);
            }))
            ->when($brandId, fn ($query) => $query->whereExists(function ($sub) use ($brandId) {
                $sub->selectRaw('1')->from('order_items')->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('order_items.order_id', 'orders.id')->where('products.brand_id', $brandId);
            }))
            ->selectRaw('orders.currency, SUM(CASE WHEN orders.discount_amount > COALESCE(item_discounts.item_discounts, 0) THEN orders.discount_amount - COALESCE(item_discounts.item_discounts, 0) ELSE 0 END) as amount')
            ->groupBy('orders.currency')->get()->keyBy('currency');

        $marginRows = (clone $itemBase)
            ->selectRaw('order_items.currency, SUM(order_items.line_total_amount) as item_revenue, SUM(CASE WHEN order_items.unit_cost_amount IS NULL THEN 1 ELSE 0 END) as missing_cost_lines, SUM(COALESCE(order_items.unit_cost_amount, 0) * order_items.quantity) as cost_amount')
            ->groupBy('order_items.currency')->get()->keyBy('currency');

        $grossMargin = $marginRows->map(function ($row, $code) use ($globalDiscounts) {
            $globalDiscount = (int) ($globalDiscounts->get($code)?->amount ?? 0);
            $missingCosts = (int) $row->missing_cost_lines;

            return (object) [
                'currency' => $code,
                'item_revenue' => (int) $row->item_revenue,
                'cost_amount' => (int) $row->cost_amount,
                'order_discount_amount' => $globalDiscount,
                'missing_cost_lines' => $missingCosts,
                'margin_amount' => $missingCosts > 0 ? null : (int) $row->item_revenue - (int) $row->cost_amount - $globalDiscount,
            ];
        })->values();

        $periodDays = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $previousTo = $from->copy()->subSecond()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($periodDays - 1)->startOfDay();
        $previousSales = $orders($previousFrom, $previousTo)
            ->selectRaw('currency, SUM(total_amount) as total_amount, COUNT(*) as orders_count')
            ->groupBy('currency')->get();

        return [
            'sales' => $sales,
            'salesByWarehouse' => $salesByWarehouse,
            'salesByTerminal' => $salesByTerminal,
            'salesByCashier' => $salesByCashier,
            'salesByProduct' => $salesByProduct,
            'salesByCategory' => $salesByCategory,
            'salesByBrand' => $salesByBrand,
            'payments' => $payments,
            'refunds' => $refunds,
            'refundsByWarehouse' => $refundsByWarehouse,
            'sessions' => $sessions,
            'cashDiscrepancies' => $cashDiscrepancies,
            'grossMargin' => $grossMargin,
            'previousSales' => $previousSales,
            'previousFrom' => $previousFrom,
            'previousTo' => $previousTo,
        ];
    }

    public function exportRows(User $user, array $filters): iterable
    {
        $warehouseIds = $user->warehouseIdsWithPermission('reports.view');
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        return Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $warehouseIds)
            ->whereBetween('placed_at', [$from, $to])
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_warehouse_id', $id))
            ->when($filters['terminal_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_terminal_id', $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, $id) => $query->where('cashier_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, $code) => $query->where('currency', $code))
            ->with(['posWarehouse:id,name', 'posTerminal:id,name,code', 'cashier:id,name'])
            ->orderBy('placed_at')->cursor();
    }
}
