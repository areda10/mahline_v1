# MAHLINE V1 — Audit du dépôt et suivi d'implémentation

- **Date de l'audit :** 17 novembre 2026
- **Dépôt :** areda10/mahline_v1
- **Branche auditée :** feature/Marketplace
- **HEAD vérifié :** 184d10db20305fa60cdc6b1835f6a6a51bd5aa1d
- **Déploiement antérieur :** aucun (confirmation du propriétaire du projet)
- **Périmètre :** domaines actuellement présents dans app/Domains

## 1. État structurel constaté

- 624 fichiers suivis dans l'arbre Git vérifié.
- 38 migrations dans database/migrations.
- 10 domaines présents : Audit, Catalog, Categories, Cooperatives, Countries, Dashboard, Identity, Localization, Media et Products.
- Aucun workflow GitHub Actions n'est présent dans .github/workflows.
- Aucun statut de commit ni résultat de workflow n'est disponible pour le HEAD vérifié.
- Les domaines Orders, Facturation/Finance, Livraison/Delivery et Inventory ne sont pas présents ; leurs migrations ne sont pas créées dans cette phase.

Le dépôt n'ayant jamais été déployé, les migrations initiales peuvent être consolidées sans préserver une compatibilité avec une base de production existante. Les migrations ne doivent toutefois pas être exécutées de façon destructive sans contrôle de l'environnement.

## 2. Audit par domaine

| Domaine | État constaté dans le dépôt | Contrôles / réserves |
|---|---|---|
| Identity | Cycle de vie des comptes, permissions par rôle, invitations, sessions multiples, tokens API, changement d'e-mail et historique de mots de passe présents | Les tests de permission et de cycle de vie doivent être exécutés. Les modifications de profil simples ne doivent pas révoquer les sessions/tokens ; les changements d'e-mail, de rôle ou d'affectation les révoquent. |
| Cooperatives | Modèle, Policy, services de création, modification, statuts, suppression logique et restauration présents | L'affectation unique d'un super_admin par coopérative est contrainte par la base. Les tests d'intégration MySQL restent à exécuter. |
| Localization | Propriété XOR utilisateur/coopérative, Maroc V1, carnet client de 5 adresses, adresse principale, suppression logique et restauration par lot présents | Les services verrouillent le propriétaire ; les contrôles de Policy sont rejoués après verrouillage. Les scénarios de restauration et les contraintes MySQL doivent être testés. |
| Countries | Le seeder de référence active le Maroc (MA) | Les écritures d'adresses V1 imposent le pays Maroc depuis le service. |
| Catalog | CRUD, associations produits/catalogues, contrôle de périmètre coopératif et génération PDF présents | **Blocage dépendance :** composer.json exige dompdf/dompdf:^3.1, mais le paquet est absent de composer.lock. Le verrou doit être régénéré avant installation/test fiable. Le PDF reste réservé à Root et super_admin dans son périmètre. |
| Categories | Catégories globales/locales, hiérarchie, suppression de branche globale avec détachement des enfants locaux et désactivation des produits orphelins présents | Les opérations de création, modification, suppression, détachement et désactivation induite écrivent maintenant des événements Audit lorsqu'un acteur est fourni. |
| Products | Produits, variantes, associations catégories/catalogues, suppression logique et réactivation conditionnée par une catégorie active présents | Les opérations de cycle de vie et de modification des associations écrivent maintenant des événements Audit lorsqu'un acteur est fourni. |
| Media | Upload, validation, dérivés d'images, médias publics/privés, primaire, ordre, suppression logique et nettoyage configuré présents | L'index unique conditionnel d'un média principal est déclaré une seule fois. La durée de rétention physique n'est pas considérée comme une règle métier V1 validée ; la configuration reste non imposée par défaut. |
| Audit | Journal append-only, hash chain SHA-256, tête verrouillée et triggers MySQL de protection présents | La consultation est réservée à Root avec audit.view. La couverture d'audit de chaque opération doit continuer d'être vérifiée domaine par domaine. |
| Dashboard | Contrôleurs et vues de tableaux de bord présents | Domaine de lecture/agrégation ; vérifier les permissions et le périmètre des requêtes lors de la validation fonctionnelle. |

## 3. Corrections réalisées pendant cette passe

- Séparation de users.create (Root uniquement) et users.invite (Root et super_admin dans son périmètre).
- Suppression de users.activate de la matrice super_admin ; l'activation manuelle reste réservée à Root.
- Alignement des contrôleurs d'édition de fiche sur users.update et des services sur les rôles et périmètres V1.
- Conservation des sessions et tokens lors d'une modification ordinaire de profil ; révocation uniquement lors d'un changement d'e-mail, de rôle ou d'affectation.
- Vérification de l'autorisation de création d'adresse de coopérative avec le contexte de la coopérative affectée.
- Correction du cas où un cooperative_admin doit pouvoir recréer une adresse manquante dans sa coopérative sans recevoir le droit de créer une coopérative.
- Verrouillage transactionnel du propriétaire pendant la suppression du carnet d'adresses.
- Utilisation explicite de primaryAddress() pour les affichages, et de addresses() pour détecter une entrée existante à modifier.
- Ajout d'événements Audit attribués à l'acteur pour Catalog, Categories et Products, y compris les changements d'associations et les désactivations induites par la suppression de catégories.
- Mise à jour des tests de régression associés et de la documentation de référence Audit.

## 4. Blocages avant validation locale

1. Régénérer composer.lock pour inclure dompdf/dompdf:^3.1 et ses dépendances transitives. Commande à exécuter depuis le dépôt local : composer update dompdf/dompdf --with-dependencies.
2. Installer les dépendances avec composer install.
3. Exécuter les migrations sur la base de test MySQL 8.4 dédiée, puis la suite complète php artisan test.
4. Exécuter php vendor/bin/pint --test.
5. Vérifier les triggers MySQL, les index générés, les contraintes de propriété et les tests concurrents sur les adresses et la chaîne Audit.
6. Vérifier les scénarios HTTP de création, modification, suppression et restauration dans les espaces Root, super_admin, cooperative_admin, user et customer.

**Aucun test n'est déclaré vert dans cet audit.** Les modifications sont écrites sur feature/Marketplace, mais l'exécution PHP/PHPUnit/Pint n'a pas été effectuée par cette passe. Aucune fusion vers main n'a été réalisée.
