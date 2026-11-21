<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Exceptions\SalesCartVersionConflict;
use App\Domains\Sales\Models\SalesCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class SalesCartController
{
    private const GUEST_COOKIE = 'mahline_sales_cart';

    private const GUEST_COOKIE_MINUTES = 43200;

    public function __construct(private readonly SalesCartManager $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return $this->cartResponse($cart);
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string', 'size:26'],
            'product_variant_id' => ['nullable', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'lte:100'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->handleMutation(function () use ($request, $data): JsonResponse {
            $cart = $this->resolveCart($request);
            $this->carts->addItem(
                (string) $cart->getKey(),
                $data['product_id'],
                $data['product_variant_id'] ?? null,
                (string) $data['quantity'],
                (int) $data['expected_version'],
            );

            return $this->cartResponse($cart->fresh()->load('activeItems'));
        });
    }

    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->handleMutation(function () use ($request, $itemId, $data): JsonResponse {
            $cart = $this->resolveCart($request);
            $this->carts->changeQuantity(
                (string) $cart->getKey(),
                $itemId,
                (string) $data['quantity'],
                (int) $data['expected_version'],
            );

            return $this->cartResponse($cart->fresh()->load('activeItems'));
        });
    }

    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->handleMutation(function () use ($request, $itemId, $data): JsonResponse {
            $cart = $this->resolveCart($request);
            $this->carts->removeItem(
                (string) $cart->getKey(),
                $itemId,
                (int) $data['expected_version'],
            );

            return $this->cartResponse($cart->fresh()->load('activeItems'));
        });
    }

    public function clear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->handleMutation(function () use ($request, $data): JsonResponse {
            $cart = $this->resolveCart($request);
            $this->carts->clear((string) $cart->getKey(), (int) $data['expected_version']);

            return $this->cartResponse($cart->fresh()->load('activeItems'));
        });
    }

    private function resolveCart(Request $request): SalesCart
    {
        $user = $request->user();
        $guestToken = (string) $request->cookie(self::GUEST_COOKIE, '');

        if ($user !== null) {
            if (! $user instanceof User || ! $user->hasRole('customer')) {
                abort(403);
            }

            $cart = $guestToken !== ''
                ? $this->carts->mergeGuestCart($guestToken, (string) $user->getKey())
                : $this->carts->getOrCreateCustomerCart((string) $user->getKey());

            Cookie::queue(Cookie::forget(self::GUEST_COOKIE));

            return $cart->load('activeItems');
        }

        if ($guestToken !== '') {
            $cart = $this->carts->findGuestCart($guestToken);
            if ($cart !== null) {
                return $cart;
            }
        }

        $created = $this->carts->createGuestCart();
        Cookie::queue(cookie(
            self::GUEST_COOKIE,
            $created['guest_token'],
            self::GUEST_COOKIE_MINUTES,
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax',
        ));

        return $created['cart']->load('activeItems');
    }

    private function cartResponse(SalesCart $cart): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => (string) $cart->getKey(),
                'version' => (int) $cart->version,
                'items' => $cart->activeItems->map(static fn ($item): array => [
                    'id' => (string) $item->getKey(),
                    'product_id' => (string) $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'cooperative_id' => (string) $item->cooperative_id,
                    'quantity' => (string) $item->quantity,
                    'unit_code' => $item->unit_code,
                    'product' => $item->current_product_snapshot,
                    'unit_price' => (string) $item->current_unit_price,
                    'currency' => 'MAD',
                ])->values(),
            ],
        ]);
    }

    private function handleMutation(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (SalesCartVersionConflict $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'sales_cart_version_conflict',
                'current_version' => $exception->currentVersion,
            ], 409);
        }
    }
}
