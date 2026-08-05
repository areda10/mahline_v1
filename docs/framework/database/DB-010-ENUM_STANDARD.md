# ENUM_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit le standard officiel de gestion des **énumérations (Enums)** dans le framework **MAHLINE**.

L'objectif est de standardiser la gestion des états, types et valeurs constantes tout en garantissant la portabilité de la base de données et la robustesse du code.

---

# Principe

Le framework **MAHLINE** utilise les **Enums PHP natifs** (PHP 8.1+) pour représenter les états métier.

Les types `ENUM` propres aux moteurs de base de données (MySQL, MariaDB, PostgreSQL, etc.) sont interdits pour les données métier.

---

# Pourquoi ne pas utiliser les ENUM SQL ?

Les `ENUM` SQL présentent plusieurs inconvénients :

* dépendance au moteur de base de données ;
* évolution plus complexe (ajout ou suppression de valeurs) ;
* migrations plus lourdes ;
* portabilité réduite ;
* logique métier déplacée dans la base de données.

Les Enums PHP offrent une meilleure maintenabilité et une meilleure intégration avec Laravel.

---

# Représentation en base de données

Les valeurs d'un Enum sont stockées dans une colonne simple.

Le type recommandé est :

```php id="g6c5dt"
$table->string('status');
```

Selon le besoin, d'autres types peuvent être utilisés (`integer`, `tinyInteger`), mais les chaînes de caractères sont privilégiées pour leur lisibilité.

---

# Exemple

## Migration

```php id="xh4vx9"
$table->string('status')->default('active');
```

## Enum PHP

```php id="k1shs8"
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
```

## Modèle

```php id="jlwmgn"
protected function casts(): array
{
    return [
        'status' => UserStatus::class,
    ];
}
```

---

# Localisation

Les Enums sont organisés par domaine.

```text id="aq6gzg"
app/
└── Domains/
    ├── Identity/
    │   └── Enums/
    │       └── UserStatus.php
    │
    ├── Catalog/
    │   └── Enums/
    │
    ├── Finance/
    │   └── Enums/
    │
    └── ...
```

Chaque domaine est responsable de ses propres Enums.

---

# Convention de nommage

Les classes d'Enum utilisent le format :

```text id="4qvwti"
<Entity><Concept>
```

Exemples :

```text id="v5v7np"
UserStatus
OrderStatus
InvoiceStatus
PaymentStatus
LanguageCode
CurrencyCode
```

Les noms doivent être explicites et représentatifs du concept métier.

---

# Valeurs

Les valeurs d'un Enum doivent être :

* stables ;
* explicites ;
* en anglais ;
* en minuscules (`snake_case` si plusieurs mots).

Exemples :

```text id="uxm96v"
active
inactive
pending
validated
cancelled
paid
draft
```

Éviter les abréviations ambiguës.

---

# Valeur par défaut

Une valeur par défaut peut être définie dans la migration lorsqu'elle correspond à un état initial métier.

Exemple :

```php id="4qmg8e"
$table->string('status')->default('active');
```

La valeur par défaut doit correspondre à une valeur définie dans l'Enum PHP.

---

# Validation

Toutes les valeurs reçues via les formulaires, API ou imports doivent être validées.

Laravel permet notamment d'utiliser la règle :

```php id="q1v3zj"
Rule::enum(UserStatus::class);
```

Aucune valeur non prévue par l'Enum ne doit être enregistrée.

---

# Évolution

L'ajout d'une nouvelle valeur dans un Enum nécessite :

* la mise à jour de la classe PHP ;
* la mise à jour des règles métier concernées ;
* l'adaptation des tests ;
* la mise à jour de la documentation.

Une migration n'est généralement pas nécessaire si la colonne reste de type `string`.

---

# Interdictions

Sont interdits :

* les `ENUM` SQL pour les données métier ;
* les chaînes de caractères codées en dur dans les services ou contrôleurs ;
* les valeurs non documentées ;
* les Enums partagés entre plusieurs domaines sans justification.

---

# Cas particuliers

Certaines listes de référence (pays, devises, langues, etc.) ne doivent pas être modélisées par des Enums lorsqu'elles sont amenées à évoluer.

Elles doivent être stockées dans des tables de référence dédiées.

---

# Validation

Avant validation d'un développement, vérifier que :

* aucun `ENUM` SQL n'est utilisé ;
* les Enums PHP sont définis dans le bon domaine ;
* les colonnes utilisent un type adapté (`string` recommandé) ;
* les valeurs sont validées via Laravel ;
* les tests couvrent les différents cas de l'Enum.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* INDEX_STANDARD.md
* AUDIT_STANDARD.md
* SOFT_DELETE_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Les Enums PHP constituent le standard officiel de gestion des états métier dans MAHLINE.

Ils garantissent une meilleure lisibilité du code, une forte cohérence entre les domaines et une indépendance vis-à-vis du moteur de base de données, tout en simplifiant les évolutions futures.

---

# Historique

## Version 1.0

* Adoption des Enums PHP comme standard officiel.
* Interdiction des `ENUM` SQL pour les entités métier.
* Définition des conventions de nommage et de stockage.
* Intégration avec les casts Eloquent et la validation Laravel.
* Publication du standard officiel des Enums du framework MAHLINE.
