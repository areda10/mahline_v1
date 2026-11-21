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
            'users.update' => 'Modifier les fiches et adresses des utilisateurs dans le périmètre autorisé',
            'users.create' => 'Créer les comptes internes (réservé à super_admin_root)',
            'users.invite' => 'Envoyer et renvoyer les invitations de compte',
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

            // Inventory
            'inventory.stock.view' => 'Consulter l’état du stock dans le périmètre autorisé',
            'inventory.stock.initialize' => 'Initialiser le stock',
            'inventory.stock.adjust' => 'Effectuer des ajustements de stock',
            'inventory.stock.receive' => 'Enregistrer des entrées de stock',
            'inventory.movements.view' => 'Consulter les mouvements de stock',
            'inventory.reservations.create' => 'Créer les réservations de stock au checkout',
            'inventory.reservations.consume' => 'Consommer les réservations lors de la confirmation',
            'inventory.reservations.release' => 'Libérer les réservations autorisées',
            'inventory.quarantine.manage' => 'Gérer les quarantaines et inspections',
            'inventory.quarantine.authorize_sale' => 'Autoriser la vente de quantités en quarantaine',
            'inventory.quarantine.revoke_sale' => 'Révoquer une autorisation de vente en quarantaine',
            'inventory.count.create' => 'Créer une session d’inventaire physique',
            'inventory.count.perform' => 'Effectuer les comptages physiques',
            'inventory.count.validate' => 'Valider les comptages physiques',
            'inventory.count.cancel' => 'Annuler logiquement une session d’inventaire',
            'inventory.threshold.manage' => 'Configurer les seuils de stock',
            'inventory.reports.view' => 'Consulter les rapports Inventory',
            'inventory.reports.export' => 'Exporter les rapports Inventory en PDF',
            'inventory.security_reports.create' => 'Signaler un risque de sécurité Inventory',
            'inventory.security_reports.view' => 'Consulter les signalements de sécurité dans le périmètre autorisé',
            'inventory.security_reports.view_own' => 'Consulter le suivi de ses propres signalements',
            'inventory.security_reports.manage' => 'Traiter les signalements de sécurité',
            'inventory.security_reports.close' => 'Clôturer les signalements de sécurité',
            'inventory.security_reports.reopen' => 'Réouvrir les signalements de sécurité',
            'orders.safety_holds.resolve' => 'Décider de la reprise ou de l’annulation après un blocage de sécurité',
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
