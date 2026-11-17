# MAHLINE Media — Release V1

## Périmètre

- Galerie d’images produit avec une image principale.
- Logo et galerie de photos de coopérative.
- Une image de profil active par utilisateur/client.
- Documents PDF privés avec catégorie métier.
- Validation de l’extension et du MIME réel.
- Dérivés `medium` (largeur maximale 1 200 px) et `thumbnail` (400 px) pour les images.
- Suppression logique immédiate et purge physique après 30 jours.
- Journalisation des opérations sans contenu binaire, chemin de stockage ni URL privée.

SVG, certifications de coopératives et certifications de produits sont hors périmètre V1 (Release V2).

## Formats et limites

| Catégorie | Formats | Taille maximale |
| --- | --- | ---: |
| Image produit | JPEG, PNG, WebP | 5 Mo |
| Logo coopérative | JPEG, PNG, WebP | 5 Mo |
| Photo coopérative | JPEG, PNG, WebP | 5 Mo |
| Profil utilisateur/client | JPEG, PNG, WebP | 2 Mo |
| Document | PDF uniquement | 15 Mo |

L’extension PHP GD doit être activée, avec prise en charge du format image envoyé, pour générer les dérivés. Configure aussi `upload_max_filesize` à au moins `15M` et `post_max_size` au-dessus de `15M` dans le PHP utilisé par le serveur web et le CLI.

## Stockage

Les disques sont configurables dans `config/media.php` :

- `MEDIA_PUBLIC_DISK` : disque logique des images produit et coopérative (défaut `local`, non exposé directement par le serveur web).
- `MEDIA_PRIVATE_DISK` : disque des profils et documents privés (défaut `local`).

En production, les deux variables peuvent pointer vers un disque S3 configuré en privé. Ne configure jamais un bucket ou un disque web-accessible pour ces médias : la visibilité `public` est logique et les images sont transmises par l’application après vérification de l’état du propriétaire. Les contrôleurs ne retournent jamais d’URL directe du stockage. Les documents privés sont transmis uniquement par une route protégée par authentification et session active.

## API web

Toutes les opérations d’écriture utilisent une session authentifiée et active.

### Déposer un média

```http
POST /media
Content-Type: multipart/form-data

owner_type: product | cooperative | user
owner_id: ULID
category: product_image | cooperative_logo | cooperative_photo | profile_image | document
file: fichier
document_category: obligatoire pour category=document
```

Le serveur choisit la collection, le disque, la visibilité, le nom de stockage et les dérivés. Le client ne peut pas fournir de chemin ou de visibilité.

### Diffuser une image publique

```http
GET /media/{media}
GET /media/{media}?variant=medium
GET /media/{media}?variant=thumbnail
```

Seules les images produit et coopérative sont publiques. Le propriétaire doit être actif et non supprimé.

### Consulter un média privé

```http
GET /media/{media}/private
```

La route exige une authentification, une session active et l’autorisation de consulter l’entité propriétaire. Les PDF sont servis en téléchargement avec `X-Content-Type-Options: nosniff`.

### Gérer une galerie

```http
PUT    /media/{media}/primary
PATCH  /media/{media}/order
DELETE /media/{media}
```

La première image produit devient automatiquement l’image principale. Une suppression de l’image principale promeut la première image restante. Le logo coopérative et l’image de profil sont remplacés par upload : l’ancienne version est supprimée logiquement.

## Autorisations

- `super_admin_root` : accès global aux médias produits et coopératives.
- `super_admin` : médias des coopératives assignées uniquement.
- `cooperative_admin` : médias de sa coopérative et de ses produits, sous réserve des permissions métier existantes.
- `user` : lecture dans son périmètre et gestion de sa propre image de profil / ses propres documents.
- `customer` : images publiques des produits et gestion de sa propre image de profil / ses propres documents.

Les documents restent privés. La Policy Media contrôle l’entité propriétaire; elle ne remplace pas les autorisations métier des domaines.

## Purge

La commande `php artisan media:purge-expired` supprime les fichiers et dérivés dont la suppression logique date d’au moins 30 jours, puis supprime leur ligne. Elle est planifiée quotidiennement dans `routes/console.php`. Le scheduler Laravel doit être exécuté en production.

## Tests

```bash
php artisan test tests/Framework/Domains/Media
php artisan test tests/Framework/Database/Migrations/MediaCategoryIndexMigrationTest.php
vendor/bin/pint --test
```


## Rétention des médias supprimés

La durée de conservation avant purge physique n'est pas une règle métier V1 validée. En conséquence, `MEDIA_RETENTION_DAYS` est non configurée par défaut et le nettoyage automatique ne purge aucun média tant qu'une durée positive n'est pas explicitement définie. La suppression applicative reste logique ; les fichiers et lignes sont conservés jusqu'à cette décision de rétention.
