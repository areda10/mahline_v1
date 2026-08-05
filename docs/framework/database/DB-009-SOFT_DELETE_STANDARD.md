# SOFT_DELETE_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit le standard officiel d'utilisation du **Soft Delete** dans le framework **MAHLINE**.

Le Soft Delete permet de conserver les données supprimées à des fins de traçabilité, de restauration, d'audit et de conformité, tout en les masquant des traitements courants.

---

# Principes

Le Soft Delete est la méthode de suppression par défaut des entités métier.

Une suppression logique ne retire pas physiquement les données de la base de données. Elle renseigne uniquement la date de suppression.

Ce mécanisme permet :

* la restauration d'un enregistrement ;
* la conservation de l'historique ;
* l'audit des opérations ;
* le respect des exigences métier et réglementaires.

---

# Champ d'application

Le Soft Delete est obligatoire pour toutes les entités métier.

Exemples :

* users
* cooperatives
* products
* categories
* orders
* invoices
* payments
* media
* warehouses
* stocks

---

# Implémentation

Toutes les tables concernées utilisent :

```php id="7j0f5h"
$table->softDeletes();
```

Ce qui crée automatiquement :

```text id="8x4lwd"
deleted_at
```

Les modèles Eloquent utilisent le trait :

```php id="lcjlwm"
use Illuminate\Database\Eloquent\SoftDeletes;
```

---

# Audit

Lors d'une suppression logique :

* `deleted_at` est renseigné automatiquement ;
* `deleted_by` est renseigné par la Foundation lorsque l'utilisateur est identifié.

Exemple :

```text id="vj3g9x"
deleted_by
deleted_at
```

Le Soft Delete est donc étroitement lié au standard d'audit.

---

# Comportement des requêtes

Par défaut, les requêtes Eloquent excluent les enregistrements supprimés.

Pour inclure les éléments supprimés :

```php id="c41w4o"
Model::withTrashed()->get();
```

Pour récupérer uniquement les éléments supprimés :

```php id="9vxw3m"
Model::onlyTrashed()->get();
```

---

# Restauration

Une entité supprimée logiquement peut être restaurée.

```php id="apd8lu"
$model->restore();
```

Lors de la restauration :

* `deleted_at` redevient `NULL` ;
* `deleted_by` peut être conservé pour l'historique ou réinitialisé selon la politique du domaine (la décision doit être uniforme et documentée).

---

# Suppression définitive

Une suppression physique est réalisée via :

```php id="m2mcbz"
$model->forceDelete();
```

Cette opération est exceptionnelle.

Elle doit être réservée :

* aux traitements techniques ;
* aux exigences légales ;
* aux opérations d'administration autorisées.

---

# Relations

Lorsqu'une entité est supprimée logiquement :

* les relations ne doivent pas être supprimées automatiquement, sauf décision métier explicite ;
* les contraintes de clés étrangères continuent de garantir l'intégrité des données.

Le choix entre `restrictOnDelete()`, `nullOnDelete()` et `cascadeOnDelete()` reste défini par le standard des clés étrangères.

---

# Tables exemptées

Le Soft Delete n'est généralement pas utilisé sur :

* les tables pivot simples ;
* les tables techniques Laravel (`jobs`, `cache`, `sessions`, etc.) ;
* les tables temporaires.

Toute exception doit être documentée.

---

# API

Par défaut, les API ne retournent pas les enregistrements supprimés.

Les points d'accès permettant de consulter ou restaurer les éléments supprimés doivent être protégés par des autorisations spécifiques.

---

# Performance

Pour les tables volumineuses, il est recommandé d'indexer la colonne `deleted_at` lorsqu'elle est fréquemment utilisée dans les filtres.

Exemple :

```php id="hczb6p"
$table->index('deleted_at');
```

Ou, si pertinent :

```php id="czmrba"
$table->index(['status', 'deleted_at']);
```

---

# Archivage

Le Soft Delete ne remplace pas une politique d'archivage.

Pour les données anciennes ou volumineuses, des mécanismes d'archivage pourront être mis en place afin de limiter la croissance des tables opérationnelles.

---

# Sécurité

La restauration et la suppression définitive doivent être réservées aux utilisateurs autorisés.

Ces opérations doivent être tracées par le domaine **Audit**.

---

# Interdictions

Sont interdits :

* la suppression physique par défaut des entités métier ;
* la désactivation du Soft Delete sans justification documentée ;
* la restauration sans contrôle d'autorisation ;
* l'utilisation de `forceDelete()` dans les traitements métier courants.

---

# Validation

Avant validation d'une migration ou d'un modèle, vérifier que :

* `softDeletes()` est présent lorsque requis ;
* le modèle utilise le trait `SoftDeletes` ;
* `deleted_by` est pris en charge par le standard d'audit ;
* les autorisations de restauration et de suppression définitive sont définies ;
* les exceptions sont documentées.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* INDEX_STANDARD.md
* AUDIT_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Le Soft Delete est un composant fondamental de la stratégie de gestion des données du framework MAHLINE.

Il garantit la conservation des informations, facilite les restaurations, améliore la traçabilité et complète les mécanismes d'audit, tout en laissant la possibilité d'un archivage ou d'une suppression définitive lorsque cela est nécessaire.

---

# Historique

## Version 1.0

* Adoption du Soft Delete comme mécanisme de suppression par défaut.
* Définition des règles de restauration et de suppression définitive.
* Intégration avec le standard d'audit.
* Standardisation des comportements Eloquent et des règles de sécurité.
* Publication du standard officiel du Soft Delete.
