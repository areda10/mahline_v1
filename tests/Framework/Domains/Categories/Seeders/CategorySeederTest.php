<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Categories\Seeders;

use App\Domains\Categories\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_four_global_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $expected = [
            'bien-etre',
            'fruits-secs',
            'miels-amlou-confitures',
            'epices',
        ];

        foreach ($expected as $slug) {
            $this->assertDatabaseHas('categories', [
                'slug' => $slug,
                'cooperative_id' => null,
                'is_active' => true,
            ]);
        }

        $this->assertSame(
            4,
            Category::query()
                ->whereNull('cooperative_id')
                ->whereIn('slug', $expected)
                ->count(),
        );
    }

    public function test_running_the_seeder_twice_does_not_duplicate_categories(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(
            4,
            Category::query()
                ->whereNull('cooperative_id')
                ->whereIn('slug', [
                    'bien-etre',
                    'fruits-secs',
                    'miels-amlou-confitures',
                    'epices',
                ])
                ->count(),
        );
    }
}