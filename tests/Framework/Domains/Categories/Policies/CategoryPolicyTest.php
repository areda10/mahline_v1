<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Policies;

use App\Domains\Categories\Models\Category;
use App\Domains\Categories\Policies\CategoryPolicy;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CategoryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CategoryPolicy();
    }

    public function test_view_any_requires_view_permission(): void
    {
        $user = $this->userWithPermission('categories.view');

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_is_denied_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_view_requires_view_permission(): void
    {
        $user = $this->userWithPermission('categories.view');
        $category = new Category();

        $this->assertTrue($this->policy->view($user, $category));
    }

    public function test_create_requires_create_permission(): void
    {
        $user = $this->userWithPermission('categories.create');

        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_requires_update_permission(): void
    {
        $user = $this->userWithPermission('categories.update');
        $category = new Category();

        $this->assertTrue($this->policy->update($user, $category));
    }

    public function test_delete_requires_delete_permission(): void
    {
        $user = $this->userWithPermission('categories.delete');
        $category = new Category();

        $this->assertTrue($this->policy->delete($user, $category));
    }

    public function test_restore_requires_restore_permission(): void
    {
        $user = $this->userWithPermission('categories.restore');
        $category = new Category();

        $this->assertTrue($this->policy->restore($user, $category));
    }

    public function test_force_delete_requires_force_delete_permission(): void
    {
        $user = $this->userWithPermission('categories.force_delete');
        $category = new Category();

        $this->assertTrue($this->policy->forceDelete($user, $category));
    }

    public function test_create_is_denied_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->create($user));
    }

    private function userWithPermission(string $slug): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Test role',
            'slug' => 'test-role-' . str_replace('.', '-', $slug),
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission->getKey());
        $user->roles()->attach($role->getKey());

        return $user;
    }
    
    public function test_generic_view_permission_does_not_bypass_category_policy(): void
    {
        $user = $this->userWithPermission('view');

        $this->assertFalse(
            Gate::forUser($user)->allows('viewAny', Category::class)
        );
    }

    public function test_category_view_permission_is_respected_by_gate(): void
    {
        $user = $this->userWithPermission('categories.view');

        $this->assertTrue(
            Gate::forUser($user)->allows('viewAny', Category::class)
        );
    }
}