<?php

declare(strict_types=1);

namespace App\Domains\Sales\Services;

use App\Domains\Products\Contracts\CheckoutCatalog;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Exceptions\SalesCartVersionConflict;
use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Models\SalesCartItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SalesCartService implements SalesCartManager
{
    private const MAX_QUANTITY_MILLI = 100000;

    public function __construct(private readonly CheckoutCatalog $catalog) {}

    public function createGuestCart(): array
    {
        $token = Str::random(64);
        $cart = SalesCart::query()->create([
            'guest_token_hash' => hash('sha256', $token),
            'version' => 1,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return ['cart' => $cart, 'guest_token' => $token];
    }

    public function getOrCreateCustomerCart(string $customerId): SalesCart
    {
        try {
            return DB::transaction(function () use ($customerId): SalesCart {
                $cart = SalesCart::query()
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->first();

                if ($cart !== null) {
                    return $cart;
                }

                return SalesCart::query()->create([
                    'customer_id' => $customerId,
                    'version' => 1,
                    'last_activity_at' => now(),
                    'expires_at' => null,
                ]);
            });
        } catch (QueryException $exception) {
            // The unique customer_id constraint resolves simultaneous first-cart creation.
            $cart = SalesCart::query()->where('customer_id', $customerId)->first();
            if ($cart !== null) {
                return $cart;
            }

            throw $exception;
        }
    }

    public function findGuestCart(string $guestToken): ?SalesCart
    {
        if ($guestToken === '') {
            return null;
        }

        return SalesCart::query()
            ->where('guest_token_hash', hash('sha256', $guestToken))
            ->whereNull('merged_into_cart_id')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('activeItems')
            ->first();
    }

    public function addItem(
        string $cartId,
        string $productId,
        ?string $variantId,
        string|int|float $quantity,
        int $expectedVersion,
    ): SalesCartItem {
        $quantityMilli = $this->quantityToMilli($quantity);

        return DB::transaction(function () use ($cartId, $productId, $variantId, $quantityMilli, $expectedVersion): SalesCartItem {
            $cart = $this->lockCart($cartId);
            $this->assertVersion($cart, $expectedVersion);

            $product = $this->catalog->resolveForCheckout($productId, $variantId);
            if ($product === null
                || ($product->variantRequested && ! $product->variantFound)
                || (! $product->variantRequested && $product->hasVariants)
                || $product->unitCode === null
                || $product->unitLabel === null
                || $product->effectivePrice === null
                || $product->currency !== 'MAD') {
                throw ValidationException::withMessages([
                    'product_id' => ['Ce produit ou cette variante ne peut pas être ajouté au panier.'],
                ]);
            }

            $fingerprintData = [
                'product_id' => $product->productId,
                'variant_id' => $product->variantId,
                'cooperative_id' => $product->cooperativeId,
                'unit_code' => $product->unitCode,
            ];
            $commercialFingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
            $lineFingerprint = hash('sha256', $cart->getKey().'|'.$commercialFingerprint);
            $priceFingerprint = hash('sha256', $commercialFingerprint.'|'.$product->effectivePrice.'|'.$product->currency);
            $existing = $cart->activeItems()
                ->where('line_fingerprint', $lineFingerprint)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $newQuantity = $this->quantityToMilli((string) $existing->quantity) + $quantityMilli;
                $this->assertQuantityLimit($newQuantity);
                $existing->forceFill(['quantity' => $this->formatQuantity($newQuantity)])->save();
                $item = $existing;
            } else {
                $snapshot = [
                    'product_id' => $product->productId,
                    'name' => $product->productName,
                    'slug' => $product->productSlug,
                    'variant_id' => $product->variantId,
                    'variant_label' => $product->variantLabel,
                    'sku' => $product->variantSku,
                    'unit_code' => $product->unitCode,
                    'unit_label' => $product->unitLabel,
                    'currency' => $product->currency,
                ];
                $item = $cart->items()->create([
                    'product_id' => $product->productId,
                    'product_variant_id' => $product->variantId,
                    'cooperative_id' => $product->cooperativeId,
                    'unit_code' => $product->unitCode,
                    'quantity' => $this->formatQuantity($quantityMilli),
                    'initial_product_snapshot' => $snapshot,
                    'current_product_snapshot' => $snapshot,
                    'initial_unit_price' => $product->effectivePrice,
                    'current_unit_price' => $product->effectivePrice,
                    'accepted_unit_price' => $product->effectivePrice,
                    'accepted_price_fingerprint' => $priceFingerprint,
                    'commercial_fingerprint' => $commercialFingerprint,
                    'line_fingerprint' => $lineFingerprint,
                    'status' => 'active',
                ]);
            }

            $this->touch($cart);

            return $item->refresh();
        });
    }

    public function changeQuantity(
        string $cartId,
        string $itemId,
        string|int|float $quantity,
        int $expectedVersion,
    ): SalesCartItem {
        $quantityMilli = $this->quantityToMilli($quantity);

        return DB::transaction(function () use ($cartId, $itemId, $quantityMilli, $expectedVersion): SalesCartItem {
            $cart = $this->lockCart($cartId);
            $this->assertVersion($cart, $expectedVersion);
            $item = $cart->activeItems()->whereKey($itemId)->lockForUpdate()->first();

            if ($item === null) {
                throw (new ModelNotFoundException)->setModel(SalesCartItem::class, [$itemId]);
            }

            $item->forceFill(['quantity' => $this->formatQuantity($quantityMilli)])->save();
            $this->touch($cart);

            return $item->refresh();
        });
    }

    public function removeItem(string $cartId, string $itemId, int $expectedVersion): void
    {
        DB::transaction(function () use ($cartId, $itemId, $expectedVersion): void {
            $cart = $this->lockCart($cartId);
            $this->assertVersion($cart, $expectedVersion);
            $item = $cart->activeItems()->whereKey($itemId)->lockForUpdate()->first();

            if ($item === null) {
                throw (new ModelNotFoundException)->setModel(SalesCartItem::class, [$itemId]);
            }

            $item->forceFill([
                'status' => 'removed',
                'removed_at' => now(),
                'line_fingerprint' => null,
            ])->save();

            $this->touch($cart);
        });
    }

    public function clear(string $cartId, int $expectedVersion): void
    {
        DB::transaction(function () use ($cartId, $expectedVersion): void {
            $cart = $this->lockCart($cartId);
            $this->assertVersion($cart, $expectedVersion);
            $cart->activeItems()->lockForUpdate()->get()->each(function (SalesCartItem $item): void {
                $item->forceFill([
                    'status' => 'removed',
                    'removed_at' => now(),
                    'line_fingerprint' => null,
                ])->save();
            });
            $this->touch($cart);
        });
    }

    public function mergeGuestCart(string $guestToken, string $customerId): SalesCart
    {
        return DB::transaction(function () use ($guestToken, $customerId): SalesCart {
            $guest = SalesCart::query()
                ->where('guest_token_hash', hash('sha256', $guestToken))
                ->whereNull('customer_id')
                ->whereNull('merged_into_cart_id')
                ->where('expires_at', '>', now())
                ->first();

            if ($guest === null) {
                return $this->getOrCreateCustomerCart($customerId)->load('activeItems');
            }

            $customerCart = $this->getOrCreateCustomerCart($customerId);
            $ids = [(string) $guest->getKey(), (string) $customerCart->getKey()];
            sort($ids, SORT_STRING);
            $locked = SalesCart::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $guest = $locked->get((string) $guest->getKey());
            $customerCart = $locked->get((string) $customerCart->getKey());

            if ($guest === null || $customerCart === null || $guest->merged_into_cart_id !== null) {
                return $customerCart?->load('activeItems') ?? $this->getOrCreateCustomerCart($customerId);
            }

            $guestItems = $guest->activeItems()->lockForUpdate()->get();
            foreach ($guestItems as $guestItem) {
                $target = $customerCart->activeItems()
                    ->where('commercial_fingerprint', $guestItem->commercial_fingerprint)
                    ->lockForUpdate()
                    ->first();

                if ($target !== null) {
                    $sum = $this->quantityToMilli((string) $target->quantity)
                        + $this->quantityToMilli((string) $guestItem->quantity);
                    $this->assertQuantityLimit($sum);
                    $target->forceFill(['quantity' => $this->formatQuantity($sum)])->save();
                    $guestItem->forceFill([
                        'status' => 'merged',
                        'removed_at' => now(),
                        'line_fingerprint' => null,
                    ])->save();
                } else {
                    $guestItem->forceFill([
                        'cart_id' => $customerCart->getKey(),
                        'line_fingerprint' => hash('sha256', $customerCart->getKey().'|'.$guestItem->commercial_fingerprint),
                    ])->save();
                }
            }

            $guest->forceFill(['merged_into_cart_id' => $customerCart->getKey()])->save();
            $this->touch($customerCart);
            $guest->forceFill(['version' => $guest->version + 1, 'last_activity_at' => now()])->save();

            return $customerCart->refresh()->load('activeItems');
        });
    }

    private function lockCart(string $cartId): SalesCart
    {
        return SalesCart::query()->whereKey($cartId)->lockForUpdate()->firstOrFail();
    }

    private function assertVersion(SalesCart $cart, int $expectedVersion): void
    {
        if ($cart->version !== $expectedVersion) {
            throw new SalesCartVersionConflict($cart->version);
        }
    }

    private function touch(SalesCart $cart): void
    {
        $cart->forceFill([
            'version' => $cart->version + 1,
            'last_activity_at' => now(),
            'expires_at' => $cart->customer_id === null ? now()->addDays(30) : null,
        ])->save();
    }

    private function quantityToMilli(string|int|float $quantity): int
    {
        $value = trim((string) $quantity);
        if (! preg_match('/^\d{1,3}(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages([
                'quantity' => ['La quantité doit être positive et comporter au maximum trois décimales.'],
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $milli = ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
        $this->assertQuantityLimit($milli);

        if ($milli <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['La quantité doit être strictement positive.'],
            ]);
        }

        return $milli;
    }

    private function assertQuantityLimit(int $quantityMilli): void
    {
        if ($quantityMilli <= 0 || $quantityMilli > self::MAX_QUANTITY_MILLI) {
            throw ValidationException::withMessages([
                'quantity' => ['La quantité doit être supérieure à zéro et ne pas dépasser 100 unités par ligne.'],
            ]);
        }
    }

    private function formatQuantity(int $quantityMilli): string
    {
        return number_format($quantityMilli / 1000, 3, '.', '');
    }
}
