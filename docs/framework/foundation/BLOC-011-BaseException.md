# BLOC-011 — BaseException

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/Exceptions/BaseException.php
```

---

## Objectif

`BaseException` est la classe de base de toutes les exceptions métier du framework **MAHLINE**.

Elle fournit un point d'entrée commun pour la gestion des erreurs métier et garantit une approche homogène des exceptions dans toute l'application.

Toutes les exceptions métier devront hériter de `BaseException`.

---

## Responsabilités

* Centraliser les exceptions métier.
* Uniformiser la gestion des erreurs.
* Préparer la journalisation des exceptions.
* Faciliter la gestion des réponses d'erreur des API.
* Servir de classe mère pour toutes les exceptions spécifiques aux domaines.

---

## Exemple d'héritage

```text
BaseException
│
├── ProductNotFoundException
├── InvalidOrderException
├── UnauthorizedActionException
├── InvoiceGenerationException
├── PaymentException
└── StockUnavailableException
```

---

## Architecture

```text
Controller
      │
      ▼
Action
      │
      ▼
Service
      │
      ▼
Exception métier
      │
      ▼
BaseException
      │
      ▼
Handler Laravel
      │
      ▼
Réponse HTTP / API
```

Les exceptions remontent naturellement jusqu'au gestionnaire global des exceptions de Laravel.

---

## Dépendances autorisées

* `Exception`
* Types PHP
* Enums
* Value Objects

---

## Dépendances interdites

* Request
* Response
* View
* Blade
* Repository
* Service
* Logique métier

---

## Règles de développement

* Toutes les exceptions métier héritent de `BaseException`.
* Une exception représente un seul type d'erreur.
* Les messages doivent être explicites et compréhensibles.
* Les exceptions ne doivent jamais contenir de logique métier.

---

## Bonnes pratiques

✔ Créer une exception spécifique par cas d'erreur important.

✔ Donner des noms explicites (`ProductNotFoundException`, `OrderAlreadyPaidException`, etc.).

✔ Utiliser les exceptions pour signaler un comportement exceptionnel, pas pour contrôler le flux normal de l'application.

✔ Laisser le gestionnaire global des exceptions produire la réponse HTTP adaptée.

---

## Tests

Les tests vérifient :

* L'existence de la classe.
* Son caractère abstrait.
* Son héritage de `Exception`.

Les exceptions métier pourront ensuite être testées individuellement si elles ajoutent un comportement spécifique.

---

## Décision d'architecture

Toutes les erreurs métier du framework MAHLINE passent par des exceptions spécialisées.

La logique métier signale les erreurs en lançant une exception ; la présentation (Web ou API) est responsable de leur transformation en réponse adaptée.

Cette séparation améliore la lisibilité, la maintenabilité et la cohérence du framework.

---

## Historique

### Version 1.0

* Création de `BaseException`.
* Définition de la classe de base des exceptions métier.
* Préparation de la gestion centralisée des erreurs.
* Premier gel officiel du composant.
