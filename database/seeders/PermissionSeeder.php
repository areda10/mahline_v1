<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Authorization\Models\Permission;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'catalogs.view' => 'Consulter les catalogues',
            'catalogs.create' => 'Créer un catalogue',
            'catalogs.update' => 'Modifier un catalogue',
            'catalogs.delete' => 'Supprimer un catalogue',
            'catalogs.restore' => 'Restaurer un catalogue',
            'catalogs.force_delete' => 'Supprimer définitivement un catalogue',

            'categories.view' => 'Consulter les catégories',
            'categories.create' => 'Créer une catégorie',
            'categories.update' => 'Modifier une catégorie',
            'categories.delete' => 'Supprimer une catégorie',
            'categories.restore' => 'Restaurer une catégorie',
            'categories.force_delete' => 'Supprimer définitivement une catégorie',

            'products.view' => 'Consulter les produits',
            'products.create' => 'Créer un produit',
            'products.update' => 'Modifier un produit',
            'products.delete' => 'Supprimer un produit',
            'products.restore' => 'Restaurer un produit',
            'products.force_delete' => 'Supprimer définitivement un produit',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::query()->withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $name,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
        }
    }
}
