# MIGRATION_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les règles officielles de création et de gestion des migrations de base de données dans le framework **MAHLINE**.

Toutes les migrations doivent respecter ces standards afin de garantir une base de données cohérente, maintenable et évolutive.

---

# Principes

Une migration doit être :

* Simple.
* Déterministe.
* Réversible.
* Documentée.
* Indépendante de la logique métier.

Une migration ne doit jamais contenir de logique applicative.

---

# Une migration = une responsabilité

Chaque migration ne doit réaliser qu'une seule opération.

Exemples :

* Créer une table.
* Modifier une table.
* Ajouter une colonne.
* Supprimer une colonne.
* Ajouter un index.
* Renommer une table.

Une migration ne doit pas mélanger plusieurs responsabilités.

---

# Structure d'une migration

Toutes les migrations suivent la structure Laravel standard.

```php
public function up(): void
{
    //
}

public function down(): void
{
    //
}
```

* `up()` applique les changements.
* `down()` annule exactement les changements réalisés.

---

# Localisation

Les migrations sont organisées par domaine.

```text
Domains/
├── Identity/
│   └── Database/
│       └── Migrations/
│
├── Catalog/
│   └── Database/
│
├── Inventory/
│
└── ...
```

Aucune migration métier ne doit être placée hors de son domaine.

Les migrations techniques Laravel restent dans le dossier `database/migrations`.

---

# Identifiants

Toutes les entités métier utilisent un ULID.

```php
$table->ulid('id')->primary();
```

Les identifiants auto-incrémentés sont interdits pour les tables métier.

---

# Clés étrangères

Toutes les clés étrangères utilisent des ULID.

```php
$table->foreignUlid('user_id');
```

Les contraintes doivent être déclarées explicitement.

Exemple :

```php
$table->foreign('user_id')
      ->references('id')
      ->on('users')
      ->cascadeOnUpdate()
      ->restrictOnDelete();
```

---

# Ordre officiel des colonnes

Toutes les tables respectent le même ordre :

```text
1. Clé primaire

2. Clés étrangères

3. Données métier

4. Colonnes de sécurité

5. Préférences

6. Métadonnées

7. Audit

8. Timestamps

9. Soft Delete
```

Ce standard facilite la lecture et la maintenance.

---

# Audit

Toutes les tables métier incluent :

```php
created_by
updated_by
deleted_by
created_at
updated_at
deleted_at
```

Les tables pivot peuvent être exemptées si cela est justifié.

---

# Soft Delete

Le Soft Delete est activé par défaut pour les entités métier.

```php
$table->softDeletes();
```

Les tables techniques et certaines tables pivot peuvent ne pas l'utiliser.

---

# Index

Les index sont créés lors de la migration.

Exemples :

```php
$table->index('status');

$table->index('email');

$table->unique('email');

$table->index(['status', 'created_at']);
```

Les index doivent répondre à un besoin fonctionnel ou de performance.

---

# Contraintes

Les contraintes doivent être explicites.

Les suppressions en cascade (`cascadeOnDelete`) sont réservées aux cas où elles sont cohérentes avec le métier.

La suppression d'une donnée ne doit jamais provoquer la perte involontaire d'informations critiques.

---

# ENUM

Les types `ENUM` du moteur de base de données sont interdits.

À la place :

```php
$table->string('status');
```

La logique métier est portée par des Enums PHP.

---

# Valeurs par défaut

Les valeurs par défaut doivent être utilisées avec modération.

Exemples acceptés :

```php
true

false

0

now()

'fr'
```

Une valeur par défaut ne doit jamais masquer une erreur métier.

---

# Données initiales

Les migrations ne doivent pas insérer de données métier.

Les données initiales sont gérées par :

* Seeders.
* Factories.
* Scripts d'initialisation.

---

# Modification de structure

Une migration ne doit jamais être modifiée après avoir été exécutée dans un environnement partagé.

Toute évolution passe par une nouvelle migration.

---

# Suppression de colonnes

La suppression d'une colonne doit être précédée d'une analyse d'impact.

Toute suppression doit être documentée.

---

# Nommage des migrations

Utiliser les conventions Laravel.

Exemples :

```text
create_users_table

create_products_table

add_status_to_orders_table

remove_avatar_from_users_table

rename_product_code_column
```

Les noms doivent être explicites.

---

# Bonnes pratiques

Toujours :

* utiliser des noms clairs ;
* ajouter les index dès la création de la table ;
* définir les contraintes ;
* utiliser les méthodes Laravel (`cascadeOnUpdate()`, `nullOnDelete()`, etc.) ;
* prévoir la méthode `down()`.

Ne jamais :

* écrire de logique métier ;
* appeler des services ou repositories ;
* manipuler des modèles Eloquent ;
* effectuer des traitements applicatifs.

---

# Validation

Avant validation, chaque migration doit répondre aux questions suivantes :

* Utilise-t-elle un ULID ?
* Respecte-t-elle l'ordre officiel des colonnes ?
* Les index sont-ils présents ?
* Les clés étrangères sont-elles explicites ?
* Les contraintes sont-elles correctes ?
* Le Soft Delete est-il justifié ?
* La méthode `down()` est-elle complète ?
* La migration est-elle réversible ?
* La logique métier est-elle absente ?

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* INDEX_STANDARD.md
* AUDIT_STANDARD.md
* SOFT_DELETE_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Toutes les migrations du framework MAHLINE doivent respecter ce standard.

En appliquant systématiquement ces règles, la base de données reste homogène, prévisible et évolutive, quel que soit le domaine métier concerné.

---

# Historique

## Version 1.0

* Standard officiel des migrations.
* Adoption des ULID.
* Définition de l'ordre des colonnes.
* Standardisation des index et des contraintes.
* Intégration des règles d'audit et de Soft Delete.
* Interdiction des `ENUM` SQL pour les états métier.
