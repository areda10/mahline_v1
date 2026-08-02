# ADR — Architecture Decision Records

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL

---

# Présentation

Ce document centralise les décisions d'architecture majeures du framework **MAHLINE**.

Chaque décision est identifiée par un numéro unique (ADR-XXX), accompagnée de son contexte, de la décision prise, de sa justification et de ses conséquences.

L'objectif est de conserver un historique des choix techniques afin de garantir la cohérence du framework dans le temps.

---

# ADR-001 — Architecture modulaire

## Statut

✅ Acceptée

### Décision

Le framework est organisé autour de trois couches principales :

```text
Core
Shared
Domains
```

### Justification

* Séparation des responsabilités.
* Faible couplage.
* Évolution indépendante des domaines.

---

# ADR-002 — Foundation commune

## Statut

✅ Acceptée

### Décision

Tous les composants métier héritent d'une Foundation commune.

### Justification

* Uniformisation du framework.
* Réduction des duplications.
* Évolution centralisée.

---

# ADR-003 — API First

## Statut

✅ Acceptée

### Décision

Toutes les données sortantes passent par des Resources.

Les modèles Eloquent ne sont jamais exposés directement.

### Justification

* Compatibilité REST.
* Applications mobiles.
* Frontends SPA.
* Intégrations externes.

---

# ADR-004 — ULID

## Statut

✅ Acceptée

### Décision

Tous les modèles utilisent des identifiants ULID.

### Justification

* Identifiants uniques.
* Meilleur support des systèmes distribués.
* Tri chronologique.

---

# ADR-005 — Traits pour les comportements transverses

## Statut

✅ Acceptée

### Décision

Les fonctionnalités transverses (audit, journalisation, etc.) sont implémentées via des Traits.

### Justification

* Composition plutôt qu'héritage.
* Réutilisation.
* Simplicité.

---

# ADR-006 — Action = un cas d'utilisation

## Statut

✅ Acceptée

### Décision

Une Action représente un unique cas d'utilisation métier.

### Justification

* Responsabilité unique.
* Tests simplifiés.
* Code plus lisible.

---

# ADR-007 — Les Services ne dépendent jamais des Request

## Statut

✅ Acceptée

### Décision

Les Services manipulent uniquement des objets métier (DTO, Value Objects, Models, etc.).

Ils ne dépendent jamais directement de la couche HTTP.

### Justification

* Indépendance de Laravel.
* Réutilisabilité.
* Tests plus simples.

---

# ADR-008 — DTO obligatoires

## Statut

✅ Acceptée

### Décision

Les données transitent entre les couches via des DTO.

### Justification

* Typage fort.
* Validation.
* Séparation des responsabilités.

---

# ADR-009 — Policies dédiées aux autorisations

## Statut

✅ Acceptée

### Décision

Les Policies gèrent exclusivement les règles d'autorisation.

### Justification

* Sécurité centralisée.
* Respect du SRP.
* Lisibilité.

---

# ADR-010 — Value Objects

## Statut

✅ Acceptée

### Décision

Les concepts métier sans identité sont modélisés par des Value Objects.

### Justification

* Expressivité du domaine.
* Immutabilité.
* Réutilisation.

---

# ADR-011 — Typage strict

## Statut

✅ Acceptée

### Décision

Tous les fichiers PHP utilisent :

```php
declare(strict_types=1);
```

### Justification

* Réduction des erreurs.
* Meilleure robustesse.
* Contrats explicites.

---

# ADR-012 — Standards PSR

## Statut

✅ Acceptée

### Décision

Le framework respecte les standards :

* PSR-1
* PSR-4
* PSR-12

### Justification

* Compatibilité.
* Lisibilité.
* Interopérabilité.

---

# ADR-013 — Documentation obligatoire

## Statut

✅ Acceptée

### Décision

Chaque composant important doit disposer :

* de tests ;
* d'une documentation ;
* d'un statut de validation.

### Justification

* Maintenance facilitée.
* Qualité.
* Pérennité.

---

# ADR-014 — Domaines autonomes

## Statut

✅ Acceptée

### Décision

Les domaines métier sont autonomes et ne doivent pas dépendre directement les uns des autres.

### Justification

* Modularité.
* Évolutivité.
* Réduction du couplage.

---

# ADR-015 — Foundation gelée avant le métier

## Statut

✅ Acceptée

### Décision

La Foundation est stabilisée, documentée et validée avant le développement des domaines métier.

### Justification

* Réduction des changements structurels.
* Base technique fiable.
* Développement métier plus rapide.

---

# Historique

| ADR     | Sujet                         | Statut |
| ------- | ----------------------------- | ------ |
| ADR-001 | Architecture modulaire        | ✅      |
| ADR-002 | Foundation commune            | ✅      |
| ADR-003 | API First                     | ✅      |
| ADR-004 | ULID                          | ✅      |
| ADR-005 | Traits                        | ✅      |
| ADR-006 | Action = Use Case             | ✅      |
| ADR-007 | Services indépendants de HTTP | ✅      |
| ADR-008 | DTO obligatoires              | ✅      |
| ADR-009 | Policies                      | ✅      |
| ADR-010 | Value Objects                 | ✅      |
| ADR-011 | Typage strict                 | ✅      |
| ADR-012 | Standards PSR                 | ✅      |
| ADR-013 | Documentation obligatoire     | ✅      |
| ADR-014 | Domaines autonomes            | ✅      |
| ADR-015 | Foundation gelée              | ✅      |

---

# Évolution

Toute nouvelle décision d'architecture devra être ajoutée à ce document sous la forme d'un nouvel ADR.

Les ADR existants ne doivent pas être modifiés sans justification technique et validation de l'architecture.

Ce registre constitue la référence officielle des choix d'architecture du framework MAHLINE.
