# BLOC-001 — Architecture Foundation

## Version

1.0

## Statut

GELÉ

---

## Objectif

La **Foundation** constitue le socle technique du framework **MAHLINE**.

Elle regroupe les composants de base communs à tous les domaines métier et définit les conventions d'architecture qui garantissent la cohérence, la maintenabilité et l'évolutivité du framework.

La Foundation ne contient **aucune logique métier spécifique**. Elle fournit uniquement les briques fondamentales utilisées par l'ensemble des domaines.

---

## Vision d'architecture

MAHLINE est construit selon une architecture en couches, avec une séparation stricte des responsabilités.

```text
HTTP
│
├── Request
│
├── Resource
│
├── Policy
│
├── DTO
│
├── Action
│
├── Service
│
├── Repository
│
└── Model
```

Chaque couche possède une responsabilité unique et ne doit pas empiéter sur le rôle des autres.

---

## Structure de la Foundation

```text
app/Core/Foundation
│
├── Actions
├── DTOs
├── Exceptions
├── Models
├── Policies
├── Requests
├── Resources
├── Services
└── ValueObjects
```

Chaque dossier contient les classes de base utilisées par les domaines métier.

---

## Principes fondamentaux

### Responsabilité unique (SRP)

Chaque composant possède une responsabilité unique.

Exemples :

* `BaseRequest` valide les données HTTP.
* `BaseAction` orchestre un cas d'utilisation.
* `BaseService` exécute la logique métier.
* `BaseResource` transforme les données pour la présentation.
* `BasePolicy` gère les autorisations.

---

### Séparation des couches

Les dépendances suivent toujours le même sens :

```text
HTTP
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
```

Aucune couche ne doit contourner cette organisation.

---

### Isolation de Laravel

Laravel est utilisé comme framework d'infrastructure.

La logique métier reste indépendante autant que possible.

Les composants métier ne doivent pas dépendre directement de :

* Request
* Response
* Session
* Blade
* View

Les interactions HTTP restent confinées à la couche Foundation.

---

### API First

Toutes les données sortantes passent par une Resource.

Les modèles Eloquent ne sont jamais exposés directement.

Cette approche garantit une compatibilité native avec :

* API REST
* Applications mobiles
* Frontends SPA
* Intégrations externes

---

### Typage fort

Toutes les classes utilisent :

```php
declare(strict_types=1);
```

Les paramètres et valeurs de retour sont systématiquement typés.

---

### Tests

Chaque composant de la Foundation possède ses propres tests unitaires.

Aucun composant n'est considéré comme terminé sans validation de ses tests.

---

## Dépendances

La Foundation constitue le niveau le plus bas de l'architecture.

Les domaines métier dépendent de la Foundation.

La Foundation ne dépend jamais des domaines métier.

```text
Foundation
      ▲
      │
Domains
```

---

## Objectifs

La Foundation doit offrir :

* une architecture stable ;
* une forte cohérence interne ;
* une excellente testabilité ;
* une documentation complète ;
* une maintenance simplifiée ;
* une évolution maîtrisée.

---

## Décisions d'architecture

Les décisions majeures prises pour la Foundation comprennent notamment :

* utilisation des ULID pour les modèles ;
* utilisation des Traits pour les comportements transverses ;
* séparation Foundation / Support ;
* une Action représente un seul cas d'utilisation ;
* les DTO transportent uniquement des données ;
* les Resources assurent la transformation des réponses ;
* les Policies gèrent exclusivement les autorisations ;
* les Value Objects modélisent les valeurs métier.

---

## Historique

### Version 1.0

* Création de la Foundation.
* Définition de l'architecture officielle.
* Validation des composants fondamentaux.
* Premier gel officiel de l'architecture Foundation.
