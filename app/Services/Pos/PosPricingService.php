<?php

namespace App\Services\Pos;

use App\Models\Inventory;
use App\Models\PosPriceRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PosPricingService
{
    public function previewLine(Product $product, ?ProductVariant $variant, ?Inventory $inventory, ?int $customerId, int $warehouseId, string $currency, ?User $cashier = null): array
    {
        return $this->resolveLine($product, $variant, $inventory, $customerId, $cashier, $warehouseId, $currency);
    }

    public function priceLine(Product $product, ?ProductVariant $variant, ?Inventory $inventory, ?User $customer, User $cashier, int $warehouseId, string $currency): array
    {
        return $this->resolveLine($product, $variant, $inventory, $customer?->id, $cashier, $warehouseId, $currency);
    }

    public function previewOrderDiscount(int $netSubtotal, ?int $customerId, int $warehouseId, string $currency, array $preparedLines = [], ?User $cashier = null): array
    {
        return $this->resolveOrderDiscount($netSubtotal, $preparedLines, $customerId, $cashier, $warehouseId, $currency);
    }

    public function priceOrderDiscount(int $netSubtotal, array $preparedLines, ?User $customer, User $cashier, int $warehouseId, string $currency): array
    {
        return $this->resolveOrderDiscount($netSubtotal, $preparedLines, $customer?->id, $cashier, $warehouseId, $currency);
    }

    private function resolveLine(Product $product, ?ProductVariant $variant, ?Inventory $inventory, ?int $customerId, ?User $cashier, int $warehouseId, string $currency): array
    {
        $base = (int) ($variant?->price_amount ?? $product->price_amount);
        $rule = $this->lineRule($product, $customerId, $warehouseId, $currency);
        $result = $this->calculate($base, $rule);
        $approvedBy = null;

        if ($result['discount_amount'] > 0) {
            if (! $inventory || $inventory->acquisition_cost_amount === null) {
                return $this->blockedResult($base, __('Configura el costo de adquisición en este almacén para aplicar la promoción.'));
            }

            if ($rule?->requires_approval && ! ($cashier?->hasAdminPermission('pos_discounts.approve') ?? false)) {
                return $this->blockedResult($base, __('Esta promoción requiere autorización de un supervisor.'));
            }

            $floor = max((int) $inventory->acquisition_cost_amount, (int) ($inventory->minimum_sale_price_amount ?? $inventory->acquisition_cost_amount));
            if ($result['unit_price_amount'] < $floor) {
                if (! ($cashier?->hasAdminPermission('pos_discounts.override_floor') ?? false)) {
                    return $this->blockedResult($base, __('La promoción supera el límite mínimo de costo/precio de este almacén.'));
                }
                $approvedBy = $cashier?->id;
            }

            if ($rule?->requires_approval) $approvedBy = $cashier?->id;
        }

        return $result + ['approved_by' => $approvedBy, 'blocked_reason' => null];
    }

    private function resolveOrderDiscount(int $netSubtotal, array $preparedLines, ?int $customerId, ?User $cashier, int $warehouseId, string $currency): array
    {
        $rule = $this->orderRule($customerId, $warehouseId, $currency);
        $result = $this->calculate($netSubtotal, $rule, true);
        $approvedBy = null;

        if ($result['discount_amount'] > 0) {
            if ($rule?->requires_approval && ! ($cashier?->hasAdminPermission('pos_discounts.approve') ?? false)) {
                return $this->blockedResult($netSubtotal, __('El descuento de la venta requiere autorización de un supervisor.'));
            }

            $floorTotal = 0;
            foreach ($preparedLines as $line) {
                $inventory = $line['inventory'] ?? null;
                if (! $inventory || $inventory->acquisition_cost_amount === null) {
                    return $this->blockedResult($netSubtotal, __('Configura el costo de todos los artículos antes de aplicar un descuento a la venta.'));
                }
                $floorTotal += max((int) $inventory->acquisition_cost_amount, (int) ($inventory->minimum_sale_price_amount ?? $inventory->acquisition_cost_amount)) * (int) $line['quantity'];
            }

            if (($netSubtotal - $result['discount_amount']) < $floorTotal) {
                if (! ($cashier?->hasAdminPermission('pos_discounts.override_floor') ?? false)) {
                    return $this->blockedResult($netSubtotal, __('El descuento de la venta supera el límite mínimo de costo/precio de este almacén.'));
                }
                $approvedBy = $cashier?->id;
            }

            if ($rule?->requires_approval) $approvedBy = $cashier?->id;
        }

        return $result + ['approved_by' => $approvedBy, 'blocked_reason' => null];
    }

    private function blockedResult(int $baseAmount, string $reason): array
    {
        return ['rule' => null, 'original_unit_price_amount' => $baseAmount, 'unit_price_amount' => $baseAmount, 'discount_amount' => 0, 'approved_by' => null, 'blocked_reason' => $reason];
    }

    private function lineRule(Product $product, ?int $customerId, int $warehouseId, string $currency): ?PosPriceRule
    {
        return PosPriceRule::query()->activeAt()
            ->whereIn('scope', [PosPriceRule::SCOPE_PRODUCT, PosPriceRule::SCOPE_CATEGORY, PosPriceRule::SCOPE_BRAND])
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $match) => $match->where('scope', PosPriceRule::SCOPE_PRODUCT)->where('target_id', $product->id))
                ->orWhere(fn (Builder $match) => $match->where('scope', PosPriceRule::SCOPE_CATEGORY)->where('target_id', $product->category_id))
                ->orWhere(fn (Builder $match) => $match->where('scope', PosPriceRule::SCOPE_BRAND)->where('target_id', $product->brand_id)))
            ->where(fn (Builder $query) => $query->whereNull('customer_id')->orWhere('customer_id', $customerId))
            ->where(fn (Builder $query) => $query->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouseId))
            ->where(fn (Builder $query) => $query->whereNull('currency')->orWhere('currency', $currency))
            ->orderByDesc('priority')
            ->orderByRaw("CASE WHEN customer_id IS NULL THEN 0 ELSE 1 END DESC")
            ->orderByRaw("CASE scope WHEN 'product' THEN 3 WHEN 'category' THEN 2 ELSE 1 END DESC")
            ->first();
    }

    private function orderRule(?int $customerId, int $warehouseId, string $currency): ?PosPriceRule
    {
        return PosPriceRule::query()->activeAt()
            ->where('scope', PosPriceRule::SCOPE_ORDER)
            ->where(fn (Builder $query) => $query->whereNull('customer_id')->orWhere('customer_id', $customerId))
            ->where(fn (Builder $query) => $query->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouseId))
            ->where(fn (Builder $query) => $query->whereNull('currency')->orWhere('currency', $currency))
            ->orderByDesc('priority')
            ->orderByRaw("CASE WHEN customer_id IS NULL THEN 0 ELSE 1 END DESC")
            ->first();
    }

    private function calculate(int $baseAmount, ?PosPriceRule $rule, bool $orderLevel = false): array
    {
        if (! $rule || ($orderLevel && $rule->rule_type === PosPriceRule::TYPE_SPECIAL_PRICE)) {
            return ['rule' => null, 'original_unit_price_amount' => $baseAmount, 'unit_price_amount' => $baseAmount, 'discount_amount' => 0];
        }

        $discount = match ($rule->rule_type) {
            PosPriceRule::TYPE_PERCENTAGE => (int) round($baseAmount * min(10000, (int) $rule->value_amount) / 10000),
            PosPriceRule::TYPE_FIXED => min($baseAmount, (int) $rule->value_amount),
            PosPriceRule::TYPE_SPECIAL_PRICE => max(0, $baseAmount - min($baseAmount, (int) $rule->value_amount)),
            default => 0,
        };
        $price = $rule->rule_type === PosPriceRule::TYPE_SPECIAL_PRICE
            ? min($baseAmount, (int) $rule->value_amount)
            : max(0, $baseAmount - $discount);

        return ['rule' => $discount > 0 ? $rule : null, 'original_unit_price_amount' => $baseAmount, 'unit_price_amount' => $price, 'discount_amount' => $discount];
    }

    private function authorizeRule(?PosPriceRule $rule, User $cashier, int $discountAmount): ?int
    {
        if (! $rule || $discountAmount <= 0) return null;
        if ($rule->requires_approval && ! $cashier->hasAdminPermission('pos_discounts.approve')) {
            throw ValidationException::withMessages(['discounts' => "La regla {$rule->name} requiere autorización de un supervisor."]);
        }
        return $rule->requires_approval ? $cashier->id : null;
    }
}
