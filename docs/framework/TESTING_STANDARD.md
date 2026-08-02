# TESTING STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL

---

# Objectif

Ce document définit les standards de tests du framework **MAHLINE**.

Son objectif est de garantir que chaque composant du framework soit vérifié de manière fiable, reproductible et cohérente avant sa mise en production.

Les tests font partie intégrante du développement et sont obligatoires pour tous les composants du projet.

---

# Principes

Les tests doivent être :

* automatisés ;
* reproductibles ;
* indépendants ;
* rapides ;
* lisibles ;
* déterministes.

Chaque test doit vérifier un comportement précis.

---

# Organisation des tests

La structure officielle est la suivante :

```text id="9h74av"
tests/
├── Feature/
├── Framework/
│   ├── Unit/
│   └── Integration/
└── Unit/
```

## Description

### Framework

Tests des composants techniques du framework.

Exemples :

* BaseModel
* BaseService
* BaseAction
* BaseDTO
* BaseRequest
* BaseResource
* BasePolicy
* BaseException
* BaseValueObject

---

### Unit

Tests unitaires des composants métier.

Ils vérifient une classe isolée sans dépendances externes.

---

### Feature

Tests fonctionnels simulant des scénarios utilisateurs.

Ils valident le comportement global de l'application.

---

### Integration

Tests vérifiant la collaboration entre plusieurs composants.

Exemples :

* Service ↔ Repository
* Action ↔ Service
* API ↔ Base de données

---

# Nommage des tests

Chaque classe de test se termine par `Test`.

Exemples :

```text id="qtw6lm"
BaseModelTest
ProductServiceTest
CreateOrderActionTest
CountryRepositoryTest
```

Le fichier porte exactement le même nom que la classe.

---

# Structure d'un test

Chaque test suit le modèle **Arrange / Act / Assert**.

```text id="yb6utk"
Arrange
Act
Assert
```

## Arrange

Préparer les données.

## Act

Exécuter l'action.

## Assert

Vérifier le résultat.

---

# Règles

Chaque test doit :

* tester un seul comportement ;
* être indépendant des autres tests ;
* être facilement compréhensible ;
* ne contenir aucune logique métier.

---

# Données de test

Les données doivent être générées avec :

* Factories ;
* Seeders dédiés aux tests ;
* objets de test isolés.

Les données codées en dur doivent être limitées aux cas simples.

---

# Base de données

Les tests utilisant la base de données doivent garantir un état propre avant chaque exécution.

Les outils Laravel adaptés (par exemple, les mécanismes de rafraîchissement de la base) doivent être utilisés lorsque nécessaire.

---

# Couverture des tests

Chaque composant important doit disposer de tests.

## Foundation

* BaseModel
* BaseService
* BaseAction
* BaseDTO
* BaseRequest
* BaseResource
* BasePolicy
* BaseException
* BaseValueObject

## Domains

Chaque domaine possède ses propres tests unitaires, fonctionnels et d'intégration.

---

# Cas à tester

Les tests doivent couvrir notamment :

* les cas nominaux ;
* les cas limites ;
* les erreurs attendues ;
* les exceptions ;
* les autorisations ;
* les validations.

---

# Qualité des assertions

Les assertions doivent être :

* précises ;
* explicites ;
* minimales.

Une assertion doit vérifier un comportement observable.

---

# Performance

Les tests doivent rester rapides.

Les dépendances externes doivent être simulées (mockées ou remplacées par des doubles de test) lorsque cela est pertinent afin de limiter les temps d'exécution.

---

# Exécution

Exécuter l'ensemble des tests :

```bash id="2z24v2"
php artisan test
```

Exécuter un fichier :

```bash id="3l2pkm"
php artisan test tests/Framework/Unit/BaseServiceTest.php
```

Exécuter un répertoire :

```bash id="miv6oa"
php artisan test tests/Framework
```

---

# Critères de validation

Un composant est considéré comme terminé uniquement si :

* le développement est terminé ;
* les tests sont validés ;
* la documentation est rédigée ;
* les conventions sont respectées.

---

# Bonnes pratiques

* Écrire les tests en parallèle du développement.
* Utiliser des noms de méthodes de test explicites.
* Éviter les dépendances entre tests.
* Ne jamais ignorer un test en échec.
* Corriger les régressions avant d'ajouter de nouvelles fonctionnalités.

---

# Intégration continue

Avant chaque livraison :

* tous les tests doivent réussir ;
* aucune régression ne doit être introduite ;
* les nouveaux composants doivent être accompagnés de leurs tests.

---

# Conclusion

Les tests sont une exigence fondamentale du framework MAHLINE.

Ils garantissent la stabilité de la Foundation, sécurisent les évolutions et facilitent la maintenance du projet sur le long terme.

---

# Historique

## Version 1.0

* Définition des standards officiels de tests.
* Organisation des tests Framework, Unit, Feature et Integration.
* Adoption du modèle Arrange / Act / Assert.
* Formalisation des critères de validation des composants.
* Publication de la première version officielle.
