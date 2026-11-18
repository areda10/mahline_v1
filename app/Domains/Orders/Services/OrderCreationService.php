<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

final class OrderCreationService
{
    /**
     * Persist a complete multi-cooperative order from checkout intent.
     *
     * The caller must provide validated customer/address data and item identifiers
     * plus quantities. Product ownership, activity and prices are always reloaded
     * from the database; client-submitted prices are never trusted.
     *
     * @param array{
     *     delivery_address_snapshot: array<string, mixed>,
     *     items: array<int, array{product_id: string, product_variant_id?: string|null, quantity: string|int|float}>
     * } $checkout
     */
    public function createFromCheckout(User $customer, array $checkout): Order
    {
        $address = $checkout['delivery_address_snapshot'] ?? null;
        $items = $checkout['items'] ?? null;

        if (! is_array($address) || $address === [] || ! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'checkout' => ['Une adresse de livraison et au moins un article sont obligatoires.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $address, $items): Order {
            $prepared = [];
            $cooperativeTotals = [];

            foreach ($items as $index => $input) {
                if (! is_array($input) || empty($input['product_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Le produit est obligatoire.'],
                    ]);
                }

                $product = Product::query()
                    ->whereKey((string) $input['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Ce produit est indisponible ou son prix doit être défini.'],
                    ]);
                }

                $variant = null;
                if (! empty($input['product_variant_id'])) {
                    $variant = ProductVariant::query()
                        ->whereKey((string) $input['product_variant_id'])
                        ->where('product_id', $product->getKey())
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($variant === null) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_variant_id" => ['Cette variante est indisponible ou ne correspond pas au produit.'],
                        ]);
                    }
                }

                if ($variant === null && $product->variants()->exists()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => ['Une variante doit être sélectionnée pour ce produit.'],
                    ]);
                }

                if ($variant !== null && $product->unit_id !== null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit" => ['Un produit avec variantes ne peut pas conserver une unité parent.'],
                    ]);
                }

                // L'unité de commande provient exclusivement de Products :
                // produit simple ou variante sélectionnée. La valeur cliente est ignorée.
                $stockUnit = $variant !== null ? $variant->unit : $product->unit;
                if ($stockUnit === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit" => ['L’unité de stock du produit ou de sa variante doit être définie avant la commande.'],
                    ]);
                }

                if ((string) $product->currency !== 'MAD') {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Seuls les produits en MAD sont commandables en V1.'],
                    ]);
                }

                $quantityMilli = $this->quantityToMilli($input['quantity'] ?? null, $index);
                $unitPrice = $variant?->price ?? $product->price;
                if ($unitPrice === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Le prix du produit ou de sa variante doit être défini.'],
                    ]);
                }
                $unitPriceCents = $this->moneyToCents((string) $unitPrice);

                if ($unitPriceCents <= 0 || $quantityMilli <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => ['Le prix et la quantité doivent être strictement positifs.'],
                    ]);
                }

                if ($unitPriceCents > intdiv(PHP_INT_MAX - 500, $quantityMilli)) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => ['Le montant de cette ligne dépasse la limite autorisée.'],
                    ]);
                }

                $lineCents = intdiv(($unitPriceCents * $quantityMilli) + 500, 1000);
                $cooperativeId = (string) $product->cooperative_id;

                $snapshot = [
                    'product_id' => (string) $product->getKey(),
                    'product_name' => $product->name,
                    'product_slug' => $product->slug,
                    'variant_id' => $variant ? (string) $variant->getKey() : null,
                    'variant_label' => $variant?->label,
                    'sku' => $variant?->sku,
                    'unit' => $stockUnit->code,
                    'unit_label' => $stockUnit->label,
                    'quantity' => $this->formatQuantity($quantityMilli),
                    'unit_price' => $this->formatMoney($unitPriceCents),
                    'currency' => 'MAD',
                ];

                $prepared[] = [
                    'cooperative_id' => $cooperativeId,
                    'product_id' => (string) $product->getKey(),
                    'product_variant_id' => $variant ? (string) $variant->getKey() : null,
                    'snapshot' => $snapshot,
                    'quantity' => $this->formatQuantity($quantityMilli),
                    'unit' => $stockUnit->code,
                    'unit_price' => $this->formatMoney($unitPriceCents),
                    'line_cents' => $lineCents,
                ];

                $cooperativeTotals[$cooperativeId] = ($cooperativeTotals[$cooperativeId] ?? 0) + $lineCents;
            }

            $itemsTotalCents = array_sum($cooperativeTotals);
            $order = Order::query()->create([
                'customer_id' => (string) $customer->getKey(),
                'reference' => 'ORD-'.Str::ulid(),
                'status' => OrderStatus::AWAITING_COOPERATIVES,
                'currency' => 'MAD',
                'items_subtotal' => $this->formatMoney($itemsTotalCents),
                'delivery_total' => '0.00',
                'grand_total' => $this->formatMoney($itemsTotalCents),
                'delivery_address_snapshot' => $address,
                'placed_at' => now(),
            ]);
            $order->forceFill(['created_by' => (string) $customer->getKey()])->save();

            $this->recordHistory(
                'order',
                (string) $order->getKey(),
                null,
                OrderStatus::AWAITING_COOPERATIVES->value,
                $customer,
                'customer',
            );

            $cooperativeOrders = [];
            foreach ($cooperativeTotals as $cooperativeId => $subtotalCents) {
                $cooperativeOrder = CooperativeOrder::query()->create([
                    'order_id' => (string) $order->getKey(),
                    'cooperative_id' => $cooperativeId,
                    'reference' => 'CO-'.Str::ulid(),
                    'status' => CooperativeOrderStatus::AWAITING_COOPERATIVE,
                    'items_subtotal' => $this->formatMoney($subtotalCents),
                    'delivery_fee' => '0.00',
                    'grand_total' => $this->formatMoney($subtotalCents),
                ]);
                $cooperativeOrder->forceFill(['created_by' => (string) $customer->getKey()])->save();

                $cooperativeOrders[$cooperativeId] = $cooperativeOrder;
                $this->recordHistory(
                    'cooperative_order',
                    (string) $cooperativeOrder->getKey(),
                    null,
                    CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
                    $customer,
                    'customer',
                );
            }

            foreach ($prepared as $line) {
                $orderItem = OrderItem::query()->create([
                    'cooperative_order_id' => (string) $cooperativeOrders[$line['cooperative_id']]->getKey(),
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'product_snapshot' => $line['snapshot'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => '0.000',
                    'tax_amount' => '0.00',
                    'line_subtotal' => $this->formatMoney($line['line_cents']),
                    'line_total' => $this->formatMoney($line['line_cents']),
                    'status' => OrderItemStatus::ACTIVE,
                ]);
                $orderItem->forceFill(['created_by' => (string) $customer->getKey()])->save();
            }

            return $order->load(['cooperativeOrders.items']);
        }, 3);
    }

    private function recordHistory(
        string $subjectType,
        string $subjectId,
        ?string $fromStatus,
        string $toStatus,
        User $actor,
        string $actorRole,
    ): void {
        OrderStatusHistory::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => (string) $actor->getKey(),
            'actor_role' => $actorRole,
            'reason' => 'Création de la commande au checkout.',
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    }

    private function moneyToCents(string $amount): int
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages([
                'price' => ['Le prix du produit est invalide.'],
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function quantityToMilli(mixed $quantity, int $index): int
    {
        $value = (string) $quantity;
        if (! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => ['La quantité doit être positive et comporter au maximum trois décimales.'],
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function formatMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function formatQuantity(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }
}
