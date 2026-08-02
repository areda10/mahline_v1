# BLOC-012 — BaseValueObject

## Version

1.0

## Statut

GELÉ

---

## Emplacement

```text
app/Core/Foundation/ValueObjects/BaseValueObject.php
```

---

## Objectif

`BaseValueObject` est la classe de base de tous les **Value Objects** du framework **MAHLINE**.

Un Value Object représente une **valeur métier** sans identité propre. Deux Value Objects sont considérés égaux lorsqu'ils contiennent les mêmes valeurs.

Les Value Objects permettent de modéliser les concepts métier de manière plus expressive, plus sûre et plus facilement testable.

---

## Responsabilités

* Représenter une valeur métier.
* Garantir l'immuabilité des données lorsque cela est approprié.
* Encapsuler les règles propres à une valeur métier.
* Améliorer la lisibilité et la robustesse du domaine métier.

---

## Exemple d'héritage

```text
BaseValueObject
│
├── Money
├── Email
├── PhoneNumber
├── Address
├── Currency
├── TaxRate
├── Percentage
├── Weight
├── Dimensions
└── Quantity
```

---

## Architecture

```text
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
Value Object
```

Les Value Objects appartiennent au **cœur du domaine métier**.

Ils peuvent être utilisés dans les DTO, les Services, les Actions, les Entités et les Models.

---

## Dépendances autorisées

* Types PHP
* Enums
* Autres Value Objects (si nécessaire)

---

## Dépendances interdites

* Request
* Response
* Controller
* Repository
* Resource
* Session
* Base de données
* Appels HTTP

---

## Règles de développement

* Un Value Object représente un seul concept métier.
* Il ne possède pas d'identifiant (ID, ULID, UUID...).
* Il est conçu pour être immutable lorsque cela est pertinent.
* Toute validation propre à la valeur peut être encapsulée dans le Value Object.
* Les Value Objects ne contiennent pas de logique d'accès aux données.

---

## Bonnes pratiques

✔ Donner des noms métier explicites (`Money`, `Email`, `PhoneNumber`, etc.).

✔ Préférer plusieurs petits Value Objects spécialisés plutôt qu'un objet générique.

✔ Réutiliser les Value Objects dans plusieurs domaines lorsque cela est pertinent.

✔ Éviter toute dépendance à Laravel afin de préserver leur indépendance.

---

## Tests

Les tests vérifient :

* L'existence de la classe.
* Son caractère abstrait.

Chaque Value Object concret devra ensuite disposer de ses propres tests de validation et de comportement.

---

## Décision d'architecture

Les Value Objects constituent une brique essentielle du modèle de domaine de MAHLINE.

Ils permettent d'exprimer le langage métier directement dans le code, de limiter les erreurs de manipulation des données et d'améliorer la qualité globale de l'application.

Les Value Objects sont indépendants de Laravel et peuvent être réutilisés dans n'importe quel domaine métier.

---

## Historique

### Version 1.0

* Création de `BaseValueObject`.
* Définition de la classe de base des Value Objects.
* Préparation de la modélisation métier du framework.
* Premier gel officiel du composant.

---

## Conclusion

Avec `BaseValueObject`, la **Foundation V1.0** de MAHLINE est désormais complète.

Elle fournit un socle cohérent composé des éléments fondamentaux suivants :

* BaseModel
* BaseService
* BaseAction
* BaseDTO
* BaseRequest
* BaseResource
* BasePolicy
* BaseException
* BaseValueObject

Cette Foundation constitue la base officielle sur laquelle seront développés tous les domaines métier du framework MAHLINE.
