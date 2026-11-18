<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Orders\Services;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Orders\Enums\CooperativeOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\CooperativeOrder;
use App\Domains\Orders\Services\CooperativeOrderResponseService;
use App\Domains\Orders\Services\OrderCreationService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CooperativeOrderResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_admin_can_confirm_own_suborder_and_global_status_is_recalculated(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperative = Cooperative::factory()->create();
        $admin = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperative->getKey(),
        ]);
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '35.00',
            'currency' => 'MAD',
        ]);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $updated = app(CooperativeOrderResponseService::class)
            ->confirmByCooperative($suborder, $admin);

        $this->assertSame(CooperativeOrderStatus::CONFIRMED, $updated->status);
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_cooperative_admin_cannot_decide_for_another_cooperative(): void
    {
        $customer = $this->userWithRole('customer');
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $adminA = $this->userWithRole('cooperative_admin', [
            'cooperative_id' => $cooperativeA->getKey(),
        ]);
        $productB = Product::factory()->forCooperative($cooperativeB)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);

        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Rabat', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $productB->getKey(), 'quantity' => '1']],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(CooperativeOrderResponseService::class)
            ->confirmByCooperative($order->cooperativeOrders->firstOrFail(), $adminA);
    }

    public function test_root_substitution_requires_reason(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();

        $this->expectException(ValidationException::class);
        app(CooperativeOrderResponseService::class)
            ->decideOnBehalf($suborder, $root, true, '   ');
    }

    public function test_root_can_record_a_reasoned_administrative_decision(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $suborder->forceFill(['response_deadline_at' => now()->subMinute()])->save();
        $updated = app(CooperativeOrderResponseService::class)
            ->decideOnBehalf($suborder, $root, false, 'Absence de réponse coopérative');

        $this->assertSame(CooperativeOrderStatus::REFUSED, $updated->status);
        $this->assertSame((string) $root->getKey(), (string) $updated->substituted_by_admin_id);
        $this->assertSame('Absence de réponse coopérative', $updated->substitution_reason);
        $this->assertSame(OrderStatus::REFUSED, $order->fresh()->status);
    }

    public function test_root_can_extend_cooperative_response_deadline_by_at_most_24_hours(): void
    {
        $customer = $this->userWithRole('customer');
        $root = $this->userWithRole('super_admin_root');
        $cooperative = Cooperative::factory()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '20.00',
            'currency' => 'MAD',
        ]);
        $order = app(OrderCreationService::class)->createFromCheckout($customer, [
            'delivery_address_snapshot' => ['city' => 'Fès', 'country' => 'Maroc'],
            'items' => [['product_id' => (string) $product->getKey(), 'quantity' => '1']],
        ]);

        $suborder = $order->cooperativeOrders->firstOrFail();
        $newDeadline = now()->addHours(3);
        $updated = app(CooperativeOrderResponseService::class)
            ->extendResponseDeadline($suborder, $root, $newDeadline, 'Délai supplémentaire accordé');

        $this->assertEquals($newDeadline->timestamp, $updated->response_deadline_at->timestamp);
        $this->assertDatabaseHas('order_status_histories', [
            'subject_type' => 'cooperative_order',
            'subject_id' => $suborder->getKey(),
            'to_status' => CooperativeOrderStatus::AWAITING_COOPERATIVE->value,
        ]);
    }

    private function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleSlug, 'is_active' => true],
        );

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ], $attributes));
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
