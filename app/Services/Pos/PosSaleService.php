<?php

namespace App\Services\Pos;

use App\Models\Inventory;
use App\Models\InventoryLocationBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function sell(
        PosCashSession $session,
        int $cashierId,
        array $lines,
        int $paymentMethodId,
        array $customer = [],
    ): Order {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto a la venta.']);
        }

        return DB::transaction(function () use ($session, $cashierId, $lines, $paymentMethodId, $customer): Order {
            $lockedSession = PosCashSession::query()->lockForUpdate()->with('terminal.warehouse', 'terminal.location')->findOrFail($session->id);

            if (! $lockedSession->isOpen()) {
                throw ValidationException::withMessages(['session' => 'La caja debe estar abierta para registrar ventas.']);
            }

            $terminal = $lockedSession->terminal;
            $warehouse = $terminal?->warehouse;

            if (! $terminal?->is_active || ! $terminal->location?->is_active || ! $warehouse?->is_active) {
                throw ValidationException::withMessages(['terminal' => 'El terminal, establecimiento y almacén deben permanecer activos.']);
            }

            $method = PaymentMethod::query()->whereKey($paymentMethodId)->where('is_enabled', true)->first();
            if (! $method || $method->driver !== 'cash_on_delivery') {
                throw ValidationException::withMessages(['payment_method_id' => 'En esta versión del POS solo se admite un método de cobro inmediato en efectivo habilitado.']);
            }

            $prepared = [];
            $requestedByInventory = [];
            $currency = $lockedSession->currency;
            $subtotal = 0;

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $variantId = filled($line['product_variant_id'] ?? null) ? (int) $line['product_variant_id'] : null;
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($productId < 1 || $quantity < 1) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Cada línea debe tener un producto y una cantidad positiva.']);
                }

                $product = Product::query()->whereKey($productId)->where('status', Product::STATUS_ACTIVE)->first();
                if (! $product) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'El producto no existe o no está activo.']);
                }

                $variant = null;
                if ($variantId === null && $product->variants()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Selecciona una variante para este producto.']);
                }

                if ($variantId !== null) {
                    $variant = ProductVariant::query()->whereKey($variantId)->where('product_id', $product->id)->where('is_active', true)->first();
                    if (! $variant) {
                        throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'La variante no pertenece al producto o está inactiva.']);
                    }
                }

                if ($product->currency !== $currency) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'No se pueden mezclar monedas distintas en una misma sesión de caja.']);
                }

                $price = (int) ($variant?->price_amount ?? $product->price_amount);
                $lineTotal = $price * $quantity;
                $subtotal += $lineTotal;
                $stockKey = $variant ? 'variant:'.$variant->id : 'product:'.$product->id;

                $inventory = Inventory::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('stock_key', $stockKey)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $requestedByInventory[$inventory->id] = ($requestedByInventory[$inventory->id] ?? 0) + $quantity;

                    if ($inventory->available_quantity < $requestedByInventory[$inventory->id]) {
                        throw ValidationException::withMessages(["items.{$index}.quantity" => "Existencias insuficientes en el almacén {$warehouse->name}."]);
                    }
                    $balances = InventoryLocationBalance::query()
                        ->where('inventory_id', $inventory->id)
                        ->lockForUpdate()
                        ->orderBy('warehouse_location_id')
                        ->get();

                    if ($balances->sum('quantity_on_hand') < $requestedByInventory[$inventory->id]) {
                        throw ValidationException::withMessages(["items.{$index}.quantity" => 'Los saldos por ubicación no coinciden con el inventario agregado. Concilia el almacén antes de vender.']);
                    }
                }

                $prepared[] = compact('product', 'variant', 'inventory', 'quantity', 'price', 'lineTotal');
            }

            $order = Order::withoutEvents(fn () => Order::query()->create([
                'number' => 'POS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'channel' => 'pos',
                'pos_terminal_id' => $terminal->id,
                'pos_warehouse_id' => $warehouse->id,
                'pos_cash_session_id' => $lockedSession->id,
                'cashier_id' => $cashierId,
                'public_token_hash' => hash('sha256', Str::random(64)),
                'user_id' => null,
                'customer_name' => trim((string) ($customer['name'] ?? '')) ?: 'Consumidor final',
                'customer_email' => trim((string) ($customer['email'] ?? '')),
                'customer_phone' => trim((string) ($customer['phone'] ?? '')) ?: 'N/A',
                'shipping_address' => 'Venta en establecimiento',
                'shipping_city' => $terminal->location->name,
                'shipping_country' => 'CU',
                'customer_note' => $customer['note'] ?? null,
                'currency' => $currency,
                'subtotal_amount' => $subtotal,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'status' => Order::STATUS_CONFIRMED,
                'payment_status' => 'paid',
                'placed_at' => now(),
                'confirmed_at' => now(),
                'inventory_processed_at' => now(),
            ]));

            foreach ($prepared as $line) {
                $product = $line['product'];
                $variant = $line['variant'];
                $inventory = $line['inventory'];
                $quantity = $line['quantity'];

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'inventory_id' => $inventory?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $quantity,
                    'unit_price_amount' => $line['price'],
                    'line_total_amount' => $line['lineTotal'],
                    'currency' => $currency,
                ]);

                if (! $inventory) {
                    continue;
                }

                $remaining = $quantity;
                $allocations = [];
                foreach ($inventory->locationBalances as $balance) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($remaining, (int) $balance->quantity_on_hand);
                    if ($take <= 0) {
                        continue;
                    }

                    $balance->decrement('quantity_on_hand', $take);
                    $allocations[] = ['location_id' => $balance->warehouse_location_id, 'quantity' => $take];
                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages(['inventory' => 'No fue posible asignar las unidades a ubicaciones físicas.']);
                }

                $inventory->decrement('quantity_on_hand', $quantity);
                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $cashierId,
                    'type' => 'sale',
                    'quantity_delta' => -$quantity,
                    'from_location_id' => count($allocations) === 1 ? $allocations[0]['location_id'] : null,
                    'reason' => 'Venta POS '.$order->number,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'metadata' => ['channel' => 'pos', 'terminal_id' => $terminal->id, 'location_allocations' => $allocations],
                ]);
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'driver' => $method->driver,
                'status' => Payment::STATUS_PAID,
                'amount' => $subtotal,
                'currency' => $currency,
                'reviewed_by' => $cashierId,
                'reviewed_at' => now(),
                'review_note' => 'Cobro registrado en terminal POS.',
                'metadata' => ['channel' => 'pos', 'terminal_id' => $terminal->id],
            ]);

            PaymentEvent::query()->create([
                'payment_id' => $payment->id,
                'from_status' => Payment::STATUS_PENDING,
                'to_status' => Payment::STATUS_PAID,
                'actor_id' => $cashierId,
                'note' => 'Cobro de venta POS.',
                'metadata' => ['channel' => 'pos', 'cash_session_id' => $lockedSession->id],
            ]);

            $lockedSession->movements()->create([
                'payment_id' => $payment->id,
                'user_id' => $cashierId,
                'type' => PosCashMovement::TYPE_SALE,
                'amount' => $subtotal,
                'currency' => $currency,
                'reference' => $order->number,
                'note' => 'Cobro de venta POS.',
            ]);

            return $order->load(['items', 'payments.method', 'posTerminal', 'posCashSession']);
        });
    }
}
