# QUALITY CHECKLIST

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL

---

# Objectif

Cette checklist définit les critères de qualité obligatoires pour tous les développements du framework **MAHLINE**.

Aucun composant ne peut être considéré comme terminé tant que l'ensemble des critères applicables n'est pas satisfait.

Cette checklist s'applique aux composants du **Core**, du **Shared** et des **Domains**.

---

# Validation de l'architecture

## Structure

* [ ] Le composant est placé dans le bon dossier.
* [ ] Le namespace correspond à l'arborescence.
* [ ] Le composant respecte l'architecture officielle.
* [ ] Les dépendances sont autorisées.

---

## Responsabilités

* [ ] La classe possède une responsabilité unique.
* [ ] La logique métier est au bon endroit.
* [ ] Les responsabilités ne sont pas dupliquées.

---

# Validation du code

## Standards

* [ ] `declare(strict_types=1);` est présent.
* [ ] Les conventions PSR-1 sont respectées.
* [ ] Les conventions PSR-4 sont respectées.
* [ ] Les conventions PSR-12 sont respectées.

---

## Typage

* [ ] Toutes les propriétés sont typées.
* [ ] Tous les paramètres sont typés.
* [ ] Toutes les valeurs de retour sont typées.
* [ ] L'utilisation de `mixed` est justifiée.
* [ ] Les types nullable sont utilisés uniquement lorsque nécessaire.

---

## Lisibilité

* [ ] Les noms sont explicites.
* [ ] Les méthodes sont courtes.
* [ ] Les responsabilités sont clairement séparées.
* [ ] Le code est facilement compréhensible.

---

# Validation des composants

## Models

* [ ] Héritent de `BaseModel`.
* [ ] Utilisent les conventions du framework.
* [ ] Les relations sont correctement définies.
* [ ] Aucune logique métier complexe n'est présente.

---

## Services

* [ ] Héritent de `BaseService`.
* [ ] Ne dépendent jamais d'un `Request`.
* [ ] Contiennent uniquement la logique métier.

---

## Actions

* [ ] Héritent de `BaseAction`.
* [ ] Représentent un seul cas d'utilisation.
* [ ] Orchestrent le traitement sans contenir de logique technique inutile.

---

## DTO

* [ ] Héritent de `BaseDTO`.
* [ ] Transportent uniquement des données.
* [ ] Ne contiennent aucune logique métier.

---

## Requests

* [ ] Héritent de `BaseRequest`.
* [ ] Contiennent uniquement les règles de validation.
* [ ] Ne réalisent aucun traitement métier.

---

## Resources

* [ ] Héritent de `BaseResource`.
* [ ] Transforment les données.
* [ ] Ne contiennent aucune logique métier.

---

## Policies

* [ ] Héritent de `BasePolicy`.
* [ ] Gèrent uniquement les autorisations.
* [ ] Ne modifient jamais les données.

---

## Exceptions

* [ ] Héritent de `BaseException`.
* [ ] Représentent une erreur métier ou technique clairement identifiée.
* [ ] Sont utilisées de manière cohérente.

---

## Value Objects

* [ ] Héritent de `BaseValueObject` lorsque cela est prévu.
* [ ] Représentent un concept métier.
* [ ] Sont immutables lorsque cela est pertinent.

---

# Validation des tests

* [ ] Les tests unitaires sont présents.
* [ ] Les tests fonctionnels sont présents si nécessaire.
* [ ] Les tests d'intégration sont présents si nécessaire.
* [ ] Tous les tests réussissent.
* [ ] Aucun test n'est désactivé.

---

# Validation de la documentation

* [ ] Le composant est documenté.
* [ ] La documentation est à jour.
* [ ] Les exemples sont cohérents.
* [ ] Le numéro de version est renseigné.
* [ ] Le statut est renseigné.

---

# Validation des performances

* [ ] Aucun traitement inutile.
* [ ] Les accès à la base sont maîtrisés.
* [ ] Les boucles inutiles sont évitées.
* [ ] Les dépendances externes sont limitées.

---

# Validation de la sécurité

* [ ] Les données sont validées.
* [ ] Les autorisations sont vérifiées.
* [ ] Les exceptions sont correctement gérées.
* [ ] Les données sensibles ne sont pas exposées.

---

# Validation Git

Avant chaque fusion :

* [ ] Les tests sont au vert.
* [ ] La documentation est mise à jour.
* [ ] Les conventions sont respectées.
* [ ] Le code a été relu.
* [ ] Aucun fichier temporaire n'est présent.

---

# Validation finale

Un composant est considéré comme **terminé** uniquement si toutes les conditions suivantes sont remplies :

* [ ] Développement terminé.
* [ ] Architecture validée.
* [ ] Standards respectés.
* [ ] Tests validés.
* [ ] Documentation complète.
* [ ] Checklist validée.

Le composant peut alors être marqué comme **VALIDÉ**, puis **GELÉ** si aucune évolution immédiate n'est prévue.

---

# Cycle qualité MAHLINE

```text id="7dvpx5"
Conception
      │
      ▼
Développement
      │
      ▼
Tests
      │
      ▼
Documentation
      │
      ▼
Validation
      │
      ▼
Gel
```

Chaque étape est obligatoire avant de passer à la suivante.

---

# Conclusion

Cette checklist constitue la référence qualité du framework **MAHLINE**.

Elle garantit que chaque composant est développé selon les mêmes exigences techniques, documentaires et fonctionnelles, assurant ainsi une architecture homogène, fiable et durable.

---

# Historique

## Version 1.0

* Création de la checklist qualité officielle.
* Définition des critères de validation pour le code, les tests et la documentation.
* Formalisation du cycle qualité du framework.
* Publication de la première version officielle.
