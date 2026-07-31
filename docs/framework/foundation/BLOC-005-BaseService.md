# BLOC-005 — BaseService

## Statut

- Version : 1.0
- État : GELÉ

## Objectif

Classe de base des services métier.

## Responsabilités

- Contenir la logique métier.
- Servir de point d'extension commun.

## Ne doit jamais

- Dépendre de Request.
- Dépendre de Controller.
- Retourner une Response HTTP.
- Retourner une View.
- Contenir de logique de présentation.

## Dépendances autorisées

- DTO
- Repository
- Model
- ValueObject
- Event

## Tests réalisés

- Compilation
- Héritage
- Résolution via le conteneur Laravel

## Résultat

Validé.
