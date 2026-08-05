Voici la documentation officielle de **`docs/framework/database/TABLE_STANDARD.md`**.

# TABLE_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les règles officielles de conception des tables de la base de données du framework **MAHLINE**.

Toutes les tables métier doivent respecter ces standards afin d'assurer une architecture homogène, évolutive et maintenable.

---

# Principes fondamentaux

Chaque table doit respecter les principes suivants :

* Une table = une entité métier.
* Une responsabilité unique.
* Une structure cohérente.
* Une évolutivité sans rupture.
* Une lecture simple.
* Une maintenance facilitée.

---

# Une table = une responsabilité

Une table représente une seule entité métier.

## Exemples

```text
users
roles
permissions
profiles
products
categories
orders
order_items
payments
cooperatives
```

Une table ne doit jamais mélanger plusieurs concepts métier.

---

# Tables autorisées

Les tables de MAHLINE sont réparties en cinq catégories.

## 1. Tables métier

Contiennent les données fonctionnelles.

Exemples :

```text
users
products
orders
invoices
payments
```

---

## 2. Tables de liaison (Pivot)

Utilisées pour les relations Many-to-Many.

Exemples :

```text
role_user
permission_user
product_category
```

Les tables pivot ne contiennent que les informations nécessaires à la relation, sauf si cette relation possède des attributs métier.

---

## 3. Tables de référence

Contiennent des données relativement stables.

Exemples :

```text
countries
currencies
languages
units
tax_rates
```

Ces données sont généralement alimentées par des Seeders.

---

## 4. Tables techniques

Utilisées par Laravel ou par l'infrastructure.

Exemples :

```text
cache
cache_locks
jobs
job_batches
failed_jobs
sessions
```

Ces tables ne sont pas considérées comme des tables métier.

---

## 5. Tables d'historique

Conservent les traces des événements.

Exemples :

```text
connection_logs
audit_logs
activity_logs
```

Les historiques ne doivent jamais remplacer les données métier.

---

# Structure d'une table

Toutes les tables métier suivent le même ordre de colonnes :

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

Cette organisation est obligatoire.

---

# Clé primaire

Toutes les tables métier utilisent un ULID.

Exemple :

```php
$table->ulid('id')->primary();
```

Les identifiants auto-incrémentés sont interdits.

---

# Clés étrangères

Toutes les relations utilisent des ULID.

Exemple :

```php
$table->foreignUlid('user_id');
```

Les contraintes doivent être explicites.

---

# Colonnes métier

Les colonnes métier doivent représenter uniquement les informations fonctionnelles de l'entité.

Exemple pour `users` :

```text
first_name
last_name
email
phone
status
```

Les colonnes calculées ou redondantes sont à éviter, sauf justification documentée.

---

# Colonnes de sécurité

Lorsqu'elles sont nécessaires, elles sont regroupées dans une même section.

Exemples :

```text
password
remember_token
failed_login_attempts
locked_at
password_changed_at
```

---

# Préférences

Les préférences utilisateur ou système sont regroupées.

Exemples :

```text
locale
preferred_language
timezone
theme
```

---

# Métadonnées

Les métadonnées décrivent l'entité sans faire partie de son cœur métier.

Exemples :

```text
avatar
notes
settings
```

Les structures complexes sont préférablement stockées dans des tables dédiées plutôt que dans des colonnes JSON, sauf justification documentée.

---

# Audit

Toutes les tables métier contiennent :

```text
created_by
updated_by
deleted_by
```

Ces colonnes assurent la traçabilité des modifications.

---

# Horodatage

Toutes les tables métier possèdent :

```php
$table->timestamps();
```

Ce qui crée :

```text
created_at
updated_at
```

---

# Soft Delete

Par défaut :

```php
$table->softDeletes();
```

Le Soft Delete est recommandé pour toutes les entités métier.

---

# Relations

Les relations doivent respecter les principes suivants :

* explicites ;
* cohérentes ;
* limitées au besoin métier.

Les relations polymorphes ne sont utilisées que lorsqu'elles apportent un réel bénéfice architectural.

---

# Intégrité référentielle

Toute clé étrangère doit posséder une contrainte.

Les actions (`cascade`, `restrict`, `null`) sont choisies selon les règles métier et non par convenance technique.

---

# Normalisation

Les tables doivent respecter les principes de normalisation afin de limiter les redondances.

La duplication volontaire de données n'est autorisée que lorsqu'elle est justifiée par un besoin métier ou de performance documenté.

---

# Évolution

Une table existante ne doit jamais être modifiée directement dans une ancienne migration.

Toute évolution passe par une nouvelle migration.

---

# Interdictions

Sont interdits :

* plusieurs responsabilités dans une même table ;
* colonnes sans signification métier ;
* identifiants auto-incrémentés pour les tables métier ;
* `ENUM` SQL ;
* logique métier dans les migrations ;
* suppression non documentée de colonnes.

---

# Validation

Avant validation d'une table, vérifier :

* responsabilité unique ;
* ULID comme clé primaire ;
* ordre officiel des colonnes ;
* clés étrangères explicites ;
* index nécessaires ;
* audit présent ;
* timestamps présents ;
* Soft Delete justifié ;
* nom conforme aux conventions.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
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

Le respect de ce standard garantit que toutes les tables du framework MAHLINE possèdent une structure homogène, facilitent les développements futurs et réduisent les coûts de maintenance.

Chaque nouvelle table doit être conçue comme une brique métier indépendante, cohérente avec l'ensemble de l'architecture du framework.

---

# Historique

## Version 1.0

* Création du standard officiel des tables.
* Définition des catégories de tables.
* Standardisation de la structure interne.
* Adoption des ULID.
* Intégration des règles d'audit, de Soft Delete et d'intégrité référentielle.
