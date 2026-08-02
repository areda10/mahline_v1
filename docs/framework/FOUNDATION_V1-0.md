# FOUNDATION V1.0

## MAHLINE Framework

### Version

1.0

### Statut

**OFFICIELLEMENT GELÉE**

---

# Présentation

La **Foundation** constitue le socle technique du framework **MAHLINE**.

Elle regroupe l'ensemble des composants fondamentaux utilisés par tous les domaines métier. Son objectif est de fournir une architecture stable, cohérente, testable et facilement maintenable.

Tous les développements du framework reposent sur cette Foundation.

---

# Objectifs

La Foundation a pour objectifs de :

* standardiser les composants techniques ;
* appliquer une architecture homogène ;
* limiter le couplage entre les couches ;
* améliorer la réutilisabilité du code ;
* garantir une forte testabilité ;
* préparer le framework à une évolution durable.

---

# Architecture

La Foundation est organisée comme suit :

```text id="6d7h9v"
app/Core/Foundation/
│
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

Chaque composant possède une responsabilité unique.

---

# Les composants de la Foundation

## BLOC-001 — Architecture Foundation

Définit les principes d'architecture, les couches du framework et les règles de dépendance.

---

## BLOC-002 — Conventions de développement

Décrit les standards de développement :

* PSR-1
* PSR-4
* PSR-12
* Typage strict
* Structure des classes
* Règles de nommage

---

## BLOC-003 — Organisation du Framework

Présente l'organisation officielle du projet :

* Core
* Shared
* Domains

ainsi que les responsabilités de chaque couche.

---

## BLOC-004 — BaseModel

Classe de base de tous les modèles Eloquent.

Fonctionnalités :

* ULID
* Soft Deletes
* Audit (via Traits)
* Journalisation (via Traits)

---

## BLOC-005 — BaseService

Classe de base de tous les Services métier.

Règles principales :

* contient la logique métier ;
* ne dépend jamais d'un `Request` ;
* reste indépendant de la couche HTTP.

---

## BLOC-006 — BaseAction

Classe de base des Actions.

Une Action représente un unique cas d'utilisation métier et orchestre l'exécution d'un Service.

---

## BLOC-007 — BaseDTO

Classe de base des Data Transfer Objects.

Les DTO transportent les données entre les couches sans contenir de logique métier.

---

## BLOC-008 — BaseRequest

Classe de base des requêtes HTTP.

Responsable de la validation des données entrantes avant leur transformation en DTO.

---

## BLOC-009 — BaseResource

Classe de base des Resources.

Responsable de la transformation des données en représentations JSON.

Le framework applique une approche **API First**.

---

## BLOC-010 — BasePolicy

Classe de base des Policies.

Responsable exclusivement des règles d'autorisation.

---

## BLOC-011 — BaseException

Classe de base des exceptions métier.

Elle uniformise la gestion des erreurs dans l'ensemble du framework.

---

## BLOC-012 — BaseValueObject

Classe de base des Value Objects.

Les Value Objects représentent des concepts métier sans identité propre.

---

# Flux d'exécution

Le framework suit le flux suivant :

```text id="jqul4f"
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

Les autorisations sont gérées par les **Policies** et les erreurs métier par les **BaseException**.

---

# Décisions d'architecture

Les décisions majeures adoptées dans la Foundation sont :

* utilisation des ULID comme identifiants ;
* utilisation des Traits pour les comportements transverses ;
* architecture API First ;
* séparation `Core / Shared / Domains` ;
* une Action représente un cas d'utilisation ;
* les Services ne dépendent jamais des Request ;
* les DTO transportent uniquement des données ;
* les Policies gèrent uniquement les autorisations ;
* les Value Objects modélisent les concepts métier ;
* typage strict obligatoire.

---

# Standards appliqués

* PHP 8.3+
* Laravel 13
* PSR-1
* PSR-4
* PSR-12
* `declare(strict_types=1);`

---

# Tests

Chaque composant Foundation possède :

* des tests unitaires ;
* une documentation dédiée ;
* un statut de validation.

La Foundation est considérée comme complète uniquement lorsque ces trois éléments sont présents.

---

# Documentation associée

La documentation détaillée est disponible dans :

```text id="yqgj6o"
docs/framework/foundation/
```

Elle contient les documents :

* BLOC-001 à BLOC-012.

---

# État de la Foundation

| Composant     | Statut |
| ------------- | ------ |
| Architecture  | ✅      |
| Foundation    | ✅      |
| Tests         | ✅      |
| Documentation | ✅      |
| Standards     | ✅      |
| API First     | ✅      |
| Typage strict | ✅      |

---

# Conclusion

La **Foundation V1.0** constitue la base officielle du framework MAHLINE.

Elle est :

* stable ;
* documentée ;
* testée ;
* extensible ;
* prête à accueillir les domaines métier.

Toute évolution future du framework devra respecter les principes et les conventions définis dans ce document.

---

# Historique

## Version 1.0

* Création de la Foundation.
* Développement des neuf composants fondamentaux.
* Validation des tests.
* Documentation complète.
* Gel officiel de la Foundation V1.0.
