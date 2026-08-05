# DATABASE ARCHITECTURE

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit l'architecture officielle de la couche base de données du framework **MAHLINE**.

Son objectif est de garantir une structure homogène, évolutive et maintenable pour l'ensemble des domaines métier.

Toutes les tables, migrations, contraintes et relations du framework doivent respecter cette architecture.

---

# Principes

La base de données MAHLINE est conçue selon les principes suivants :

* Architecture orientée domaine (Domain Driven Design).
* Séparation claire entre les domaines métier.
* Identifiants ULID pour toutes les entités.
* Audit intégré sur toutes les tables métier.
* Soft Delete par défaut sur les entités métier.
* Contraintes d'intégrité référentielle.
* Évolutivité sans rupture de compatibilité.
* Indépendance vis-à-vis du moteur de base de données.

---

# Organisation

Chaque domaine possède ses propres migrations.

```text
Domains/
├── Identity/
│   └── Database/
│       ├── Migrations/
│       ├── Factories/
│       └── Seeders/
│
├── Catalog/
│   └── Database/
│
├── Inventory/
│   └── Database/
│
├── Finance/
│   └── Database/
│
└── ...
```

Aucune migration métier ne doit être placée hors de son domaine.

---

# Architecture logique

```text
Database
│
├── Identity
├── Localization
├── Catalog
├── Inventory
├── Cooperative
├── Sales
├── Finance
├── Communication
├── Audit
└── CMS
```

Chaque domaine est responsable de ses propres tables.

---

# Principes de conception

## Une table = une responsabilité

Chaque table représente une seule entité métier.

Exemples :

* users
* roles
* permissions
* products
* orders
* invoices

Les tables « fourre-tout » sont interdites.

---

## Relations

Les relations doivent être explicites.

Types autorisés :

* One To One
* One To Many
* Many To Many
* Polymorphic Relations (uniquement lorsque cela apporte un réel bénéfice)

Les relations complexes doivent rester exceptionnelles.

---

# Identifiants

Toutes les entités utilisent un ULID.

Exemple :

```php
$table->ulid('id')->primary();
```

Les identifiants auto-incrémentés (`BIGINT AUTO_INCREMENT`) sont interdits pour les entités métier.

---

# Clés étrangères

Toutes les relations utilisent des ULID.

Exemple :

```php
$table->foreignUlid('user_id');
```

Les contraintes doivent être définies explicitement.

---

# Audit

Toutes les tables métier contiennent :

```text
created_by
updated_by
deleted_by
created_at
updated_at
deleted_at
```

Les tables pivot peuvent être exemptées selon leur rôle fonctionnel.

---

# Soft Delete

Le Soft Delete est activé par défaut sur les entités métier.

Les tables techniques ou les tables pivot peuvent ne pas l'utiliser lorsque cela est justifié.

---

# Séparation des données

Les données sont réparties en quatre catégories.

## Données métier

Exemples :

* produits
* commandes
* utilisateurs
* coopératives

---

## Données techniques

Exemples :

* cache
* jobs
* sessions
* locks

Ces tables sont gérées par l'infrastructure Laravel ou les composants techniques.

---

## Données de référence

Exemples :

* pays
* devises
* langues
* unités
* catégories système

Ces données évoluent peu et sont généralement alimentées par des seeders.

---

## Données d'audit

Exemples :

* journaux
* historiques
* événements
* traces de sécurité

---

# Nommage

Toutes les tables utilisent :

* minuscules ;
* snake_case ;
* noms au pluriel.

Exemples :

```text
users
products
categories
orders
invoice_items
```

---

# Contraintes

Toutes les contraintes doivent être nommées explicitement.

Les suppressions en cascade ne sont utilisées que lorsqu'elles correspondent au besoin métier.

La suppression d'une entité ne doit jamais provoquer la perte involontaire de données critiques.

---

# Index

Les index sont créés dès la migration.

Types courants :

* clé primaire ;
* clé étrangère ;
* index simples ;
* index composites ;
* index uniques.

Chaque index doit répondre à un besoin identifié.

---

# Énumérations

Les états métier sont gérés par des Enums PHP.

La base de données stocke une valeur simple (généralement une chaîne de caractères).

Les types `ENUM` propres au SGBD ne sont pas utilisés.

---

# Sécurité

Les données sensibles doivent être :

* hachées lorsque nécessaire (ex. mots de passe) ;
* chiffrées lorsque requis par le métier ;
* jamais stockées en clair si cela présente un risque.

---

# Performances

Les principes suivants doivent être respectés :

* éviter les duplications de données ;
* limiter les colonnes inutilisées ;
* indexer les colonnes de recherche ;
* privilégier des relations cohérentes plutôt que des calculs complexes.

---

# Compatibilité

L'architecture est conçue pour rester compatible avec les principaux moteurs pris en charge par Laravel :

* MySQL
* MariaDB
* PostgreSQL
* SQLite (développement et tests)

Aucun choix d'architecture ne doit dépendre d'une fonctionnalité spécifique à un seul moteur.

---

# Évolutivité

Toute évolution importante de la structure de la base de données doit :

* être documentée ;
* faire l'objet d'un ADR si elle modifie les standards ;
* préserver la compatibilité avec les domaines existants lorsque cela est possible.

---

# Documents associés

* MIGRATION_STANDARD.md
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

La couche base de données de MAHLINE constitue un socle commun à tous les domaines du framework.

En appliquant systématiquement ces principes, le projet bénéficie d'une structure cohérente, d'une meilleure maintenabilité, d'une évolution maîtrisée et d'une qualité homogène sur l'ensemble des composants.

---

# Historique

## Version 1.0

* Création de l'architecture officielle de la base de données.
* Adoption des ULID comme identifiants uniques.
* Intégration des standards d'audit et de Soft Delete.
* Définition des règles de conception, de nommage et d'organisation des tables.
* Publication de la première version officielle.
