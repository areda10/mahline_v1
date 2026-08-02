# BLOC-009 — BaseResource

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/Resources/BaseResource.php
```

---

## Objectif

`BaseResource` est la classe de base de toutes les Resources du framework **MAHLINE**.

Elle est responsable de transformer les objets métier (Models ou DTO) en représentations JSON destinées aux API ou aux interfaces clientes.

Toutes les Resources du projet devront hériter de `BaseResource`.

---

## Responsabilités

* Transformer les données métier en JSON.
* Fournir un point d'entrée commun pour toutes les Resources.
* Garantir une structure uniforme des réponses API.
* Préparer le framework à une architecture **API First**.

---

## Exemple d'héritage

```text
BaseResource
│
├── ProductResource
├── CategoryResource
├── OrderResource
├── InvoiceResource
├── UserResource
├── CountryResource
└── CooperativeResource
```

---

## Architecture

```text
Model / DTO
      │
      ▼
BaseResource
      │
      ▼
Resource métier
      │
      ▼
Réponse JSON
```

Les `Resource` appartiennent à la couche de présentation.

Elles ne contiennent aucune logique métier.

---

## Dépendances autorisées

* Illuminate\Http\Resources\Json\JsonResource
* Model
* DTO
* Collections

---

## Dépendances interdites

* Service
* Repository
* Action
* Request
* SQL
* Logique métier

---

## Règles de développement

* Toutes les réponses API passent par une Resource.
* Les transformations d'affichage sont réalisées dans les Resources.
* Les Models Eloquent ne sont jamais exposés directement.
* Les Resources restent simples, lisibles et spécialisées.

---

## Bonnes pratiques

✔ Une Resource par entité métier.

✔ Utiliser les Collections Laravel pour les listes.

✔ Retourner uniquement les données nécessaires.

✔ Masquer les champs internes ou sensibles.

✔ Conserver une structure JSON cohérente dans toute l'application.

---

## Tests

Les tests vérifient :

* L'existence de la classe.
* Son caractère abstrait.
* Son héritage de `JsonResource`.

---

## Décision d'architecture

MAHLINE adopte une approche **API First**.

Toutes les données sortantes transitent par une Resource afin de garantir :

* une séparation claire entre le métier et la présentation ;
* une compatibilité avec les API REST ;
* une compatibilité avec les applications mobiles ;
* une évolution simplifiée des interfaces clientes.

---

## Historique

### Version 1.0

* Création de `BaseResource`.
* Héritage de `Illuminate\Http\Resources\Json\JsonResource`.
* Préparation de l'architecture API First.
* Premier gel officiel du composant.
