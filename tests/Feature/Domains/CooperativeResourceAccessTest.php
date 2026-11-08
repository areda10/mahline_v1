<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_admin_cannot_open_catalog_category_or_product_pages_of_another_cooperative(): void
    {
        $ownCooperative = Cooperative::factory()->active()->create();
        $foreignCooperative = Cooperative::factory()->active()->create();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'cooperative_id' => $ownCooperative->getKey(),
        ]);

        $role = Role::query()->create([
            'name' => 'Cooperative Admin',
            'slug' => 'cooperative_admin',
            'is_active' => true,
        ]);

        $permissions = collect([
            'cooperatives.view',
            'catalogs.view',
            'categories.view',
            'products.view',
        ])->map(fn (string $slug): Permission => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'is_active' => true,
            ],
        ));

        $role->permissions()->attach($permissions->pluck('id')->all());
        $user->roles()->attach($role->getKey());

        $this->actingAs($user);
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);

        $cooperativeId = $foreignCooperative->getKey();

        $this->get(route('backoffice.catalogs.index', [
            'cooperative' => $cooperativeId,
        ]))->assertForbidden();

        $this->get(route('backoffice.categories.index', [
            'cooperative' => $cooperativeId,
        ]))->assertForbidden();

        $this->get(route('backoffice.products.index', [
            'cooperative' => $cooperativeId,
        ]))->assertForbidden();
    }
}
