# CODING STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL

---

# Objectif

Ce document définit les règles officielles de développement du framework **MAHLINE**.

Tous les développements doivent respecter ces standards afin de garantir :

* une base de code homogène ;
* une excellente lisibilité ;
* une maintenance facilitée ;
* une forte testabilité ;
* une évolution cohérente du framework.

Ces règles s'appliquent à tous les composants du projet : **Core**, **Shared** et **Domains**.

---

# Standards techniques

Le framework repose sur les standards suivants :

* PHP 8.3+
* Laravel 13
* PSR-1
* PSR-4
* PSR-12

---

# Déclaration stricte

Tous les fichiers PHP commencent par :

```php
<?php

declare(strict_types=1);
```

Cette règle est obligatoire.

---

# Structure d'un fichier

Chaque fichier contient :

* une seule classe ;
* un seul namespace ;
* une seule responsabilité.

Le nom du fichier est identique au nom de la classe.

Exemple :

```text
ProductService.php
CreateOrderAction.php
BaseDTO.php
InvoiceResource.php
```

---

# Namespaces

Les namespaces doivent correspondre exactement à l'arborescence des dossiers.

Exemple :

```text
app/Core/Foundation/Services/BaseService.php
```

```php
namespace App\Core\Foundation\Services;
```

---

# Typage

Toutes les propriétés, paramètres et valeurs de retour doivent être typés.

Exemple :

```php
public function execute(ProductDTO $dto): Product
```

L'utilisation de `mixed` est interdite sauf justification documentée.

---

# Responsabilité unique

Chaque classe possède une responsabilité unique.

| Composant    | Responsabilité              |
| ------------ | --------------------------- |
| Request      | Validation HTTP             |
| DTO          | Transport des données       |
| Action       | Cas d'utilisation           |
| Service      | Logique métier              |
| Repository   | Accès aux données           |
| Model        | Représentation des données  |
| Resource     | Transformation des réponses |
| Policy       | Autorisations               |
| Value Object | Valeur métier               |

---

# Dépendances

Le flux officiel est :

```text
Request
    │
    ▼
DTO
    │
    ▼
Action
    │
    ▼
Service
    │
    ▼
Repository
    │
    ▼
Model
```

Les dépendances inverses sont interdites.

Exemples :

* Un **Service** ne dépend jamais d'un `Request`.
* Une **Policy** ne contient pas de logique métier.
* Un **Model** ne dépend pas d'un **Controller**.

---

# Classes de base

Tous les composants doivent hériter des classes Foundation lorsque cela est applicable.

Exemples :

* `BaseModel`
* `BaseService`
* `BaseAction`
* `BaseDTO`
* `BaseRequest`
* `BaseResource`
* `BasePolicy`
* `BaseException`
* `BaseValueObject`

---

# Services

Les Services :

* contiennent la logique métier ;
* sont indépendants de HTTP ;
* ne manipulent jamais directement les objets `Request`.

---

# Actions

Une Action représente un seul cas d'utilisation.

Une Action orchestre l'exécution d'un Service.

---

# DTO

Les DTO :

* transportent les données ;
* ne contiennent pas de logique métier ;
* sont utilisés entre les couches.

---

# Models

Les Models :

* héritent de `BaseModel` ;
* utilisent des ULID ;
* utilisent les Soft Deletes lorsque nécessaire ;
* définissent les relations Eloquent.

Ils ne doivent pas contenir la logique métier.

---

# Policies

Les Policies :

* gèrent exclusivement les autorisations ;
* ne modifient jamais la base de données ;
* ne réalisent aucun traitement métier.

---

# Resources

Les Resources :

* transforment les données ;
* préparent les réponses JSON ;
* ne contiennent pas de logique métier.

---

# Exceptions

Toutes les exceptions métier héritent de `BaseException`.

Les exceptions sont utilisées pour signaler un comportement exceptionnel, jamais pour contrôler le flux normal de l'application.

---

# Value Objects

Les Value Objects :

* représentent un concept métier ;
* sont dépourvus d'identité ;
* sont immutables lorsque cela est pertinent.

---

# Documentation

Chaque composant important doit disposer :

* d'une documentation ;
* de tests ;
* d'un historique de version.

---

# Tests

Chaque nouvelle fonctionnalité doit être accompagnée de tests adaptés.

Aucun composant n'est considéré comme terminé tant que :

* le code est développé ;
* les tests sont validés ;
* la documentation est terminée.

---

# Qualité du code

Le code doit privilégier :

* la simplicité ;
* la lisibilité ;
* la cohérence ;
* la réutilisabilité ;
* la maintenabilité.

Les optimisations prématurées sont à éviter.

---

# Revue de code

Avant toute validation, vérifier :

* respect des conventions ;
* typage complet ;
* absence de duplication ;
* respect de l'architecture ;
* présence des tests ;
* mise à jour de la documentation.

---

# Décisions d'architecture

Ces standards sont directement issus des ADR officiels du framework.

Toute évolution des règles de développement doit être validée par un nouvel ADR avant d'être appliquée.

---

# Conclusion

Le respect de ce document est obligatoire pour tous les développements du framework MAHLINE.

Ces standards garantissent une architecture stable, un code homogène et une évolution maîtrisée du projet.

---

# Historique

## Version 1.0

* Adoption des standards PSR-1, PSR-4 et PSR-12.
* Généralisation du typage strict.
* Définition des règles de responsabilité des composants.
* Formalisation des règles de qualité et de revue de code.
* Publication du standard officiel de développement du framework.
