# BLOC-007 — BaseDTO

## Version

1.0

## Statut

GELÉ

---

## Emplacement

app/Core/Foundation/DTOs/BaseDTO.php

---

## Objectif

BaseDTO est la classe de base de tous les Data Transfer Objects (DTO) du framework MAHLINE.

Un DTO transporte des données entre les différentes couches de l'application sans contenir de logique métier.

---

## Responsabilités

- Transporter des données.
- Garantir un typage fort.
- Servir d'interface entre les couches.

---

## Ne doit jamais

- Contenir de logique métier.
- Accéder à la base de données.
- Dépendre d'une Request HTTP.
- Accéder à la Session.
- Générer une réponse HTTP.

---

## Dépendances autorisées

- Types PHP
- Value Objects

---

## Dépendances interdites

- Controller
- Request
- Service
- Repository
- Model

---

## Tests

- Vérifie que la classe existe.
- Vérifie que la classe est abstraite.

---

## Décision d'architecture

Les DTO sont des objets de transport de données uniquement.

Ils sont indépendants de Laravel autant que possible et ne contiennent aucune logique métier.

---

## Historique

Version 1.0

Premier gel de BaseDTO.
