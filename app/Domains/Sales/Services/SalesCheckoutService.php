<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Cooperatives\Contracts\CooperativeStatusReader;
use App\Domains\Delivery\Contracts\DeliveryQuoteProvider;
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
        private readonly CooperativeStatusReader $cooperatives,
        private readonly InventoryCheckoutAvailabilityReader $inventory,
        private readonly CheckoutAddressReader $addresses,
        private readonly DeliveryQuoteProvider $deliveryQuotes,
        private readonly OrderCheckoutCreator $orders,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $customerId, string $cartId, ?string $directToken = null): array
    {
        $cartQuery = SalesCart::query()->whereKey($cartId);
        if ($directToken === null) {
            $cartQuery->where('customer_id', $customerId);
        } else {
            $cartQuery->whereNull('customer_id')
                ->where('guest_token_hash', hash('sha256', $directToken))
                ->whereNull('merged_into_cart_id')
                ->where('expires_at', '>', now());
        }
        $cart = $cartQuery->with('activeItems')->firstOrFail();

        $availability = $this->availability($cart->activeItems);
        $items = $cart->activeItems;

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Le panier ne contient aucun article.'],
            ]);
        }

        $cooperativeIds = $availability['available_cooperative_ids'];
        $excludedCooperativeIds = $availability['stock_unavailable_cooperative_ids'];

        if ($availability['invalid_cooperative_ids'] !== []
            || $availability['inactive_cooperative_ids'] !== []
            || $cooperativeIds === []) {
            return $availability + [
                'items' => $items->map(static fn (SalesCartItem $item): array => [
                    'id' => (string) $item->getKey(),
                    'product' => $item->current_product_snapshot,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) ($item->accepted_unit_price ?? $item->current_unit_price),
                    'cooperative_id' => (string) $item->cooperative_id,
                    'unavailable_reason' => $item->unavailable_reason,
                ])->values()->all(),
                'excluded_cooperative_ids' => [],
                'excluded_groups' => [],
                'delivery_quotes' => [],
                'subtotal' => '0.00',
                'delivery_total' => '0.00',
                'grand_total' => '0.00',
                'checkout_allowed' => false,
            ];
        }

        $quotes = $this->deliveryQuotes->quoteForCooperatives($cooperativeIds);
        $quotesByCooperative = [];
        foreach ($quotes as $quote) {
            $quote->assertUsable();
            $quotesByCooperative[$quote->cooperativeId] = $quote->snapshot();
        }

        if (count($quotesByCooperative) !== count($cooperativeIds)
            || array_diff($cooperativeIds, array_keys($quotesByCooperative)) !== []) {
            throw ValidationException::withMessages([
                'delivery' => ['Un devis de livraison valide est requis pour chaque coopérative.'],
            ]);
        }

        $subtotalCents = 0;
        $groups = [];
        foreach ($items as $item) {
            $cooperativeId = (string) $item->cooperative_id;
            if (! in_array($cooperativeId, $cooperativeIds, true)) {
                continue;
            }

            $unitPrice = (string) ($item->accepted_unit_price ?? $item->current_unit_price);
            $lineCents = (int) round(((float) $unitPrice) * ((float) $item->quantity) * 100);
            $subtotalCents += $lineCents;
            $groups[$cooperativeId] ??= [
                'cooperative_id' => $cooperativeId,
                'items' => [],
                'subtotal' => '0.00',
                'delivery' => $quotesByCooperative[$cooperativeId]['amount'],
                'delivery_quote' => $quotesByCooperative[$cooperativeId],
            ];
            $groups[$cooperativeId]['items'][] = [
                'id' => (string) $item->getKey(),
                'product' => $item->current_product_snapshot,
                'quantity' => (string) $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => number_format($lineCents / 100, 2, '.', ''),
            ];
            $groups[$cooperativeId]['subtotal'] = number_format(
                ((float) $groups[$cooperativeId]['subtotal']) + ($lineCents / 100),
                2,
                '.',
                '',
            );
        }

        $deliveryCents = array_sum(array_map(
            static fn (array $quote): int => (int) round(((float) $quote['amount']) * 100),
            array_values($quotesByCooperative),
        ));

        $excludedGroups = $items
            ->filter(static fn (SalesCartItem $item): bool => in_array(
                (string) $item->cooperative_id,
                $excludedCooperativeIds,
                true,
            ))
            ->groupBy(static fn (SalesCartItem $item): string => (string) $item->cooperative_id)
            ->map(static fn ($cooperativeItems, string $cooperativeId): array => [
                'cooperative_id' => $cooperativeId,
                'items' => $cooperativeItems->map(static fn (SalesCartItem $item): array => [
                    'id' => (string) $item->getKey(),
                    'product' => $item->current_product_snapshot,
                    'quantity' => (string) $item->quantity,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return $availability + [
            'groups' => array_values($groups),
            'excluded_cooperative_ids' => $excludedCooperativeIds,
            'excluded_groups' => $excludedGroups,
            'delivery_quotes' => array_values($quotesByCooperative),
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'delivery_total' => number_format($deliveryCents / 100, 2, '.', ''),
            'grand_total' => number_format(($subtotalCents + $deliveryCents) / 100, 2, '.', ''),
            'checkout_allowed' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(
        string $customerId,
        string $cartId,
        string $addressId,
        string $idempotencyKey,
        ?string $directToken = null,
    ): array {
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Une clé d’idempotence valide est obligatoire (100 caractères maximum).'],
            ]);
        }

        return DB::transaction(function () use ($customerId, $cartId, $addressId, $key, $directToken): array {
            $cartQuery = SalesCart::query()->whereKey($cartId);
            if ($directToken === null) {
                $cartQuery->where('customer_id', $customerId);
            } else {
                $cartQuery->whereNull('customer_id')
                    ->where('guest_token_hash', hash('sha256', $directToken))
                    ->whereNull('merged_into_cart_id')
                    ->where('expires_at', '>', now());
            }
            $cart = $cartQuery->lockForUpdate()->firstOrFail();

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
            $excludedIds = $availability['stock_unavailable_cooperative_ids'];
            $eligibleIds = $availability['available_cooperative_ids'];

            if ($availability['inactive_cooperative_ids'] !== []) {
                throw ValidationException::withMessages([
                    'cooperatives' => ['Une coopérative de votre panier est inactive ou fermée. Retirez manuellement les lignes concernées avant de poursuivre. Votre panier a été conservé.'],
                ]);
            }

            if ($availability['invalid_cooperative_ids'] !== []) {
                throw ValidationException::withMessages([
                    'checkout' => ['Au moins un produit ou une ligne de votre panier n’est plus commandable. Corrigez ou retirez les lignes concernées avant de poursuivre. Votre panier a été conservé.'],
                ]);
            }

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

            $cooperativeIds = array_values(array_unique(array_column($orderItems, 'cooperative_id')));
            $quotes = $this->deliveryQuotes->quoteForCooperatives($cooperativeIds);
            $quotesByCooperative = [];
            foreach ($quotes as $quote) {
                $quote->assertUsable();
                $quotesByCooperative[$quote->cooperativeId] = $quote->snapshot();
            }
            if (count($quotesByCooperative) !== count($cooperativeIds)
                || array_diff($cooperativeIds, array_keys($quotesByCooperative)) !== []) {
                throw ValidationException::withMessages([
                    'delivery' => ['Un devis de livraison valide est requis pour chaque coopérative.'],
                ]);
            }

            $order = $this->orders->create(new OrderCheckoutData(
                customerId: $customerId,
                idempotencyKey: $key,
                deliveryAddressSnapshot: $address,
                items: $orderItems,
                deliveryQuotes: array_values($quotesByCooperative),
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

            $expectedOrderedIds = array_values(array_diff($cooperativeIds, $orderExcludedIds));
            if (array_diff($expectedOrderedIds, $orderedIds) !== []) {
                throw ValidationException::withMessages([
                    'inventory' => ['La création des sous-commandes ne correspond pas au résultat de la vérification du stock. La transaction est annulée et votre panier est conservé.'],
                ]);
            }

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
        $inactiveCooperatives = [];

        foreach ($items as $index => $item) {
            $cooperativeId = (string) $item->cooperative_id;
            $cooperative = $this->cooperatives->findStatus($cooperativeId);
            if ($cooperative === null || ! $cooperative->isActive || $cooperative->isDeleted) {
                $invalidCooperatives[$cooperativeId] = true;
                $inactiveCooperatives[$cooperativeId] = true;

                continue;
            }

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
            'invalid_cooperative_ids' => array_keys($invalidCooperatives),
            'stock_unavailable_cooperative_ids' => $stockUnavailable,
            'inactive_cooperative_ids' => array_keys($inactiveCooperatives),
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
