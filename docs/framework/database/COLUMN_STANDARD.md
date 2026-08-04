Voici la documentation officielle de **`docs/framework/database/COLUMN_STANDARD.md`**.

# COLUMN_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les règles officielles de conception, de nommage et d'organisation des colonnes de toutes les tables du framework **MAHLINE**.

L'objectif est de garantir une structure uniforme, lisible, cohérente et évolutive sur l'ensemble de la base de données.

---

# Principes

Toutes les colonnes doivent être :

* explicites ;
* cohérentes ;
* prévisibles ;
* documentées ;
* réutilisables.

Une colonne ne doit représenter qu'une seule information.

---

# Ordre officiel des colonnes

Toutes les tables métier respectent l'ordre suivant :

```text
1. Clé primaire

2. Clés étrangères

3. Données métier

4. Colonnes de sécurité

5. Préférences

6. Métadonnées

7. Audit

8. Horodatage

9. Soft Delete
```

Cet ordre est obligatoire pour toutes les nouvelles tables.

---

# 1. Clé primaire

Toutes les tables utilisent un ULID.

Exemple :

```php
$table->ulid('id')->primary();
```

---

# 2. Clés étrangères

Les clés étrangères sont regroupées immédiatement après la clé primaire.

Exemples :

```text
user_id
country_id
category_id
cooperative_id
```

Toutes les clés étrangères utilisent le type ULID.

```php
$table->foreignUlid('user_id');
```

---

# 3. Données métier

Les données métier représentent les informations principales de l'entité.

Exemple pour la table `users` :

```text
first_name
last_name
display_name
email
phone
status
```

Les colonnes doivent être atomiques et ne pas mélanger plusieurs informations.

---

# 4. Colonnes de sécurité

Les colonnes liées à la sécurité sont regroupées.

Exemples :

```text
password
remember_token
email_verified_at
phone_verified_at
password_changed_at
failed_login_attempts
locked_at
last_login_at
last_activity_at
```

---

# 5. Préférences

Les préférences de l'utilisateur ou de l'entité sont regroupées.

Exemples :

```text
locale
preferred_language
timezone
theme
currency
```

---

# 6. Métadonnées

Les métadonnées apportent des informations complémentaires sans faire partie du cœur métier.

Exemples :

```text
avatar
description
notes
settings
metadata
```

L'utilisation de colonnes JSON (`settings`, `metadata`) doit rester exceptionnelle et être justifiée.

---

# 7. Audit

Toutes les tables métier possèdent les colonnes suivantes :

```text
created_by
updated_by
deleted_by
```

Ces colonnes référencent les utilisateurs responsables des opérations.

---

# 8. Horodatage

Toutes les tables métier utilisent :

```php
$table->timestamps();
```

Ce qui crée automatiquement :

```text
created_at
updated_at
```

---

# 9. Soft Delete

Lorsque le Soft Delete est activé :

```php
$table->softDeletes();
```

La colonne suivante est ajoutée :

```text
deleted_at
```

---

# Conventions de nommage

Les colonnes doivent respecter les règles suivantes :

* minuscules ;
* snake_case ;
* noms explicites ;
* pas d'abréviations ambiguës.

### Exemples

Correct :

```text
first_name
last_name
phone_number
email_verified_at
```

Incorrect :

```text
fname
ln
ph
mail2
```

---

# Types recommandés

| Type d'information | Type Laravel recommandé |
| ------------------ | ----------------------- |
| Identifiant        | `ulid()`                |
| Texte court        | `string()`              |
| Texte long         | `text()`                |
| Booléen            | `boolean()`             |
| Date               | `date()`                |
| Date et heure      | `timestamp()`           |
| Nombre entier      | `integer()`             |
| Nombre décimal     | `decimal()`             |
| JSON (exception)   | `json()`                |

---

# Valeurs nulles

Une colonne est déclarée `nullable()` uniquement lorsqu'une valeur absente est cohérente avec le métier.

Éviter les colonnes optionnelles sans justification.

---

# Valeurs par défaut

Les valeurs par défaut doivent être limitées aux cas pertinents.

Exemples :

```php
$table->boolean('is_active')->default(true);

$table->integer('failed_login_attempts')->default(0);

$table->string('locale')->default('fr');
```

Les valeurs par défaut ne doivent jamais masquer un problème métier.

---

# Colonnes interdites

Sont interdites :

* colonnes génériques (`data`, `value`, `info`, `misc`) ;
* noms non explicites ;
* doublons fonctionnels ;
* informations calculables stockées sans justification ;
* colonnes sans utilisation identifiée.

---

# Colonnes calculées

Les données calculables ne doivent pas être stockées, sauf nécessité métier ou optimisation documentée.

Exemple :

Le montant total d'une commande est généralement calculé à partir des lignes de commande, sauf si le métier impose de conserver une valeur figée.

---

# Évolution des colonnes

Une colonne existante ne doit jamais être modifiée dans une ancienne migration.

Toute évolution passe par une nouvelle migration.

---

# Validation

Avant validation d'une table, vérifier que :

* les colonnes sont dans l'ordre officiel ;
* les noms sont explicites ;
* les types sont adaptés ;
* les valeurs par défaut sont justifiées ;
* les colonnes inutiles sont absentes ;
* les règles d'audit sont respectées.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
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

Le respect de ce standard garantit une organisation uniforme des colonnes dans toutes les tables du framework MAHLINE.

Une structure homogène améliore la lisibilité du schéma de données, facilite les revues de code, réduit les erreurs et simplifie les évolutions futures.

---

# Historique

## Version 1.0

* Définition de l'ordre officiel des colonnes.
* Standardisation des types de données.
* Adoption des conventions de nommage.
* Intégration des règles d'audit et de sécurité.
* Publication du standard officiel des colonnes du framework MAHLINE.
