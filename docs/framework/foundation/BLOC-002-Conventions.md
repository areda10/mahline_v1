# BLOC-002 — Conventions de développement

## Version

1.0

## Statut

GELÉ

---

## Objectif

Ce document définit les conventions de développement officielles du framework **MAHLINE**.

L'objectif est de garantir une base de code homogène, lisible, maintenable et évolutive, quel que soit le nombre de développeurs participant au projet.

Toutes les nouvelles classes, tous les nouveaux domaines et toutes les nouvelles fonctionnalités devront respecter ces conventions.

---

## Standards

MAHLINE respecte les standards suivants :

* PSR-1 — Basic Coding Standard
* PSR-4 — Autoloading Standard
* PSR-12 — Extended Coding Style
* PHP 8.3+

---

## Déclaration stricte

Tous les fichiers PHP doivent commencer par :

```php
<?php

declare(strict_types=1);
```

L'utilisation de `strict_types` est obligatoire.

---

## Structure des classes

Chaque fichier contient une seule classe.

Une classe correspond à un seul fichier.

Le nom du fichier doit être identique au nom de la classe.

Exemples :

```text
BaseDTO.php
BaseRequest.php
ProductService.php
CreateOrderAction.php
CountryRepository.php
```

---

## Namespaces

Les namespaces doivent refléter exactement l'arborescence du projet.

Exemple :

```text
app/Core/Foundation/Services/BaseService.php
```

```php
namespace App\Core\Foundation\Services;
```

---

## Typage

Toutes les méthodes doivent définir :

* les types des paramètres ;
* le type de retour.

Exemple :

```php
public function execute(ProductDTO $dto): Product
```

L'utilisation de `mixed` est interdite sauf justification exceptionnelle.

---

## Documentation

Toutes les classes publiques doivent être documentées avec un PHPDoc.

Les méthodes complexes doivent être documentées lorsque cela améliore la compréhension.

---

## Responsabilité unique

Chaque classe possède une seule responsabilité.

Exemples :

* Une Action représente un cas d'utilisation.
* Un Service contient la logique métier.
* Un DTO transporte des données.
* Une Policy gère les autorisations.
* Une Resource transforme les données.

---

## Dépendances

Les dépendances doivent respecter l'architecture officielle.

Exemple :

```text
Controller
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

Les Services ne dépendent jamais directement des `Request`.

---

## Immutabilité

Les DTO et les Value Objects sont conçus pour être immutables lorsque cela est pertinent.

Ils ne doivent pas être utilisés comme objets de stockage d'état.

---

## Exceptions

Toutes les exceptions métier héritent de `BaseException`.

Les exceptions natives de PHP ne sont utilisées que lorsqu'elles sont réellement adaptées.

---

## Tests

Chaque composant doit être accompagné de tests.

Aucun composant Foundation n'est considéré comme terminé sans :

* développement ;
* validation ;
* tests ;
* documentation.

---

## Nommage

Les noms doivent être explicites.

Exemples :

```text
CreateProductAction
UpdateOrderAction
InvoiceService
Money
Email
ProductRepository
CountryResource
```

Les abréviations inutiles sont interdites.

---

## Conventions des acronymes

Les acronymes conservent leur casse dans les noms de classes et de fichiers.

Exemples :

```text
BaseDTO.php
BaseURL.php
APIClient.php
XMLParser.php
PDFGenerator.php
ULIDGenerator.php
```

Cette règle garantit la compatibilité avec PSR-4.

---

## Qualité du code

Le code doit privilégier :

* la simplicité ;
* la lisibilité ;
* la cohérence ;
* la réutilisabilité ;
* la testabilité.

Les optimisations prématurées doivent être évitées.

---

## Décision d'architecture

La qualité du framework repose sur des conventions communes appliquées de manière systématique.

Le respect de ces conventions est obligatoire pour tous les composants de MAHLINE.

---

## Historique

### Version 1.0

* Définition des standards de développement.
* Adoption de PSR-12.
* Généralisation du typage strict.
* Mise en place des conventions de nommage.
* Premier gel officiel des conventions de développement.
