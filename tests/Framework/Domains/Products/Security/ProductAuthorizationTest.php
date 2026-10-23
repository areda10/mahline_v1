<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Products\Security;

use App\Domains\Products\Policies\ProductPolicy;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ProductPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(ProductPolicy::class);
    }

    public function test_user_from_another_cooperative_cannot_view_product(): void
    {
        $product = Product::factory()->create();

        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('isAuthorized')
            ->andReturn(true);

        $user->shouldReceive('hasPermission')
            ->with('products.view')
            ->andReturn(true);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin_root'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['cooperative_admin', 'user'])
            ->andReturn(true);

        $user->cooperative_id = '01OTHERCOOPERATIVE0000000000';

        $this->assertFalse(
            $this->policy->view($user, $product),
        );
    }

    public function test_user_without_view_permission_cannot_view_product(): void
    {
        $product = Product::factory()->create();

        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('isAuthorized')
            ->andReturn(true);

        $user->shouldReceive('hasPermission')
            ->with('products.view')
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin_root'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['cooperative_admin', 'user'])
            ->andReturn(true);

        $user->cooperative_id = $product->cooperative_id;

        $this->assertFalse(
            $this->policy->view($user, $product),
        );
    }

    public function test_user_from_another_cooperative_cannot_update_product(): void
    {
        $product = Product::factory()->create();

        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('isAuthorized')
            ->andReturn(true);

        $user->shouldReceive('hasPermission')
            ->with('products.update')
            ->andReturn(true);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin_root'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['cooperative_admin', 'user'])
            ->andReturn(true);

        $user->cooperative_id = '01OTHERCOOPERATIVE0000000000';

        $this->assertFalse(
            $this->policy->update($user, $product),
        );
    }

    public function test_user_from_another_cooperative_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $user = Mockery::mock(User::class)->makePartial();

        $user->shouldReceive('isAuthorized')
            ->andReturn(true);

        $user->shouldReceive('hasPermission')
            ->with('products.delete')
            ->andReturn(true);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin_root'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['super_admin'])
            ->andReturn(false);

        $user->shouldReceive('hasAnyRole')
            ->with(['cooperative_admin', 'user'])
            ->andReturn(true);

        $user->cooperative_id = '01OTHERCOOPERATIVE0000000000';

        $this->assertFalse(
            $this->policy->delete($user, $product),
        );
    }
}
