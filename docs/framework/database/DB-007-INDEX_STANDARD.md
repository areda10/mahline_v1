# INDEX_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les règles officielles de création et de gestion des **index** dans la base de données du framework **MAHLINE**.

L'objectif est de garantir de bonnes performances tout en conservant une architecture simple, cohérente et évolutive.

---

# Principes

Les index sont créés pour :

* accélérer les recherches ;
* optimiser les jointures ;
* garantir l'unicité lorsque nécessaire ;
* améliorer les performances des tris et des filtres.

Un index ne doit jamais être ajouté sans justification fonctionnelle ou technique.

---

# Types d'index autorisés

Le framework MAHLINE utilise les types d'index suivants :

* Clé primaire (`PRIMARY KEY`)
* Index simple (`INDEX`)
* Index unique (`UNIQUE`)
* Index composite (`INDEX`)
* Clé étrangère (avec son index associé)

Les autres types spécifiques à un moteur de base de données (FullText, Spatial, etc.) doivent être documentés et justifiés.

---

# Clé primaire

Toutes les tables métier utilisent un ULID comme clé primaire.

```php id="rj6qxa"
$table->ulid('id')->primary();
```

La clé primaire est automatiquement indexée.

---

# Clés étrangères

Toutes les clés étrangères doivent être indexées.

Exemple :

```php id="2h52i8"
$table->foreignUlid('user_id');

$table->foreign('user_id')
      ->references('id')
      ->on('users');
```

Les performances des jointures dépendent directement de ces index.

---

# Index simples

Créer un index simple lorsqu'une colonne est régulièrement utilisée :

* dans les recherches ;
* dans les filtres ;
* dans les tris.

Exemple :

```php id="rjlwmn"
$table->index('status');

$table->index('email');

$table->index('created_at');
```

---

# Index uniques

Utiliser un index unique lorsque la valeur doit être unique.

Exemples :

```php id="ynzxii"
$table->unique('email');

$table->unique('code');

$table->unique('reference');
```

L'unicité doit correspondre à une règle métier.

---

# Index composites

Créer un index composite lorsque plusieurs colonnes sont fréquemment utilisées ensemble.

Exemple :

```php id="kl3mse"
$table->index([
    'status',
    'created_at'
]);
```

Ou :

```php id="0gxtp7"
$table->index([
    'country_id',
    'city_id'
]);
```

L'ordre des colonnes dans un index composite doit refléter les requêtes les plus fréquentes.

---

# Colonnes généralement indexées

Les colonnes suivantes sont souvent de bonnes candidates :

```text id="1aflmw"
status

email

phone

code

reference

created_at

updated_at

deleted_at

last_login_at
```

Le choix final dépend des besoins métier.

---

# Colonnes à ne pas indexer systématiquement

Éviter d'indexer :

* les colonnes très peu utilisées ;
* les colonnes contenant des textes longs (`TEXT`) ;
* les colonnes avec une très faible sélectivité (par exemple un booléen utilisé seul).

Chaque index a un coût lors des insertions et des mises à jour.

---

# Index et Soft Delete

Lorsque les requêtes filtrent régulièrement sur les enregistrements actifs, un index composite peut être pertinent.

Exemple :

```php id="qdr8ie"
$table->index([
    'status',
    'deleted_at'
]);
```

Le choix doit être basé sur les usages observés.

---

# Index et audit

Les colonnes d'audit peuvent être indexées si elles sont fréquemment utilisées.

Exemple :

```php id="t8o6sa"
$table->index('created_by');

$table->index('updated_by');
```

---

# Nommage des index

Par défaut, Laravel génère des noms d'index.

Pour les index complexes ou lorsque la lisibilité est importante, il est recommandé de fournir un nom explicite.

Exemple :

```php id="wdckdz"
$table->index(
    ['status', 'created_at'],
    'idx_users_status_created_at'
);
```

---

# Évolution

Un index existant ne doit jamais être supprimé sans :

* une analyse d'impact ;
* une justification ;
* une nouvelle migration.

---

# Performances

Les index doivent être revus régulièrement.

Un index inutile :

* ralentit les écritures ;
* consomme de l'espace disque ;
* augmente le coût des mises à jour.

Le principe est de créer le nombre d'index nécessaire, sans excès.

---

# Interdictions

Sont interdits :

* index créés sans justification ;
* duplication d'index équivalents ;
* index sur des colonnes jamais recherchées ;
* suppression d'un index sans analyse d'impact.

---

# Validation

Avant validation d'une migration, vérifier que :

* la clé primaire est indexée ;
* toutes les clés étrangères sont indexées ;
* les colonnes de recherche sont correctement indexées ;
* les contraintes d'unicité sont respectées ;
* les index composites sont justifiés ;
* aucun index redondant n'a été créé.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* AUDIT_STANDARD.md
* SOFT_DELETE_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Les index sont essentiels aux performances du framework MAHLINE.

Ils doivent être pensés dès la conception des migrations et rester alignés avec les besoins fonctionnels réels. Un schéma d'indexation cohérent améliore les temps de réponse tout en limitant les coûts de maintenance.

---

# Historique

## Version 1.0

* Définition des types d'index autorisés.
* Standardisation des index simples, uniques et composites.
* Intégration des recommandations pour les clés étrangères, l'audit et le Soft Delete.
* Définition des règles de validation et de maintenance des index.
* Publication du standard officiel des index du framework MAHLINE.
