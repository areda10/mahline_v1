<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Inventory\Contracts\InventoryCheckoutAvailabilityReader;
use App\Domains\Inventory\DTOs\InventoryCheckoutLineData;
use App\Domains\Inventory\Enums\InventoryStockableType;
use App\Domains\Localization\Contracts\CheckoutAddressReader;
use App\Domains\Orders\Contracts\OrderCheckoutCreator;
use App\Domains\Orders\DTOs\OrderCheckoutData;
use App\Domains\Products\Contracts\CheckoutCatalog;
use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Models\SalesCartItem;
use App\Domains\Sales\Models\SalesCheckoutAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SalesCheckoutService
{
    public function __construct(
        private readonly CheckoutCatalog $catalog,
        private readonly InventoryCheckoutAvailabilityReader $inventory,
        private readonly CheckoutAddressReader $addresses,
        private readonly OrderCheckoutCreator $orders,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $customerId, string $cartId): array
    {
        $cart = SalesCart::query()
            ->whereKey($cartId)
            ->where('customer_id', $customerId)
            ->with('activeItems')
            ->firstOrFail();

        return $this->availability($cart->activeItems);
    }

    /** @return array<string, mixed> */
    public function confirm(
        string $customerId,
        string $cartId,
        string $addressId,
        string $idempotencyKey,
    ): array {
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Une clé d’idempotence valide est obligatoire (100 caractères maximum).'],
            ]);
        }

        return DB::transaction(function () use ($customerId, $cartId, $addressId, $key): array {
            $cart = SalesCart::query()
                ->whereKey($cartId)
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $keyHash = hash('sha256', $key);
            $previous = SalesCheckoutAttempt::query()
                ->where('customer_id', $customerId)
                ->where('idempotency_key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                if ((string) $previous->cart_id !== $cartId
                    || (string) $previous->delivery_address_id !== $addressId) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Cette clé a déjà été utilisée pour un autre panier ou une autre adresse.'],
                    ]);
                }

                return $previous->response_snapshot;
            }

            $cart->load(['activeItems' => static fn ($query) => $query->lockForUpdate()]);
            $items = $cart->activeItems;
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Le panier ne contient aucun article commandable.'],
                ]);
            }

            $availability = $this->availability($items);
            $excludedIds = $availability['unavailable_cooperative_ids'];
            $eligibleIds = $availability['available_cooperative_ids'];

            if ($eligibleIds === []) {
                throw ValidationException::withMessages([
                    'inventory' => ['Aucune coopérative ne dispose actuellement de tous les articles demandés. Votre panier a été conservé.'],
                ]);
            }

            $address = $this->addresses->snapshotForCustomer($customerId, $addressId)->toArray();
            $orderItems = [];
            $orderedCartItems = [];

            foreach ($items as $item) {
                if (! in_array((string) $item->cooperative_id, $eligibleIds, true)) {
                    continue;
                }

                $orderItems[] = [
                    'product_id' => (string) $item->product_id,
                    'product_variant_id' => $item->product_variant_id === null ? null : (string) $item->product_variant_id,
                    'cooperative_id' => (string) $item->cooperative_id,
                    'accepted_unit_price' => (string) ($item->accepted_unit_price ?? $item->initial_unit_price),
                    'accepted_commercial_fingerprint' => (string) ($item->accepted_commercial_fingerprint ?? $item->commercial_fingerprint),
                    'quantity' => (string) $item->quantity,
                ];
                $orderedCartItems[] = $item;
            }

            $order = $this->orders->create(new OrderCheckoutData(
                customerId: $customerId,
                idempotencyKey: $key,
                deliveryAddressSnapshot: $address,
                items: $orderItems,
            ));

            $orderedIds = $order->cooperativeOrders
                ->map(static fn ($suborder): string => (string) $suborder->cooperative_id)
                ->values()
                ->all();

            $orderExcludedIds = array_values(array_unique(array_merge(
                $excludedIds,
                array_map('strval', $order->inventory_excluded_cooperative_ids ?? []),
            )));
            $orderedIds = array_values(array_diff($orderedIds, $orderExcludedIds));

            if ($orderedIds === []) {
                throw ValidationException::withMessages([
                    'inventory' => ['Le stock a changé pendant la confirmation. Aucune commande n’a été créée et le panier est conservé.'],
                ]);
            }

            foreach ($orderedCartItems as $item) {
                if (! in_array((string) $item->cooperative_id, $orderedIds, true)) {
                    continue;
                }

                $item->forceFill([
                    'status' => 'ordered',
                    'removed_at' => now(),
                    'line_fingerprint' => null,
                ])->save();
            }

            $cart->forceFill([
                'version' => (int) $cart->version + 1,
                'last_activity_at' => now(),
            ])->save();

            $excludedIds = array_values(array_unique(array_merge(
                $orderExcludedIds,
                array_diff(
                    $items->pluck('cooperative_id')->map(static fn ($id): string => (string) $id)->unique()->all(),
                    $orderedIds,
                ),
            )));

            $response = [
                'order_id' => (string) $order->getKey(),
                'order_reference' => (string) $order->reference,
                'ordered_cooperative_ids' => $orderedIds,
                'excluded_cooperative_ids' => $excludedIds,
                'remaining_cart_item_ids' => $cart->activeItems()->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
                'message' => $excludedIds === []
                    ? 'Votre commande a été créée.'
                    : 'Votre commande a été créée pour les coopératives disponibles. Les autres articles restent dans votre panier.',
            ];

            SalesCheckoutAttempt::query()->create([
                'customer_id' => $customerId,
                'cart_id' => $cartId,
                'delivery_address_id' => $addressId,
                'idempotency_key_hash' => $keyHash,
                'order_id' => (string) $order->getKey(),
                'ordered_cooperative_ids' => $orderedIds,
                'excluded_cooperative_ids' => $excludedIds,
                'response_snapshot' => $response,
            ]);

            return $response;
        }, 3);
    }

    /**
     * @param  iterable<SalesCartItem>  $items
     * @return array<string, mixed>
     */
    private function availability(iterable $items): array
    {
        $linesByCooperative = [];
        $invalidCooperatives = [];

        foreach ($items as $index => $item) {
            $cooperativeId = (string) $item->cooperative_id;
            if ($item->unavailable_reason !== null) {
                $invalidCooperatives[$cooperativeId] = true;

                continue;
            }

            $product = $this->catalog->resolveForCheckout(
                (string) $item->product_id,
                $item->product_variant_id === null ? null : (string) $item->product_variant_id,
            );

            if ($product === null
                || $product->productIsDeleted
                || ! $product->productIsActive
                || ($product->variantRequested && (! $product->variantFound || $product->variantIsDeleted || ! $product->variantIsActive))
                || (! $product->variantRequested && $product->hasVariants)
                || $product->unitCode === null
                || $product->currency !== 'MAD') {
                $invalidCooperatives[$cooperativeId] = true;

                continue;
            }

            $linesByCooperative[$cooperativeId][] = [
                'item' => $item,
                'line' => new InventoryCheckoutLineData(
                    lineKey: (string) $item->getKey(),
                    cooperativeId: $cooperativeId,
                    stockableType: $product->variantId === null ? InventoryStockableType::PRODUCT : InventoryStockableType::VARIANT,
                    stockableId: (string) ($product->variantId ?? $product->productId),
                    quantity: (string) $item->quantity,
                    unitCode: $product->unitCode,
                ),
            ];
        }

        $validLines = [];
        foreach ($linesByCooperative as $cooperativeId => $lines) {
            if (isset($invalidCooperatives[$cooperativeId])) {
                continue;
            }
            foreach ($lines as $entry) {
                $validLines[] = $entry['line'];
            }
        }

        $stockUnavailable = [];
        if ($validLines !== []) {
            $preview = $this->inventory->preview($validLines);
            $stockUnavailable = $preview->unavailableCooperativeIds();
        }

        $allCooperativeIds = [];
        foreach ($items as $item) {
            $allCooperativeIds[(string) $item->cooperative_id] = true;
        }

        $unavailable = array_values(array_unique(array_merge(
            array_keys($invalidCooperatives),
            $stockUnavailable,
        )));
        $available = array_values(array_diff(array_keys($allCooperativeIds), $unavailable));

        return [
            'available_cooperative_ids' => $available,
            'unavailable_cooperative_ids' => $unavailable,
            'cooperatives' => array_map(
                static fn (string $id): array => [
                    'cooperative_id' => $id,
                    'available' => in_array($id, $available, true),
                ],
                array_keys($allCooperativeIds),
            ),
        ];
    }
}
