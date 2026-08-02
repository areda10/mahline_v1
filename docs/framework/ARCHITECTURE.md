Voici le document docs/framework/ARCHITECTURE.md. Il décrit la vision globale de l'architecture de MAHLINE, tandis que ARCHITECTURE_AUDIT.md décrit son état de conformité.

# ARCHITECTURE

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIELLE

---

# Objectif

Ce document présente l'architecture globale du framework **MAHLINE**.

Il définit les différentes couches du framework, leurs responsabilités, les dépendances autorisées et les principes de conception utilisés pour construire une architecture modulaire, maintenable et évolutive.

Il constitue la référence technique de l'organisation du projet.

---

# Vision

MAHLINE est construit autour d'une architecture orientée domaines (DDD simplifié) reposant sur trois piliers :

* **Core** : infrastructure et composants techniques communs.
* **Shared** : composants transverses réutilisables.
* **Domains** : logique métier organisée par domaine fonctionnel.

Cette séparation permet de limiter les dépendances, d'améliorer la réutilisation et de faire évoluer chaque domaine indépendamment.

---

# Architecture générale

```text id="s7gxaq"
app/
├── Core/
├── Shared/
└── Domains/
```

---

# Core

Le dossier **Core** contient tous les composants techniques du framework.

```text id="c6kwju"
Core/
├── Foundation/
├── Providers/
├── Contracts/
├── Interfaces/
├── Traits/
├── Enums/
├── Exceptions/
├── Helpers/
├── Services/
└── ValueObjects/
```

## Responsabilités

* fournir les composants techniques communs ;
* définir les conventions du framework ;
* héberger les abstractions ;
* assurer la stabilité de l'architecture.

---

# Foundation

La Foundation est le socle technique de MAHLINE.

Elle contient les classes de base suivantes :

```text id="97kx0q"
Foundation/
├── BaseModel
├── BaseService
├── BaseAction
├── BaseDTO
├── BaseRequest
├── BaseResource
├── BasePolicy
├── BaseException
└── BaseValueObject
```

Tous les composants métier héritent de ces classes.

---

# Shared

Le dossier **Shared** regroupe les composants réutilisables dans plusieurs domaines.

Exemples :

```text id="9cxt7r"
Shared/
├── Traits/
├── Enums/
├── Casts/
├── Collections/
├── Constants/
├── Helpers/
├── Macros/
├── Rules/
├── Utils/
└── Validators/
```

Les composants de Shared ne contiennent aucune logique métier spécifique.

---

# Domains

Les domaines regroupent la logique métier.

Organisation prévue :

```text id="igjrb5"
Domains/
├── Identity/
├── Localization/
├── Catalog/
├── Inventory/
├── Sales/
├── Finance/
├── Communication/
├── Audit/
└── CMS/
```

Chaque domaine est autonome et suit une organisation interne commune.

---

# Structure d'un domaine

```text id="otqkrm"
Domain/
├── Actions/
├── DTOs/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Repositories/
├── Services/
├── Tests/
└── ...
```

Cette structure est identique pour tous les domaines.

---

# Flux d'exécution

Le traitement d'une requête suit le flux suivant :

```text id="6g3wh8"
HTTP Request
      │
      ▼
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
      │
      ▼
Resource
      │
      ▼
HTTP Response
```

Les Policies interviennent avant l'exécution d'une action et les Exceptions assurent la remontée des erreurs métier.

---

# Dépendances

Les dépendances sont strictement contrôlées.

```text id="ux1qte"
Core
  ▲
  │
Shared
  ▲
  │
Domains
```

## Règles

* `Domains` peut utiliser `Core` et `Shared`.
* `Shared` peut utiliser `Core`.
* `Core` ne dépend jamais de `Domains`.
* Les domaines ne doivent pas dépendre directement les uns des autres.

Les échanges entre domaines se font par des contrats, des événements ou des interfaces.

---

# Principes d'architecture

Le framework applique les principes suivants :

* Responsabilité unique (SRP)
* Faible couplage
* Forte cohésion
* API First
* Typage strict
* Architecture modulaire
* Réutilisabilité
* Testabilité

---

# Standards techniques

* PHP 8.3+
* Laravel 13
* PSR-1
* PSR-4
* PSR-12
* `declare(strict_types=1);`

---

# Décisions d'architecture

Les principales décisions sont :

* utilisation des ULID ;
* utilisation des Traits pour les comportements transverses ;
* séparation `Core / Shared / Domains` ;
* une Action représente un cas d'utilisation ;
* les Services ne dépendent jamais des `Request` ;
* les DTO transportent uniquement des données ;
* les Resources assurent la transformation des réponses ;
* les Policies gèrent les autorisations ;
* les Value Objects représentent des concepts métier sans identité.

---

# Objectifs

L'architecture de MAHLINE vise à :

* simplifier la maintenance ;
* favoriser la réutilisation ;
* faciliter les tests ;
* permettre l'évolution indépendante des domaines ;
* offrir une base stable pour les futures versions du framework.

---

# Documents associés

* `README.md`
* `FOUNDATION_V1.md`
* `ARCHITECTURE_AUDIT.md`
* `ADR.md`
* `CODING_STANDARD.md`
* `NAMING_CONVENTIONS.md`
* `TESTING_STANDARD.md`
* `QUALITY_CHECKLIST.md`

---

# Historique

## Version 1.0

* Définition de l'architecture officielle du framework.
* Validation de la séparation `Core / Shared / Domains`.
* Adoption d'une architecture modulaire orientée domaines.
* Publication de la première version officielle.
