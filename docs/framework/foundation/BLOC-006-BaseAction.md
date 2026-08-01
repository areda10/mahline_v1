# BLOC-006 — BaseAction

## Version

1.0

## Statut

GELÉ

---

## Emplacement

app/Core/Foundation/Actions/BaseAction.php

---

## Objectif

BaseAction est la classe de base de toutes les Actions du framework MAHLINE.

Une Action représente un cas d'utilisation unique (Use Case).

Exemples :

- CreateProductAction
- UpdateProductAction
- DeleteProductAction
- RegisterUserAction
- GenerateInvoiceAction

---

## Responsabilités

- Représenter un seul cas d'utilisation.
- Orchestrer la logique métier.
- Coordonner les Services.
- Retourner un résultat métier.

---

## Ne doit jamais

- Dépendre directement de Request.
- Retourner une View.
- Retourner une Response HTTP.
- Accéder directement à la Session.
- Contenir du HTML.

---

## Dépendances autorisées

- DTO
- Service
- Repository
- Model
- Event
- Exception

---

## Dépendances interdites

- Controller
- Request
- Blade
- Middleware

---

## Tests

- Vérifie que la classe existe.
- Vérifie que la classe est abstraite.

---

## Décision d'architecture

Une Action représente toujours un seul cas d'utilisation métier.

Elle ne doit jamais contenir de logique de présentation.

---

## Historique

Version 1.0

Premier gel de BaseAction.
