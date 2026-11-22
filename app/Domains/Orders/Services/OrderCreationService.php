<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Cooperatives\Contracts\CooperativeStatusReader;
use App\Domains\Inventory\Contracts\InventoryCheckoutAvailabilityReader;
use App\Domains\Inventory\DTOs\InventoryCheckoutLineData;
use App\Domains\Inventory\DTOs\InventoryCheckoutPreview;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderItemStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Products\Contracts\CheckoutCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrderCreationService
{
    public function __construct(
        private readonly CheckoutCatalog $products,
        private readonly InventoryCheckoutAvailabilityReader $inventoryAvailability,
        private readonly CooperativeStatusReader $cooperatives,
    ) {}

    /**
     * Return the stock preview that must be shown to the customer before finalization.
     * The preview never exposes physical or reserved quantities.
     *
     * @param  array{items: array<int, array{product_id: string, product_variant_id?: string|null, quantity: string|int|float}>}  $checkout
     */
    public function previewFromCheckout(array $checkout): InventoryCheckoutPreview
    {
        $items = $checkout['items'] ?? null;
        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'checkout' => ['Au moins un article est obligatoire.'],
            ]);
        }

        $lines = [];
        foreach ($items as $index => $input) {
            if (! is_array($input) || empty($input['product_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['Le produit est obligatoire.'],
                ]);
            }

            $productData = $this->products->resolveForCheckout(
                (string) $input['product_id'],
                empty($input['product_variant_id']) ? null : (string) $input['product_variant_id'],
            );

            if ($productData === null
                || ($productData->variantRequested && ! $productData->variantFound)
                || (! $productData->variantRequested && $productData->hasVariants)
                || $productData->unitCode === null) {
                throw ValidationException::withMessages([
                    "items.{$index}" => ['Le produit, sa variante ou son unité de stock est indisponible.'],
                ]);
            }

            $quantity = $this->quantityToMilli($input['quantity'] ?? null, $index);
            $variant = $productData->variantId !== null;
            $lines[] = new InventoryCheckoutLineData(
                lineKey: (string) $index,
                cooperativeId: $productData->cooperativeId,
                stockableType: $variant ? InventoryStockableType::VARIANT : InventoryStockableType::PRODUCT,
                stockableId: $variant ? $productData->variantId : $productData->productId,
                quantity: $this->formatQuantity($quantity),
                unitCode: $productData->unitCode,
            );
        }

        return $this->inventoryAvailability->preview($lines);
    }

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
    public function createFromCheckout(
        User $customer,
        array $checkout,
        ?string $idempotencyKey = null,
        ?string $acceptedInventoryFingerprint = null,
    ): Order {
        $address = $checkout['delivery_address_snapshot'] ?? null;
        $items = $checkout['items'] ?? null;

        if (! is_array($address) || $address === [] || ! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'checkout' => ['Une adresse de livraison et au moins un article sont obligatoires.'],
            ]);
        }

        $idempotencyKey = trim($idempotencyKey ?? (string) ($checkout['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'checkout.idempotency_key' => ['Une clé d’idempotence stable est obligatoire pour finaliser le checkout.'],
            ]);
        }
        if (strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'checkout.idempotency_key' => ['La clé d’idempotence du checkout est trop longue.'],
            ]);
        }

        $checkoutRequestHash = hash('sha256', json_encode([
            'customer_id' => (string) $customer->getKey(),
            'address' => $address,
            'items' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($customer, $address, $items, $idempotencyKey, $checkoutRequestHash): Order {
            $existingOrder = Order::query()
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingOrder !== null) {
                if (! hash_equals((string) $existingOrder->checkout_request_hash, $checkoutRequestHash)) {
                    throw ValidationException::withMessages([
                        'checkout.idempotency_key' => ['Cette clé a déjà été utilisée pour un autre checkout.'],
                    ]);
                }

                return $existingOrder->load(['cooperativeOrders.items']);
            }

            $prepared = [];
            $cooperativeTotals = [];
            $forcedUnavailableCooperativeIds = [];

            foreach ($items as $index => $input) {
                if (! is_array($input) || empty($input['product_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Le produit est obligatoire.'],
                    ]);
                }

                $requestedCooperativeId = (string) ($input['cooperative_id'] ?? '');
                $productData = $this->products->resolveForCheckout(
                    (string) $input['product_id'],
                    empty($input['product_variant_id']) ? null : (string) $input['product_variant_id'],
                );

                if ($productData === null) {
                    if ($requestedCooperativeId !== '') {
                        $forcedUnavailableCooperativeIds[] = $requestedCooperativeId;
                        continue;
                    }

                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Ce produit est indisponible ou son prix doit être défini.'],
                    ]);
                }

                $cooperativeId = $productData->cooperativeId;
                if ($requestedCooperativeId !== '' && ! hash_equals($requestedCooperativeId, $cooperativeId)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Le produit ne correspond plus à la coopérative enregistrée dans le panier.'],
                    ]);
                }

                $cooperative = $this->cooperatives->findStatus($cooperativeId);
                if ($cooperative === null || ! $cooperative->isActive || $cooperative->isDeleted) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                if ($productData->variantRequested && ! $productData->variantFound) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                if (! $productData->variantRequested && $productData->hasVariants) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                if ($productData->variantFound && $productData->variantId !== null && $productData->hasParentUnit) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                if ($productData->unitCode === null || $productData->unitLabel === null) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                if ($productData->currency !== 'MAD') {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

                $quantityMilli = $this->quantityToMilli($input['quantity'] ?? null, $index);
                $unitPrice = $productData->effectivePrice;
                if ($unitPrice === null) {
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }
                $unitPriceCents = $this->moneyToCents((string) $unitPrice);

                $acceptedPrice = $input['accepted_unit_price'] ?? null;
                if ($acceptedPrice !== null && $unitPriceCents > $this->moneyToCents((string) $acceptedPrice)) {
                    // A price increase requires explicit customer acceptance in Sales.
                    $forcedUnavailableCooperativeIds[] = $cooperativeId;
                    continue;
                }

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
                $snapshot = [
                    'product_id' => $productData->productId,
                    'product_name' => $productData->productName,
                    'product_slug' => $productData->productSlug,
                    'variant_id' => $productData->variantId,
                    'variant_label' => $productData->variantLabel,
                    'sku' => $productData->variantSku,
                    'unit' => $productData->unitCode,
                    'unit_label' => $productData->unitLabel,
                    'quantity' => $this->formatQuantity($quantityMilli),
                    'unit_price' => $this->formatMoney($unitPriceCents),
                    'currency' => 'MAD',
                ];

                $prepared[] = [
                    'line_key' => (string) $index,
                    'cooperative_id' => $cooperativeId,
                    'product_id' => $productData->productId,
                    'product_variant_id' => $productData->variantId,
                    'snapshot' => $snapshot,
                    'quantity' => $this->formatQuantity($quantityMilli),
                    'unit' => $productData->unitCode,
                    'unit_price' => $this->formatMoney($unitPriceCents),
                    'line_cents' => $lineCents,
                ];

                $cooperativeTotals[$cooperativeId] = ($cooperativeTotals[$cooperativeId] ?? 0) + $lineCents;
            }

            $forcedUnavailableCooperativeIds = array_values(array_unique($forcedUnavailableCooperativeIds));
            $prepared = array_values(array_filter(
                $prepared,
                static fn (array $line): bool => ! in_array($line['cooperative_id'], $forcedUnavailableCooperativeIds, true),
            ));

            if ($prepared === []) {
                throw ValidationException::withMessages([
                    'inventory_preview' => ['Aucun article ne peut être commandé après vérification des statuts et des prix.'],
                ]);
            }

            $previewLines = array_map(
                fn (array $line): InventoryCheckoutLineData => new InventoryCheckoutLineData(
                    lineKey: $line['line_key'],
                    cooperativeId: $line['cooperative_id'],
                    stockableType: $line['product_variant_id'] === null ? InventoryStockableType::PRODUCT : InventoryStockableType::VARIANT,
                    stockableId: $line['product_variant_id'] ?? $line['product_id'],
                    quantity: $line['quantity'],
                    unitCode: $line['unit'],
                ),
                $prepared,
            );
            $preview = $this->inventoryAvailability->preview($previewLines);
            $unavailableCooperativeIds = array_values(array_unique(array_merge(
                $forcedUnavailableCooperativeIds,
                $preview->unavailableCooperativeIds(),
            )));

            // Availability is intentionally recalculated inside this transaction.
            // The customer preview is informational: a stock change does not force
            // a second confirmation. Unavailable cooperatives are excluded as a whole.
            $prepared = array_values(array_filter(
                $prepared,
                static fn (array $line): bool => ! in_array($line['cooperative_id'], $unavailableCooperativeIds, true),
            ));
            if ($prepared === []) {
                throw ValidationException::withMessages([
                    'inventory_preview' => ['Aucun article ne peut être réservé.'],
                ]);
            }

            $cooperativeTotals = [];
            foreach ($prepared as $line) {
                $cooperativeTotals[$line['cooperative_id']] = ($cooperativeTotals[$line['cooperative_id']] ?? 0) + $line['line_cents'];
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
                'checkout_idempotency_key' => $idempotencyKey,
                'checkout_request_hash' => $checkoutRequestHash,
                'inventory_excluded_cooperative_ids' => $unavailableCooperativeIds,
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
                    'response_deadline_at' => now()->addHours(12),
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
