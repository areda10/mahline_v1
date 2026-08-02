# BLOC-008 — BaseRequest

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/Requests/BaseRequest.php
```

---

## Objectif

`BaseRequest` est la classe de base de toutes les requêtes HTTP du framework **MAHLINE**.

Elle centralise les comportements communs des requêtes de validation et constitue le point d'entrée de toutes les données provenant des contrôleurs.

Toutes les classes de type `FormRequest` du projet devront hériter de `BaseRequest`.

---

## Responsabilités

* Centraliser les comportements communs des requêtes HTTP.
* Fournir une autorisation par défaut.
* Définir une structure commune pour les règles de validation.
* Préparer les futures fonctionnalités de validation communes.

---

## Exemple d'héritage

```text
BaseRequest
│
├── CreateProductRequest
├── UpdateProductRequest
├── DeleteProductRequest
├── RegisterUserRequest
├── LoginRequest
└── CreateOrderRequest
```

---

## Architecture

```text
HTTP Request
      │
      ▼
BaseRequest
      │
      ▼
Request métier
      │
      ▼
DTO
      │
      ▼
Action
      │
      ▼
Service
```

Le `Request` ne doit jamais être transmis directement aux Services.

Les données doivent être converties en DTO avant d'entrer dans la couche métier.

---

## Dépendances autorisées

* Laravel FormRequest
* Validation Laravel
* DTO (via les contrôleurs)

---

## Dépendances interdites

* Service
* Repository
* Model
* View
* Blade
* Session
* Logique métier

---

## Règles de développement

* Toutes les requêtes métier héritent de `BaseRequest`.
* Les règles de validation sont définies dans les classes enfants.
* Les autorisations spécifiques sont gérées dans les classes enfants lorsque nécessaire.
* Aucune logique métier ne doit être écrite dans un Request.

---

## Bonnes pratiques

✔ Utiliser un Request par cas d'utilisation.

✔ Valider toutes les entrées utilisateur.

✔ Convertir les données validées en DTO avant d'appeler une Action.

✔ Garder les Request légers et spécialisés.

---

## Tests

Les tests vérifient :

* L'existence de la classe.
* Son caractère abstrait.
* Son héritage de `Illuminate\Foundation\Http\FormRequest`.

---

## Décision d'architecture

Le `Request` appartient à la couche HTTP.

Il ne doit jamais être utilisé directement dans la couche métier.

La communication entre la couche HTTP et la couche métier s'effectue exclusivement au moyen de DTO.

---

## Historique

### Version 1.0

* Création de `BaseRequest`.
* Héritage de `FormRequest`.
* Autorisation par défaut.
* Structure de validation commune.
* Premier gel officiel du composant.
