# BLOC-010 — BasePolicy

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/Policies/BasePolicy.php
```

---

## Objectif

`BasePolicy` est la classe de base de toutes les Policies du framework **MAHLINE**.

Une Policy est responsable exclusivement des règles d'autorisation. Elle détermine si un utilisateur est autorisé ou non à effectuer une action sur une ressource.

Toutes les Policies du projet devront hériter de `BasePolicy`.

---

## Responsabilités

* Centraliser les règles communes d'autorisation.
* Fournir une base uniforme pour toutes les Policies.
* Garantir une séparation claire entre les autorisations et la logique métier.
* Préparer le framework à une gestion fine des rôles et des permissions.

---

## Exemple d'héritage

```text
BasePolicy
│
├── UserPolicy
├── ProductPolicy
├── CategoryPolicy
├── OrderPolicy
├── InvoicePolicy
├── CooperativePolicy
└── CountryPolicy
```

---

## Architecture

```text
Utilisateur
      │
      ▼
Controller
      │
      ▼
Policy
      │
      ▼
Autorisé ?
      │
      ├── Oui
      ▼
Action
      │
      ▼
Service
```

La Policy intervient uniquement avant l'exécution de l'action métier.

---

## Dépendances autorisées

* User
* Model
* Enum
* Value Object
* Types PHP

---

## Dépendances interdites

* Service
* Repository
* Request
* Response
* Resource
* Base de données (écriture)
* Appels HTTP
* Logique métier

---

## Règles de développement

* Une Policy par ressource métier.
* Les méthodes retournent uniquement un booléen ou un objet de réponse d'autorisation.
* Les Policies ne modifient jamais l'état de l'application.
* Les règles d'autorisation restent simples, lisibles et testables.

---

## Bonnes pratiques

✔ Une responsabilité unique : autoriser ou refuser.

✔ Utiliser des méthodes explicites (`view`, `create`, `update`, `delete`, etc.).

✔ Centraliser les règles d'accès dans les Policies.

✔ Éviter toute duplication de logique d'autorisation.

---

## Tests

Les tests vérifient :

* L'existence de la classe.
* Son caractère abstrait.

Les Policies concrètes devront également être testées pour chaque règle d'autorisation.

---

## Décision d'architecture

Les Policies constituent la couche de sécurité du framework.

Elles répondent uniquement à la question :

> **"Cet utilisateur est-il autorisé à effectuer cette action ?"**

Toute logique métier reste dans les Actions et les Services.

---

## Intégration future

`BasePolicy` servira de fondation au domaine **Identity**, qui gérera notamment :

* les rôles ;
* les permissions ;
* les profils utilisateurs ;
* les règles d'accès à la marketplace.

---

## Historique

### Version 1.0

* Création de `BasePolicy`.
* Définition de la responsabilité unique des Policies.
* Préparation de la gestion des autorisations de MAHLINE.
* Premier gel officiel du composant.
