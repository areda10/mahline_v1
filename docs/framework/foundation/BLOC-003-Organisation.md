# BLOC-003 — Organisation du Framework

## Version

1.0

## Statut

GELÉ

---

## Objectif

Ce document définit l'organisation officielle du framework **MAHLINE**.

Il décrit la structure des répertoires, les responsabilités de chaque couche et les règles d'organisation des composants afin de garantir une architecture modulaire, cohérente et évolutive.

L'objectif est que chaque développeur puisse localiser immédiatement un composant et comprendre son rôle.

---

## Architecture générale

Le framework MAHLINE est organisé autour de trois niveaux principaux :

```text
app/
├── Core/
├── Domains/
└── Shared/
```

---

## Core

Le dossier **Core** contient les composants techniques communs à l'ensemble du framework.

Il ne contient aucune logique métier spécifique.

```text
app/Core/
├── Foundation/
├── Providers/
├── Contracts/
├── Traits/
├── Enums/
├── Exceptions/
├── Helpers/
├── Interfaces/
├── Services/
├── ValueObjects/
└── ...
```

### Responsabilités

* Fournir les composants de base.
* Définir les conventions techniques.
* Héberger les éléments transverses.
* Assurer la stabilité du framework.

---

## Foundation

La Foundation constitue le socle du framework.

```text
Foundation/
├── Actions/
├── DTOs/
├── Exceptions/
├── Models/
├── Policies/
├── Requests/
├── Resources/
├── Services/
└── ValueObjects/
```

Tous les composants fondamentaux héritent de ces classes de base.

---

## Domains

Le dossier **Domains** regroupe toute la logique métier.

Chaque domaine est autonome.

Exemple :

```text
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

Chaque domaine possède sa propre organisation interne.

Exemple :

```text
Catalog/
└── Products/
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
    └── Tests/
```

---

## Shared

Le dossier **Shared** contient les composants réutilisables par plusieurs domaines.

Exemples :

```text
Shared/
├── Traits/
├── Enums/
├── Helpers/
├── Rules/
├── Validators/
├── Collections/
├── Casts/
├── Constants/
├── Macros/
└── Utils/
```

Les composants de `Shared` ne contiennent pas de logique métier spécifique.

---

## Dépendances

Les dépendances suivent toujours le même sens :

```text
Core
  ▲
  │
Shared
  ▲
  │
Domains
```

Règles :

* Les **Domains** peuvent utiliser `Core` et `Shared`.
* **Shared** peut utiliser `Core`.
* **Core** ne dépend jamais de `Domains`.
* Les domaines ne doivent pas dépendre directement les uns des autres.

Les échanges entre domaines passent par des contrats, des événements ou des interfaces clairement définies.

---

## Organisation des fichiers

Chaque fichier contient :

* une seule classe ;
* un seul namespace ;
* une seule responsabilité.

Le nom du fichier est identique au nom de la classe.

---

## Tests

Chaque couche possède ses propres tests.

```text
tests/
├── Framework/
├── Unit/
├── Feature/
└── Integration/
```

Les composants Foundation sont testés indépendamment des domaines métier.

---

## Documentation

Chaque composant important possède :

* son document de conception ;
* ses tests ;
* son historique de version.

La documentation est stockée dans :

```text
docs/framework/
```

---

## Décisions d'architecture

Les principales décisions retenues sont :

* séparation stricte entre Core, Shared et Domains ;
* architecture orientée domaines (Domain-Driven Design simplifié) ;
* Foundation commune à tous les domaines ;
* architecture API First ;
* composants faiblement couplés ;
* responsabilité unique pour chaque classe.

---

## Objectifs

Cette organisation permet :

* une maintenance facilitée ;
* une meilleure évolutivité ;
* une forte réutilisabilité ;
* une excellente testabilité ;
* une séparation claire des responsabilités.

---

## Historique

### Version 1.0

* Définition de l'organisation officielle du framework.
* Adoption de la structure `Core / Shared / Domains`.
* Validation de l'architecture modulaire.
* Premier gel officiel de l'organisation du framework.
