# ULID_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit le standard officiel d'utilisation des **ULID (Universally Unique Lexicographically Sortable Identifier)** dans le framework **MAHLINE**.

Tous les identifiants des entités métier doivent respecter ce standard afin d'assurer une architecture cohérente, performante et évolutive.

---

# Principe

Toutes les entités métier du framework utilisent un **ULID** comme clé primaire.

Les identifiants numériques auto-incrémentés (`AUTO_INCREMENT`, `BIGINT`) ne sont pas utilisés pour les tables métier.

Exemple :

```php
$table->ulid('id')->primary();
```

---

# Pourquoi les ULID ?

Les ULID offrent plusieurs avantages :

* Identifiant unique à l'échelle mondiale.
* Tri lexicographique par date de création.
* Compatibilité avec les architectures distribuées.
* Génération sans accès préalable à la base de données.
* Réduction des risques de collision.
* Adapté aux API, aux microservices et aux synchronisations.

---

# Champ d'application

Le standard ULID s'applique à :

* toutes les tables métier ;
* toutes les clés primaires ;
* toutes les clés étrangères référant une entité métier ;
* tous les modèles Eloquent correspondants.

Les tables techniques gérées par Laravel peuvent utiliser leur structure native si cela est imposé par le framework.

---

# Clé primaire

Toutes les tables métier utilisent :

```php
$table->ulid('id')->primary();
```

Exemple :

```php
Schema::create('users', function (Blueprint $table) {
    $table->ulid('id')->primary();
});
```

---

# Clés étrangères

Les relations utilisent également des ULID.

Exemple :

```php
$table->foreignUlid('user_id');
```

Puis :

```php
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->cascadeOnUpdate()
    ->restrictOnDelete();
```

Toutes les relations doivent être cohérentes avec ce standard.

---

# Configuration des modèles

Tous les modèles héritant de `BaseModel` ou `BaseAuthenticatable` doivent être configurés pour utiliser des ULID.

Exemple :

```php
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class User extends BaseAuthenticatable
{
    use HasUlids;
}
```

Le modèle doit également être non auto-incrémenté et utiliser une clé de type chaîne.

---

# Génération

Les ULID sont générés automatiquement par Laravel via le trait `HasUlids`.

Aucune génération manuelle ne doit être implémentée sauf cas exceptionnel documenté.

---

# Format

Un ULID est composé de 26 caractères alphanumériques.

Exemple :

```text
01K1T9D7W6E3F6R5YQ8J9N4X2A
```

Les développeurs ne doivent pas faire d'hypothèses sur sa structure interne.

---

# Avantages fonctionnels

L'utilisation des ULID permet :

* de créer des entités avant leur persistance ;
* de faciliter les échanges entre systèmes ;
* d'éviter l'exposition du volume de données (contrairement aux identifiants séquentiels) ;
* de simplifier les importations et les fusions de données.

---

# Indexation

Les colonnes ULID utilisées comme clés primaires ou étrangères doivent être indexées.

Les clés primaires le sont automatiquement.

Les clés étrangères doivent être indexées selon les besoins fonctionnels et les performances attendues.

---

# Import / Export

Les ULID doivent être conservés lors :

* des exports ;
* des imports ;
* des sauvegardes ;
* des restaurations ;
* des synchronisations entre environnements.

Ils ne doivent jamais être régénérés pendant ces opérations.

---

# API

Les API exposent les ULID comme identifiants publics.

Les applications clientes ne doivent jamais supposer un ordre numérique ni tenter de les convertir.

---

# Sécurité

Les ULID ne remplacent pas les mécanismes d'autorisation.

Ils réduisent toutefois le risque d'énumération des ressources par rapport aux identifiants séquentiels.

Toutes les vérifications d'accès restent obligatoires.

---

# Exceptions

Les tables techniques fournies par Laravel (par exemple `jobs`, `failed_jobs`, `cache`, `sessions`) peuvent conserver leur structure d'origine si cela est recommandé par le framework.

Toute exception doit être documentée.

---

# Interdictions

Sont interdits :

* `AUTO_INCREMENT` pour les entités métier ;
* `foreignId()` vers une entité métier utilisant un ULID ;
* la génération manuelle d'ULID sans justification ;
* la conversion d'un ULID en entier.

---

# Validation

Avant validation d'une migration ou d'un modèle, vérifier que :

* toutes les clés primaires métier utilisent `ulid()`;
* toutes les relations utilisent `foreignUlid()`;
* les modèles utilisent le trait `HasUlids`;
* les identifiants sont de type chaîne ;
* aucune clé auto-incrémentée n'est utilisée pour une entité métier.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* INDEX_STANDARD.md
* AUDIT_STANDARD.md
* SOFT_DELETE_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

L'utilisation systématique des ULID constitue un standard fondamental du framework MAHLINE.

Elle garantit des identifiants uniques, stables et adaptés aux architectures modernes, tout en renforçant la cohérence de l'ensemble des domaines métier.

---

# Historique

## Version 1.0

* Adoption officielle des ULID comme identifiant unique des entités métier.
* Standardisation des clés primaires et étrangères.
* Intégration avec les modèles Laravel via `HasUlids`.
* Définition des règles d'utilisation, de validation et des exceptions.
