# FOREIGN_KEY_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit le standard officiel de gestion des **clés étrangères (Foreign Keys)** dans le framework **MAHLINE**.

Les clés étrangères garantissent l'intégrité référentielle entre les tables et assurent la cohérence des données dans l'ensemble des domaines métier.

---

# Principes

Toutes les relations entre entités métier doivent être matérialisées par des clés étrangères explicites.

Les principes suivants sont obligatoires :

* Intégrité référentielle.
* Cohérence des types de données.
* Contraintes explicites.
* Lisibilité des migrations.
* Compatibilité entre les domaines.

---

# Type des clés étrangères

Toutes les clés étrangères référant une entité métier utilisent un **ULID**.

Exemple :

```php id="2j7v6t"
$table->foreignUlid('user_id');
```

Les clés étrangères numériques (`foreignId()`) sont interdites pour les entités métier.

---

# Convention de nommage

Une clé étrangère est nommée selon la règle :

```text id="suv4b6"
<entité>_id
```

Exemples :

```text id="nffotq"
user_id
role_id
country_id
product_id
category_id
order_id
invoice_id
cooperative_id
```

Les noms ambigus ou abrégés sont interdits.

---

# Déclaration des contraintes

Chaque clé étrangère doit être suivie d'une contrainte explicite.

Exemple :

```php id="pr8qyz"
$table->foreignUlid('user_id');

$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->cascadeOnUpdate()
    ->restrictOnDelete();
```

L'utilisation des méthodes Laravel (`cascadeOnUpdate()`, `restrictOnDelete()`, `nullOnDelete()`, etc.) est recommandée.

---

# Règles de suppression

Le comportement lors de la suppression d'un enregistrement dépend du besoin métier.

## Restrict

Empêche la suppression si des dépendances existent.

```php id="2p85ut"
->restrictOnDelete()
```

À utiliser par défaut lorsque les données doivent être conservées.

---

## Cascade

Supprime automatiquement les enregistrements dépendants.

```php id="dg9c5o"
->cascadeOnDelete()
```

À utiliser uniquement lorsque les données enfants n'ont aucun sens sans le parent.

Exemples possibles :

* lignes temporaires ;
* tables de liaison simples.

---

## Set Null

La clé étrangère est mise à `NULL`.

```php id="jlwmme"
->nullOnDelete()
```

La colonne doit être déclarée `nullable()`.

Exemple :

```php id="p8ow2r"
$table->foreignUlid('updated_by')->nullable();
```

---

# Règles de mise à jour

Par défaut :

```php id="7ztmvv"
->cascadeOnUpdate()
```

Cela garantit la cohérence si un identifiant venait à évoluer, même si ce cas reste exceptionnel avec les ULID.

---

# Audit

Les colonnes d'audit utilisent également des clés étrangères.

Exemple :

```php id="dydim5"
created_by
updated_by
deleted_by
```

Les contraintes recommandées sont :

```php id="8k4g1g"
->nullOnDelete()
->cascadeOnUpdate()
```

Ainsi, la suppression d'un utilisateur ne supprime pas les données historiques.

---

# Relations Many-to-Many

Les tables pivot utilisent des clés étrangères sur chacune des entités liées.

Exemple :

```php id="lvdmp7"
user_id
role_id
```

Chaque clé étrangère possède sa propre contrainte.

---

# Relations polymorphes

Les relations polymorphes Laravel (`morphs`, `nullableMorphs`, `uuidMorphs`, etc.) sont autorisées uniquement lorsqu'elles apportent un bénéfice architectural clairement identifié.

Leur utilisation doit être documentée.

---

# Index

Toute clé étrangère doit être indexée.

Les méthodes Laravel (`foreignUlid()`) créent généralement un index, mais celui-ci doit être vérifié lors de la revue de migration.

---

# Intégrité référentielle

Il est interdit de créer une relation logique sans clé étrangère lorsqu'une contrainte peut être appliquée.

Les exceptions doivent être documentées (interopérabilité, données externes, contraintes techniques).

---

# Clés étrangères facultatives

Une clé étrangère peut être facultative si le métier l'autorise.

Exemple :

```php id="ijhqqy"
$table->foreignUlid('manager_id')->nullable();
```

La contrainte doit alors utiliser `nullOnDelete()` lorsque cela est cohérent.

---

# Interdictions

Sont interdits :

* `foreignId()` pour les entités métier ;
* clés étrangères sans contrainte ;
* noms non explicites ;
* types incompatibles entre clé primaire et clé étrangère ;
* suppression en cascade sans justification métier.

---

# Validation

Avant validation d'une migration, vérifier que :

* toutes les clés étrangères utilisent `foreignUlid()` ;
* chaque relation possède une contrainte explicite ;
* les actions `onUpdate` et `onDelete` sont définies ;
* les noms suivent la convention `<entité>_id` ;
* les colonnes facultatives sont déclarées `nullable()` si nécessaire ;
* les relations sont cohérentes avec le modèle métier.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* INDEX_STANDARD.md
* AUDIT_STANDARD.md
* SOFT_DELETE_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Les clés étrangères constituent un élément essentiel de l'architecture de la base de données MAHLINE.

Le respect de ce standard garantit l'intégrité des relations, limite les incohérences et améliore la qualité globale des données.

---

# Historique

## Version 1.0

* Adoption des ULID pour toutes les clés étrangères métier.
* Définition des conventions de nommage.
* Standardisation des contraintes `onUpdate` et `onDelete`.
* Intégration des règles pour les relations, l'audit et les tables pivot.
* Publication du standard officiel des clés étrangères.
