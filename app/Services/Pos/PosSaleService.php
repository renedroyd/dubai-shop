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
use App\Models\PosDiscountEvent;
use App\Services\Pos\PosPricingService;
use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        array $paymentDetails = [],
    ): Order {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto a la venta.']);
        }

        return DB::transaction(function () use ($session, $cashierId, $lines, $paymentMethodId, $customer, $paymentDetails): Order {
            $lockedSession = PosCashSession::query()->lockForUpdate()->with('terminal.warehouse', 'terminal.location')->findOrFail($session->id);

            if (! $lockedSession->isOpen()) {
                throw ValidationException::withMessages(['session' => 'La caja debe estar abierta para registrar ventas.']);
            }

            $terminal = $lockedSession->terminal;
            $warehouse = $terminal?->warehouse;

            $cashier = User::query()->findOrFail($cashierId);
            if (! $warehouse || ! $cashier->hasWarehousePermission($warehouse->id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para vender desde este almacén.');
            }

            $assignedCashierIds = array_map('intval', $terminal->assigned_cashier_ids ?? []);
            if ($assignedCashierIds !== [] && ! in_array($cashierId, $assignedCashierIds, true)) {
                throw new AuthorizationException('No estás asignado a este terminal POS.');
            }

            if (! $terminal?->is_active || ! $terminal->location?->is_active || ! $warehouse?->is_active) {
                throw ValidationException::withMessages(['terminal' => 'El terminal, establecimiento y almacén deben permanecer activos.']);
            }

            $isMixed = (bool) ($paymentDetails['mixed'] ?? false);
            $method = PaymentMethod::query()->whereKey($paymentMethodId)->where('is_enabled', true)->first();
            if (! $method || ! in_array($method->driver, ['cash_on_delivery', 'zelle_manual'], true)) {
                throw ValidationException::withMessages(['payment_method_id' => 'Selecciona un método de pago POS habilitado.']);
            }
            $requiresReview = $method->driver === 'zelle_manual' || $isMixed;
            if ($requiresReview) {
                if ($isMixed && $method->driver !== 'cash_on_delivery') {
                    throw ValidationException::withMessages(['payment_method_id' => 'Para un pago mixto selecciona efectivo como método base.']);
                }
                $proof = $paymentDetails['proof'] ?? null;
                if ($lockedSession->currency !== 'USD') {
                    throw ValidationException::withMessages(['payment_method_id' => 'Zelle solo está disponible para ventas en USD.']);
                }
                if (! filled($paymentDetails['reference'] ?? null) || ! filled($paymentDetails['sender_name'] ?? null)
                    || ! filled($paymentDetails['transfer_date'] ?? null) || ! is_object($proof) || ! method_exists($proof, 'getRealPath')) {
                    throw ValidationException::withMessages(['paymentDetails' => 'Para registrar Zelle indica remitente, referencia, fecha y adjunta el comprobante.']);
                }
            }

            $cashReceived = (int) ($paymentDetails['cash_received_amount'] ?? 0);
            if ($method->driver === 'cash_on_delivery') {
                $cashReceived = $cashReceived > 0 ? $cashReceived : 0;
            }

            $customerUserId = filled($customer['user_id'] ?? null) ? (int) $customer['user_id'] : null;
            $customerUser = $customerUserId
                ? User::query()->whereKey($customerUserId)->where('is_admin', false)->where('admin_access_enabled', false)->lockForUpdate()->first()
                : null;
            if ($customerUserId !== null && ! $customerUser) {
                throw ValidationException::withMessages(['customer.user_id' => 'El cliente seleccionado no existe o no es una cuenta de cliente válida.']);
            }

            $prepared = [];
            $requestedByInventory = [];
            $currency = $lockedSession->currency;
            $subtotal = 0;
            $netSubtotal = 0;
            $itemDiscountTotal = 0;

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $variantId = filled($line['product_variant_id'] ?? null) ? (int) $line['product_variant_id'] : null;
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($productId < 1 || $quantity < 1) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Cada línea debe tener un producto y una cantidad positiva.']);
                }

                $product = Product::query()->whereKey($productId)->where('status', Product::STATUS_ACTIVE)->lockForUpdate()->first();
                if (! $product) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'El producto no existe o no está activo.']);
                }

                $variant = null;
                if ($variantId === null && $product->variants()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'Selecciona una variante para este producto.']);
                }

                if ($variantId !== null) {
                    $variant = ProductVariant::query()->whereKey($variantId)->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->first();
                    if (! $variant) {
                        throw ValidationException::withMessages(["items.{$index}.product_variant_id" => 'La variante no pertenece al producto o está inactiva.']);
                    }
                }

                if ($product->currency !== $currency) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'No se pueden mezclar monedas distintas en una misma sesión de caja.']);
                }

                $basePrice = (int) ($variant?->price_amount ?? $product->price_amount);
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

                $pricing = app(PosPricingService::class)->priceLine($product, $variant, $inventory, $customerUser, $cashier, $warehouse->id, $currency);
                $price = $pricing['unit_price_amount'];
                $lineTotal = $price * $quantity;
                $originalLineTotal = $basePrice * $quantity;
                $lineDiscount = $pricing['discount_amount'] * $quantity;
                $subtotal += $originalLineTotal;
                $netSubtotal += $lineTotal;
                $itemDiscountTotal += $lineDiscount;
                $prepared[] = [
                    'product' => $product, 'variant' => $variant, 'inventory' => $inventory, 'quantity' => $quantity,
                    'price' => $price, 'originalPrice' => $basePrice, 'lineTotal' => $lineTotal,
                    'discountAmount' => $lineDiscount, 'unitCost' => $inventory?->acquisition_cost_amount,
                    'rule' => $pricing['rule'], 'approvedBy' => $pricing['approved_by'],
                ];
            }

            $orderPricing = app(PosPricingService::class)->priceOrderDiscount($netSubtotal, $prepared, $customerUser, $cashier, $warehouse->id, $currency);
            $orderDiscountAmount = $orderPricing['discount_amount'];
            $eligibleSubtotal = max(0, $netSubtotal - $orderDiscountAmount);
            $redemption = null;
            $requestedLoyaltyPoints = (int) ($paymentDetails['loyalty_points'] ?? 0);
            $loyaltyDiscountAmount = 0;

            if ($requestedLoyaltyPoints > 0) {
                if (! $customerUser) {
                    throw ValidationException::withMessages(['loyaltyPointsToRedeem' => 'Vincula una cuenta de cliente para canjear puntos.']);
                }

                $redemption = app(PosLoyaltyService::class)->prepareRedemption(
                    $customerUser->id,
                    $currency,
                    $eligibleSubtotal,
                    $requestedLoyaltyPoints,
                );
                $loyaltyDiscountAmount = (int) $redemption['discount_amount'];
            }

            $total = $eligibleSubtotal - $loyaltyDiscountAmount;
            $discountTotal = $itemDiscountTotal + $orderDiscountAmount + $loyaltyDiscountAmount;
            if ($total <= 0) {
                throw ValidationException::withMessages(['discounts' => 'El total de la venta debe ser mayor que cero.']);
            }

            $cashAmount = $isMixed ? (int) ($paymentDetails['cash_amount'] ?? 0) : $total;
            $zelleAmount = $isMixed ? (int) ($paymentDetails['zelle_amount'] ?? 0) : 0;
            if ($isMixed) {
                if ($currency !== 'USD') {
                    throw ValidationException::withMessages(['paymentDetails' => 'Los pagos mixtos con Zelle solo están disponibles en USD.']);
                }
                if ($cashAmount <= 0 || $zelleAmount <= 0 || $cashAmount + $zelleAmount !== $total) {
                    throw ValidationException::withMessages(['paymentDetails' => 'Los importes de efectivo y Zelle deben ser positivos y sumar exactamente el total de la venta.']);
                }
                if ($cashReceived === 0) {
                    $cashReceived = $cashAmount;
                }
                if ($cashReceived < $cashAmount) {
                    throw ValidationException::withMessages(['cash_received_amount' => 'El efectivo recibido no puede ser inferior a la parte asignada al efectivo.']);
                }
            } else {
                if ($method->driver === 'cash_on_delivery' && $cashReceived > 0 && $cashReceived < $total) {
                    throw ValidationException::withMessages(['cash_received_amount' => 'El efectivo recibido no puede ser inferior al total de la venta.']);
                }
                if ($method->driver === 'cash_on_delivery' && $cashReceived === 0) {
                    $cashReceived = $total;
                }
            }
            $cashCollectedAmount = $method->driver === 'cash_on_delivery' ? $cashAmount : 0;
            $cashLimit = $terminal->cashLimitFor($currency);
            if ($cashCollectedAmount > 0 && $cashLimit !== null) {
                $cashTotals = $lockedSession->movements()
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_in", [
                        PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_SALE,
                    ])
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_out", [
                        PosCashMovement::TYPE_CASH_OUT, PosCashMovement::TYPE_REFUND,
                    ])->first();
                $availableCash = (int) $lockedSession->opening_cash_amount + (int) $cashTotals->cash_in - (int) $cashTotals->cash_out;

                if ($availableCash + $cashCollectedAmount > $cashLimit) {
                    throw ValidationException::withMessages(['paymentDetails' => 'La venta supera el límite de efectivo configurado para este terminal. Registra una salida de efectivo autorizada antes de continuar.']);
                }
            }

            $zelleMethod = $isMixed
                ? PaymentMethod::query()->where('driver', 'zelle_manual')->where('is_enabled', true)->first()
                : null;
            if ($isMixed && ! $zelleMethod) {
                throw ValidationException::withMessages(['paymentDetails' => 'El método Zelle manual no está habilitado.']);
            }

            $order = Order::withoutEvents(fn () => Order::query()->create([
                'number' => 'POS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'channel' => 'pos',
                'pos_terminal_id' => $terminal->id,
                'pos_warehouse_id' => $warehouse->id,
                'pos_cash_session_id' => $lockedSession->id,
                'cashier_id' => $cashierId,
                'public_token_hash' => hash('sha256', Str::random(64)),
                'user_id' => $customerUser?->id,
                'customer_name' => $customerUser?->name ?: (trim((string) ($customer['name'] ?? '')) ?: 'Consumidor final'),
                'customer_email' => $customerUser?->email ?: trim((string) ($customer['email'] ?? '')),
                'customer_phone' => trim((string) ($customer['phone'] ?? '')) ?: 'N/A',
                'shipping_address' => 'Venta en establecimiento',
                'shipping_city' => $terminal->location->name,
                'shipping_country' => 'CU',
                'customer_note' => $customer['note'] ?? null,
                'currency' => $currency,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discountTotal,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $total,
                'status' => $requiresReview ? Order::STATUS_PENDING : Order::STATUS_CONFIRMED,
                'payment_status' => $requiresReview ? 'awaiting_review' : 'paid',
                'placed_at' => now(),
                'confirmed_at' => $requiresReview ? null : now(),
                'inventory_processed_at' => $requiresReview ? null : now(),
            ]));

            if ($redemption !== null) {
                app(PosLoyaltyService::class)->recordRedemption($order, $redemption);
            }

            if ($orderDiscountAmount > 0 && $orderPricing['rule']) {
                PosDiscountEvent::query()->create([
                    'order_id' => $order->id, 'pos_price_rule_id' => $orderPricing['rule']->id,
                    'applied_by' => $cashierId, 'approved_by' => $orderPricing['approved_by'],
                    'scope' => 'order', 'discount_amount' => $orderDiscountAmount,
                    'rule_snapshot' => ['name' => $orderPricing['rule']->name, 'rule_type' => $orderPricing['rule']->rule_type, 'value_amount' => $orderPricing['rule']->value_amount, 'currency' => $orderPricing['rule']->currency, 'warehouse_id' => $orderPricing['rule']->warehouse_id, 'customer_id' => $orderPricing['rule']->customer_id],
                ]);
            }

            foreach ($prepared as $line) {
                $product = $line['product'];
                $variant = $line['variant'];
                $inventory = $line['inventory'];
                $quantity = $line['quantity'];

                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'inventory_id' => $inventory?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $quantity,
                    'unit_price_amount' => $line['price'],
                    'original_unit_price_amount' => $line['originalPrice'],
                    'line_total_amount' => $line['lineTotal'],
                    'discount_amount' => $line['discountAmount'],
                    'unit_cost_amount' => $line['unitCost'],
                    'pos_price_rule_id' => $line['rule']?->id,
                    'currency' => $currency,
                ]);

                if ($line['discountAmount'] > 0 && $line['rule']) {
                    PosDiscountEvent::query()->create([
                        'order_id' => $order->id, 'order_item_id' => $orderItem->id,
                        'pos_price_rule_id' => $line['rule']->id, 'applied_by' => $cashierId,
                        'approved_by' => $line['approvedBy'], 'scope' => $line['rule']->scope,
                        'discount_amount' => $line['discountAmount'],
                        'rule_snapshot' => ['name' => $line['rule']->name, 'rule_type' => $line['rule']->rule_type, 'value_amount' => $line['rule']->value_amount, 'currency' => $line['rule']->currency, 'warehouse_id' => $line['rule']->warehouse_id, 'customer_id' => $line['rule']->customer_id],
                    ]);
                }

                if (! $inventory) {
                    continue;
                }

                if ($requiresReview) {
                    $inventory->increment('quantity_reserved', $quantity);
                    InventoryMovement::query()->create([
                        'inventory_id' => $inventory->id,
                        'user_id' => $cashierId,
                        'type' => 'reservation',
                        'quantity_delta' => -$quantity,
                        'reason' => 'Reserva POS pendiente de validar Zelle '.$order->number,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'metadata' => ['channel' => 'pos', 'terminal_id' => $terminal->id, 'order_item_id' => $orderItem->id],
                    ]);
                } else {
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
                    'metadata' => ['channel' => 'pos', 'terminal_id' => $terminal->id, 'order_item_id' => $orderItem->id, 'location_allocations' => $allocations],
                ]);
                }
            }

            $proofPath = $requiresReview ? Storage::disk('local')->putFile('payment-proofs/pos', $paymentDetails['proof']) : null;

            if ($isMixed) {
                $cashPayment = Payment::query()->create([
                    'order_id' => $order->id,
                    'payment_method_id' => $method->id,
                    'driver' => 'cash_on_delivery',
                    'status' => Payment::STATUS_PAID,
                    'amount' => $cashAmount,
                    'currency' => $currency,
                    'reviewed_by' => $cashierId,
                    'reviewed_at' => now(),
                    'review_note' => 'Componente en efectivo de un pago mixto POS.',
                    'metadata' => [
                        'channel' => 'pos', 'terminal_id' => $terminal->id, 'cash_session_id' => $lockedSession->id,
                        'cash_received_amount' => $cashReceived, 'change_amount' => max(0, $cashReceived - $cashAmount),
                        'mixed_payment' => true,
                    ],
                ]);
                $cashPayment->events()->create([
                    'from_status' => null, 'to_status' => Payment::STATUS_PAID, 'actor_id' => $cashierId,
                    'note' => 'Componente en efectivo registrado en POS.', 'metadata' => ['mixed_payment' => true],
                ]);

                $zellePayment = Payment::query()->create([
                    'order_id' => $order->id,
                    'payment_method_id' => $zelleMethod->id,
                    'driver' => 'zelle_manual',
                    'status' => Payment::STATUS_AWAITING_REVIEW,
                    'amount' => $zelleAmount,
                    'currency' => $currency,
                    'reference' => trim((string) $paymentDetails['reference']),
                    'proof_path' => $proofPath,
                    'metadata' => [
                        'channel' => 'pos', 'terminal_id' => $terminal->id, 'cash_session_id' => $lockedSession->id,
                        'sender_name' => trim((string) $paymentDetails['sender_name']),
                        'transfer_date' => $paymentDetails['transfer_date'], 'mixed_payment' => true,
                    ],
                ]);
                $zellePayment->events()->create([
                    'from_status' => null, 'to_status' => Payment::STATUS_AWAITING_REVIEW, 'actor_id' => $cashierId,
                    'note' => 'Componente Zelle pendiente de validación en pago mixto POS.',
                    'metadata' => ['mixed_payment' => true, 'cash_payment_id' => $cashPayment->id],
                ]);
                $lockedSession->movements()->create([
                    'payment_id' => $cashPayment->id, 'user_id' => $cashierId, 'type' => PosCashMovement::TYPE_SALE,
                    'amount' => $cashAmount, 'currency' => $currency, 'reference' => $order->number,
                    'note' => 'Componente en efectivo de pago mixto POS.',
                    'metadata' => ['mixed_payment' => true, 'zelle_pending_amount' => $zelleAmount],
                ]);
            } else {
                $payment = Payment::query()->create([
                    'order_id' => $order->id, 'payment_method_id' => $method->id, 'driver' => $method->driver,
                    'status' => $requiresReview ? Payment::STATUS_AWAITING_REVIEW : Payment::STATUS_PAID,
                    'amount' => $total, 'currency' => $currency,
                    'reference' => $requiresReview ? trim((string) $paymentDetails['reference']) : null,
                    'proof_path' => $proofPath,
                    'reviewed_by' => $requiresReview ? null : $cashierId,
                    'reviewed_at' => $requiresReview ? null : now(),
                    'review_note' => $requiresReview ? null : 'Cobro registrado en terminal POS.',
                    'metadata' => [
                        'channel' => 'pos', 'terminal_id' => $terminal->id, 'cash_session_id' => $lockedSession->id,
                        'sender_name' => $requiresReview ? trim((string) $paymentDetails['sender_name']) : null,
                        'transfer_date' => $requiresReview ? $paymentDetails['transfer_date'] : null,
                        'cash_received_amount' => $requiresReview ? null : $cashReceived,
                        'change_amount' => $requiresReview ? null : max(0, $cashReceived - $total),
                    ],
                ]);
                $payment->events()->create([
                    'from_status' => null,
                    'to_status' => $requiresReview ? Payment::STATUS_AWAITING_REVIEW : Payment::STATUS_PAID,
                    'actor_id' => $cashierId,
                    'note' => $requiresReview ? 'Pago Zelle POS pendiente de validación.' : 'Cobro de venta POS.',
                    'metadata' => ['channel' => 'pos', 'cash_session_id' => $lockedSession->id],
                ]);
                if (! $requiresReview) {
                    $lockedSession->movements()->create([
                        'payment_id' => $payment->id, 'user_id' => $cashierId, 'type' => PosCashMovement::TYPE_SALE,
                        'amount' => $total, 'currency' => $currency, 'reference' => $order->number,
                        'note' => 'Cobro de venta POS.',
                    ]);
                }
            }

            if (! $requiresReview) {
                app(PosLoyaltyService::class)->awardForPaidPosOrder($order);
            }

            return $order->load(['items', 'payments.method', 'posTerminal', 'posCashSession']);
        });
    }
}
