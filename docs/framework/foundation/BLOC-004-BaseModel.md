# BLOC-004 — BaseModel

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/Models/BaseModel.php
```

---

## Objectif

`BaseModel` est la classe de base de tous les modèles Eloquent du framework **MAHLINE**.

Tous les modèles métier doivent hériter de `BaseModel` afin de garantir un comportement uniforme, des conventions communes et une évolution centralisée.

---

## Responsabilités

* Fournir une base commune à tous les modèles.
* Centraliser les comportements transverses.
* Standardiser la gestion des identifiants.
* Préparer les fonctionnalités d'audit et de journalisation.
* Garantir une architecture homogène.

---

## Héritage

```text
Illuminate\Database\Eloquent\Model
                │
                ▼
           BaseModel
                │
                ├── User
                ├── Product
                ├── Category
                ├── Order
                ├── Invoice
                ├── Country
                └── Cooperative
```

Tous les modèles métier héritent de `BaseModel`.

---

## Fonctionnalités intégrées

### ULID

Tous les modèles utilisent un identifiant **ULID**.

Cette décision est appliquée à l'ensemble du framework.

Exemple :

```php
$id = "01KYTYZDRGP5KBT4XDJVP036FW";
```

---

### Soft Deletes

Tous les modèles utilisent le trait Laravel :

```php
use Illuminate\Database\Eloquent\SoftDeletes;
```

La suppression logique est privilégiée afin de préserver l'historique des données.

---

### Audit

Le modèle est conçu pour intégrer les mécanismes d'audit.

Les comportements d'audit sont implémentés via des **Traits**, afin de conserver une architecture modulaire.

Exemples de traits :

```text
HasAudit
HasCreatedBy
HasUpdatedBy
HasDeletedBy
```

---

### Journalisation

La journalisation des événements métier est également assurée par des **Traits** spécialisés.

Elle permettra notamment de suivre :

* les créations ;
* les modifications ;
* les suppressions ;
* les restaurations.

---

## Décisions d'architecture

Les comportements transverses sont ajoutés par **composition** (Traits) et non par une hiérarchie complexe de classes.

Cette approche facilite :

* la réutilisation ;
* les tests ;
* la maintenance ;
* l'évolution du framework.

---

## Dépendances autorisées

* Eloquent Model
* Traits de la Foundation
* Traits Laravel
* Casts
* Enums
* Value Objects

---

## Dépendances interdites

`BaseModel` ne doit jamais dépendre directement de :

* Request
* Controller
* Service
* Repository
* Resource
* Blade
* Session

La logique métier ne doit pas être placée dans les modèles.

---

## Règles de développement

* Tous les modèles héritent de `BaseModel`.
* Tous les modèles utilisent des ULID.
* Les comportements transverses sont ajoutés par des Traits.
* Les relations Eloquent restent dans les modèles.
* La logique métier reste dans les Services et les Actions.

---

## Tests

Les tests vérifient notamment :

* l'existence de `BaseModel` ;
* la génération des ULID ;
* le bon fonctionnement des traits communs ;
* l'héritage des modèles.

---

## Bonnes pratiques

✔ Utiliser les Relations Eloquent uniquement pour représenter les liens entre entités.

✔ Encapsuler la logique métier dans les Services.

✔ Préférer les Traits pour les comportements communs.

✔ Conserver des modèles simples et spécialisés.

---

## Historique

### Version 1.0

* Création de `BaseModel`.
* Adoption des ULID comme identifiant unique.
* Intégration des Soft Deletes.
* Préparation de l'audit et de la journalisation via des Traits.
* Premier gel officiel de `BaseModel`.
