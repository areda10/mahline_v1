# MAHLINE Framework

> Documentation officielle du framework **MAHLINE**

---

# Présentation

MAHLINE est un framework métier construit sur **Laravel 13** destiné au développement d'une plateforme de marketplace moderne, modulaire et évolutive.

Son objectif est de fournir une architecture robuste permettant de développer rapidement des applications métier tout en garantissant :

* une architecture claire ;
* une forte maintenabilité ;
* une excellente testabilité ;
* une documentation complète ;
* une séparation stricte des responsabilités.

---

# Vision

Le framework est conçu autour des principes suivants :

* Architecture modulaire
* Domain-Driven Design (DDD simplifié)
* API First
* SOLID
* PSR-1 / PSR-4 / PSR-12
* Typage strict
* Forte couverture de tests
* Documentation complète

---

# Architecture générale

```text
app/
├── Core/
├── Domains/
└── Shared/
```

## Core

Le **Core** contient les composants techniques communs du framework.

Exemples :

* Foundation
* Providers
* Contracts
* Interfaces
* Traits
* Helpers

---

## Domains

Les **Domains** regroupent toute la logique métier.

Exemple :

```text
Domains/
├── Identity
├── Localization
├── Catalog
├── Inventory
├── Sales
├── Finance
├── Communication
├── Audit
└── CMS
```

Chaque domaine est autonome et respecte les conventions du framework.

---

## Shared

Le dossier **Shared** contient les composants réutilisables par plusieurs domaines.

Exemples :

* Traits
* Enums
* Validators
* Rules
* Collections
* Casts
* Helpers

---

# Documentation

## Foundation

La Foundation constitue le socle technique du framework.

Documentation détaillée :

```text
docs/framework/foundation/
```

Composants :

* BLOC-001 — Architecture Foundation
* BLOC-002 — Conventions de développement
* BLOC-003 — Organisation du Framework
* BLOC-004 — BaseModel
* BLOC-005 — BaseService
* BLOC-006 — BaseAction
* BLOC-007 — BaseDTO
* BLOC-008 — BaseRequest
* BLOC-009 — BaseResource
* BLOC-010 — BasePolicy
* BLOC-011 — BaseException
* BLOC-012 — BaseValueObject

---

# Documents de référence

Les documents principaux du framework sont :

```text
docs/framework/

README.md
FOUNDATION_V1.md
ARCHITECTURE.md
ARCHITECTURE_AUDIT.md
ADR.md
CODING_STANDARD.md
NAMING_CONVENTIONS.md
TESTING_STANDARD.md
QUALITY_CHECKLIST.md
```

---

# Standards techniques

Le framework applique les standards suivants :

* PHP 8.3+
* Laravel 13
* PSR-1
* PSR-4
* PSR-12
* Typage strict (`declare(strict_types=1);`)

---

# Principes d'architecture

Les principes fondamentaux sont :

* Responsabilité unique (SRP)
* Faible couplage
* Forte cohésion
* Architecture modulaire
* API First
* Une Action = un cas d'utilisation
* Les Services contiennent la logique métier
* Les DTO transportent les données
* Les Resources gèrent la présentation
* Les Policies gèrent les autorisations
* Les Value Objects représentent les concepts métier

---

# Cycle de développement

Chaque composant est développé selon le processus suivant :

1. Conception
2. Développement
3. Tests
4. Documentation
5. Validation
6. Gel

Aucun composant n'est considéré comme terminé tant que ces six étapes ne sont pas complètes.

---

# État du projet

## Foundation

✅ Terminée

## Shared

🔄 À développer

## Domains

🔄 En préparation

---

# Feuille de route

Les prochains domaines seront développés dans l'ordre suivant :

1. Identity
2. Localization
3. Catalog
4. Inventory
5. Sales
6. Finance
7. Communication
8. Audit
9. CMS

---

# Licence

Ce framework est développé dans le cadre du projet **MAHLINE**.

Tous les développements doivent respecter les conventions et l'architecture définies dans cette documentation.

---

# Version

**MAHLINE Framework V1.0**

Statut : **Foundation officiellement gelée**
