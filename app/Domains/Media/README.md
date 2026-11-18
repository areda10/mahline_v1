# MAHLINE Media — Spécification technique V1

**Statut d’implémentation : EN COURS — la vague 1 est testée localement (70 tests / 258 assertions, test de migration et Pint verts selon le retour utilisateur) ; la vague 2 reste à tester. Ne pas déclarer le domaine conforme tant que les écarts V1 ne sont pas fermés.**

## Périmètre fonctionnel V1

- Galerie de produits, une image principale au maximum et 10 images actives maximum par produit.
- Logo et galerie de photos de coopérative.
- Photo de profil privée.
- Documents privés PDF, JPG, JPEG et PNG ; catégories contrôlées : `identity`, `legal`, `administrative`, `certification`, `other`.
- Quota de 20 documents actifs maximum par propriétaire.
- Stockage physique privé pour les originaux et dérivés ; accès public uniquement via le contrôleur qui revérifie la visibilité à chaque requête.
- Suppression logique, rétention par défaut de 90 jours, purge physique progressive et reprenable.
- Audit complet append-only, chaîne cryptographique, incidents de sécurité et workflow de traitement.
- La documentation fonctionnelle de référence et les 74 décisions MEDIA V1 sont dans `docs/roadmap/FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md`.

Les fonctionnalités non validées dans le cadrage V1 sont reportées à V2.

## Formats, limites et dimensions

| Catégorie | Formats | Taille maximale |
| --- | --- | ---: |
| Image produit | JPG, JPEG, PNG, WebP statique | 5 Mo |
| Logo coopérative | JPG, JPEG, PNG, WebP statique | 5 Mo |
| Photo coopérative | JPG, JPEG, PNG, WebP statique | 5 Mo |
| Photo de profil | JPG, JPEG, PNG, WebP statique | 5 Mo |
| Document privé | PDF, JPG, JPEG, PNG | 10 Mo |

- Validation serveur du MIME réel, de l’extension et de la signature du contenu.
- Dimensions maximales d’une image : 4000 × 4000 pixels ; limite de 16 millions de pixels.
- Les GIF et images animées, y compris WebP animé, sont refusés en V1.
- Orientation EXIF corrigée avant la génération des dérivés ; les métadonnées EXIF restantes, notamment GPS, ne sont pas réémises dans les dérivés.
- Les originaux sont conservés sans recompression.
- Dérivés obligatoires, sans agrandissement artificiel et avec conservation du ratio :
  - `thumbnail` : 150 × 150 maximum ;
  - `small` : 300 × 300 maximum ;
  - `medium` : 600 × 600 maximum ;
  - `large` : 1600 × 1600 maximum.
- Format des dérivés : JPEG vers WebP ; PNG transparent vers PNG ; PNG opaque vers WebP ; WebP vers WebP.
- Qualité cible des dérivés compressés : 85.

L’extension PHP GD avec WebP est requise ; l’extension EXIF est requise pour le traitement des JPEG orientés.

## Stockage et diffusion

Les disques sont configurables dans `config/media.php` :

- `MEDIA_PUBLIC_DISK` : disque de stockage des images publiques logiques (défaut `local`, non exposé directement).
- `MEDIA_PRIVATE_DISK` : disque des médias privés (défaut `local`).

Aucun original ni dérivé n’est servi par une URL physique du stockage. Les images publiques sont diffusées par `GET /media/{media}`, après vérification en base du média, du produit/catalogue/coopérative et de leur état. Les images publiques utilisent `public, no-cache, must-revalidate`; les médias privés utilisent `private, no-store`.

Les variantes disponibles sont `original`, `thumbnail`, `small`, `medium` et `large`. Les documents privés peuvent être affichés dans le navigateur ; `?download=1` demande le téléchargement.

## API web actuelle

Toutes les écritures et consultations privées nécessitent une session authentifiée active.

```http
POST /media
Content-Type: multipart/form-data

owner_type: product | cooperative | user
owner_id: ULID
category: product_image | cooperative_logo | cooperative_photo | profile_image | document
document_category: identity | legal | administrative | certification | other (obligatoire pour document)
file: fichier
```

Le serveur détermine la collection, le disque, la visibilité, le chemin et les dérivés. Le client ne fournit jamais de chemin physique.

```http
GET    /media/{media}
GET    /media/{media}?variant=small
GET    /media/{media}/private
GET    /media/{media}/private?download=1
PUT    /media/{media}/primary
PATCH  /media/{media}/order
DELETE /media/{media}
```

## Autorisations

- `super_admin_root` : périmètre global.
- `super_admin` : coopératives affectées uniquement.
- `cooperative_admin` : sa coopérative uniquement.
- `user` : consultation des images publiques uniquement pour les médias de produits ; aucun droit de gestion des images de produits.
- Le propriétaire d’une photo de profil peut gérer sa propre photo ; les documents privés sont soumis aux Policies et au périmètre du propriétaire.
- Les journaux d’audit complets sont réservés à `super_admin_root`.

Les contrôles d’autorisation sont toujours exécutés côté serveur et ne sont jamais remplacés par les contrôles d’interface.

## Rétention et purge

- `MEDIA_RETENTION_DAYS` : valeur par défaut 90 jours ; une valeur absente/null, invalide ou négative désactive la purge.
- La rétention commence à `deleted_at`.
- La commande `php artisan media:purge-expired` est planifiée quotidiennement à 02:00 et sans chevauchement.
- La purge ne supprime la ligne MEDIA qu’après suppression de l’original et de tous les dérivés ; les suppressions incomplètes sont reprises lors d’une exécution ultérieure.
- `MEDIA_ORPHAN_SAFETY_HOURS` : délai de sécurité des fichiers orphelins, minimum 24 heures. **Le détecteur et le nettoyeur d’orphelins restent à implémenter.**

## État des corrections techniques

| Domaine | État |
| --- | --- |
| Formats, tailles, catégories documentaires, dimensions | Vague 1 testée localement : tests MEDIA verts ; nouvelle exécution globale à prévoir après les corrections suivantes |
| Quotas galerie/documents | Vague 1 testée localement : tests MEDIA verts |
| Quatre dérivés et formats intelligents | Vague 1 testée localement : tests MEDIA verts |
| EXIF et animation | Vague 1 testée localement : tests MEDIA verts |
| Verrouillage et réorganisation atomique | Verrouillage applicatif enregistré ; tests de concurrence réels encore à compléter |
| Rétention 90 jours, planification 02:00 et purge reprenable | Vague 1 testée localement : tests MEDIA verts ; couverture de reprise à approfondir |
| Détection/nettoyage des fichiers orphelins | À implémenter |
| Restauration des médias et cycle de vie des propriétaires | À implémenter ; accès root aux documents privés d’un propriétaire supprimé ajouté dans la vague 2, tests en attente |
| Audit exhaustif, intégrité avant écriture et vérification quotidienne | À implémenter / compléter ; journalisation de l’accès root aux documents d’un propriétaire supprimé ajoutée, tests en attente |
| Anti-énumération, incidents, notifications et pièces jointes | À implémenter |
| Suite de tests complète et Pint | Vague 1 verte selon retour utilisateur ; à relancer après chaque nouvelle vague et sur la suite complète |

## Tests

```bash
php artisan test tests/Framework/Domains/Media
php artisan test tests/Framework/Database/Migrations/MediaCategoryIndexMigrationTest.php
vendor/bin/pint --test
```

Ne déclarer MEDIA V1 conforme qu’après exécution réussie des tests ciblés, de la suite complète et de Pint sur la branche de travail.
