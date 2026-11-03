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
            // Audit
            'audit.view' => 'Consulter le journal d’audit',

            // Cooperatives
            'cooperatives.view' => 'Consulter les coopératives',
            'cooperatives.create' => 'Créer une coopérative',
            'cooperatives.update' => 'Modifier les coopératives',
            'cooperatives.delete' => 'Supprimer les coopératives',

            // User management
            'users.view' => 'Consulter les utilisateurs',
            'users.create' => 'Créer les utilisateurs',
            'users.activate' => 'Activer les utilisateurs',
            'users.deactivate' => 'Désactiver les utilisateurs',
            'users.suspend' => 'Suspendre les utilisateurs',
            'users.archive' => 'Archiver les utilisateurs',
            'users.restore' => 'Restaurer les utilisateurs archivés',
            'users.delete' => 'Supprimer logiquement les utilisateurs',
            'users.restore_deleted' => 'Restaurer les utilisateurs supprimés logiquement',
            'users.assign_super_admin' => 'Affecter un super administrateur à une coopérative',
            'users.unassign_super_admin' => 'Retirer l’affectation d’un super administrateur',

            // Catalogs
            'catalogs.view' => 'Consulter les catalogues',
            'catalogs.create' => 'Créer un catalogue',
            'catalogs.update' => 'Modifier un catalogue',
            'catalogs.delete' => 'Supprimer un catalogue',
            'catalogs.restore' => 'Restaurer un catalogue',
            'catalogs.force_delete' => 'Supprimer définitivement un catalogue',

            // Categories
            'categories.view' => 'Consulter les catégories',
            'categories.create' => 'Créer une catégorie',
            'categories.update' => 'Modifier une catégorie',
            'categories.delete' => 'Supprimer une catégorie',
            'categories.restore' => 'Restaurer une catégorie',
            'categories.force_delete' => 'Supprimer définitivement une catégorie',

            // Products
            'products.view' => 'Consulter les produits',
            'products.create' => 'Créer un produit',
            'products.update' => 'Modifier un produit',
            'products.delete' => 'Supprimer un produit',
            'products.restore' => 'Restaurer un produit',
            'products.force_delete' => 'Supprimer définitivement un produit',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::query()
                ->withTrashed()
                ->updateOrCreate(
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
