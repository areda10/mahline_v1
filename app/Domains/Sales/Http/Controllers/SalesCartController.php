<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Exceptions\SalesCartVersionConflict;
use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Services\SalesCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

final class SalesCartController
{
    private const GUEST_COOKIE = 'mahline_sales_cart';

    private const GUEST_COOKIE_MINUTES = 43200;

    public function __construct(
        private readonly SalesCartManager $carts,
        private readonly SalesCheckoutService $checkout,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return $this->cartResponse($cart);
    }

    public function acceptCurrentChanges(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->handleMutation(function () use ($request, $itemId, $data): JsonResponse {
            $cart = $this->resolveCart($request);
            $this->carts->acceptCurrentChanges(
                (string) $cart->getKey(),
                $itemId,
                (int) $data['expected_version'],
            );

            return $this->cartResponse($cart->fresh()->load('activeItems'));
        });
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
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'lte:100'],
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

    public function checkoutPage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('customer'), 403);

        return view('storefront.checkout', ['directCartId' => null]);
    }

    public function startDirectCheckout(Request $request): mixed
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('customer'), 403);

        $data = $request->validate([
            'product_id' => ['required', 'string', 'size:26'],
            'product_variant_id' => ['nullable', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'lte:100'],
        ]);

        $created = $this->carts->createGuestCart();
        $cartId = (string) $created['cart']->getKey();

        $this->carts->addItem(
            $cartId,
            $data['product_id'],
            $data['product_variant_id'] ?? null,
            (string) $data['quantity'],
            1,
        );

        $request->session()->put('sales.direct_checkouts.'.$cartId, $created['guest_token']);

        return redirect()->route('sales.cart.checkout.direct', ['cartId' => $cartId]);
    }

    public function directCheckoutPage(Request $request, string $cartId): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('customer'), 403);
        abort_unless(is_string($request->session()->get('sales.direct_checkouts.'.$cartId)), 404);

        return view('storefront.checkout', ['directCartId' => $cartId]);
    }

    public function checkoutPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRole('customer')) {
            abort(403);
        }

        $directCartId = $request->query('direct_cart_id');
        $directToken = null;
        if ($directCartId !== null) {
            abort_unless(is_string($directCartId) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $directCartId) === 1, 404);
            $directToken = $request->session()->get('sales.direct_checkouts.'.$directCartId);
            abort_unless(is_string($directToken), 404);
            $cartId = $directCartId;
        } else {
            $cartId = (string) $this->resolveCart($request)->getKey();
        }

        if ($directToken !== null) {
            $this->carts->refresh($cartId);
        }

        $preview = $this->checkout->preview((string) $user->getKey(), $cartId, $directToken);
        $cooperativeNames = Cooperative::query()
            ->whereIn('id', collect($preview['groups'] ?? [])->pluck('cooperative_id')->all())
            ->pluck('name', 'id');
        $preview['groups'] = array_map(static function (array $group) use ($cooperativeNames): array {
            $group['cooperative_name'] = (string) ($cooperativeNames[$group['cooperative_id']] ?? 'Coopérative');
            return $group;
        }, $preview['groups'] ?? []);

        $addresses = Address::query()
            ->with('country')
            ->where('user_id', (string) $user->getKey())
            ->whereNull('cooperative_id')
            ->whereHas('country', static fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get()
            ->map(static fn (Address $address): array => [
                'id' => (string) $address->getKey(),
                'label' => trim(implode(', ', array_filter([
                    $address->number,
                    $address->street,
                    $address->city,
                    $address->region,
                    $address->country?->name,
                ]))),
                'is_primary' => (bool) $address->is_primary,
            ])->values();

        return response()->json(['data' => $preview + ['addresses' => $addresses]]);
    }

    public function checkoutConfirm(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRole('customer')) {
            abort(403);
        }

        $data = $request->validate([
            'delivery_address_id' => ['required', 'string', 'size:26'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100'],
            'direct_cart_id' => ['nullable', 'string', 'size:26'],
        ]);

        $directCartId = $data['direct_cart_id'] ?? null;
        $directToken = null;
        if ($directCartId !== null) {
            $directToken = $request->session()->get('sales.direct_checkouts.'.$directCartId);
            abort_unless(is_string($directToken), 404);
            $cartId = $directCartId;
        } else {
            $cartId = (string) $this->resolveCart($request)->getKey();
        }

        if ($directToken !== null) {
            $this->carts->refresh($cartId);
        }

        $result = $this->checkout->confirm(
            (string) $user->getKey(),
            $cartId,
            $data['delivery_address_id'],
            $data['idempotency_key'],
            $directToken,
        );

        if ($directCartId !== null) {
            $request->session()->forget('sales.direct_checkouts.'.$directCartId);
        }

        return response()->json(['data' => $result]);
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

            return $this->carts->refresh((string) $cart->getKey());
        }

        if ($guestToken !== '') {
            $cart = $this->carts->findGuestCart($guestToken);
            if ($cart !== null) {
                return $this->carts->refresh((string) $cart->getKey());
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

        return $this->carts->refresh((string) $created['cart']->getKey());
    }

    private function cartResponse(SalesCart $cart): JsonResponse
    {
        $cooperativeNames = Cooperative::query()
            ->whereIn('id', $cart->activeItems->pluck('cooperative_id')->unique())
            ->pluck('name', 'id');

        return response()->json([
            'data' => [
                'id' => (string) $cart->getKey(),
                'version' => (int) $cart->version,
                'items' => $cart->activeItems->map(static fn ($item): array => [
                    'id' => (string) $item->getKey(),
                    'product_id' => (string) $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'cooperative_id' => (string) $item->cooperative_id,
                    'cooperative_name' => (string) ($cooperativeNames[(string) $item->cooperative_id] ?? 'Coopérative'),
                    'quantity' => (string) $item->quantity,
                    'unit_code' => $item->unit_code,
                    'product' => $item->current_product_snapshot,
                    'unit_price' => (string) $item->current_unit_price,
                    'currency' => 'MAD',
                    'status' => $item->status,
                    'unavailable_reason' => $item->unavailable_reason,
                    'accepted_unit_price' => $item->accepted_unit_price === null ? null : (string) $item->accepted_unit_price,
                    'requires_acceptance' => in_array($item->unavailable_reason, [
                        'price_change_pending',
                        'commercial_change_pending',
                        'price_and_commercial_change_pending',
                    ], true),
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
