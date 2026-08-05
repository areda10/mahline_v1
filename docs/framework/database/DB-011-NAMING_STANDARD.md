# NAMING_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les conventions officielles de nommage de la base de données du framework **MAHLINE**.

L'objectif est de garantir une nomenclature homogène, lisible et prévisible pour l'ensemble des tables, colonnes, index, contraintes et migrations.

---

# Principes

Toutes les conventions de nommage doivent être :

* cohérentes ;
* explicites ;
* prévisibles ;
* indépendantes du domaine métier.

Aucune abréviation ambiguë n'est autorisée.

---

# Tables

## Convention

Les tables utilisent :

* minuscules ;
* snake_case ;
* pluriel.

### Correct

```text id="d8r2ea"
users
products
orders
invoice_items
payment_methods
countries
```

### Incorrect

```text id="z9p5u1"
User
Product
tbl_users
USERS
orderItem
```

---

# Colonnes

Les colonnes utilisent :

* minuscules ;
* snake_case ;
* noms explicites.

### Correct

```text id="syxg6v"
first_name
last_name
phone_number
created_at
deleted_at
country_id
```

### Incorrect

```text id="eh5p8m"
fname
ln
tel
phone1
ctry
```

---

# Clés primaires

Toutes les tables métier utilisent :

```text id="x2m7rp"
id
```

Le type est toujours **ULID**.

---

# Clés étrangères

Les clés étrangères suivent la règle :

```text id="qw7kts"
<entité>_id
```

### Exemples

```text id="bp8mju"
user_id
role_id
country_id
product_id
invoice_id
```

---

# Tables pivot

Les tables pivot utilisent le nom des deux entités au singulier, triées par ordre alphabétique et séparées par un underscore.

### Exemples

```text id="t0o4yd"
permission_role
permission_user
product_category
role_user
```

Si la table pivot devient une entité métier (avec des attributs fonctionnels importants), elle doit être renommée selon son rôle métier.

---

# Index

Lorsque le nom est fourni explicitement, la convention suivante est utilisée :

```text id="8i2gnj"
idx_<table>_<colonnes>
```

### Exemples

```text id="5gk0rc"
idx_users_email

idx_orders_status

idx_products_category_id

idx_orders_status_created_at
```

---

# Contraintes uniques

Convention :

```text id="d5u7ke"
uniq_<table>_<colonnes>
```

### Exemple

```text id="tt5ygf"
uniq_users_email
```

---

# Clés étrangères (nom de contrainte)

Convention :

```text id="0f9u7w"
fk_<table>_<colonne>
```

### Exemple

```text id="x9m6pd"
fk_orders_user_id

fk_products_category_id
```

---

# Migrations

Les migrations suivent les conventions Laravel.

### Création

```text id="g1km8s"
create_users_table

create_products_table

create_orders_table
```

### Modification

```text id="lbv5ah"
add_status_to_orders_table

add_avatar_to_users_table

remove_logo_from_cooperatives_table
```

### Renommage

```text id="rvvcc9"
rename_product_code_column

rename_orders_table
```

---

# Enum PHP

Les Enums utilisent :

```text id="gjlwm1"
<Entity><Concept>
```

### Exemples

```text id="85v9qv"
UserStatus

OrderStatus

InvoiceStatus

PaymentStatus
```

---

# Modèles

Les modèles utilisent :

* PascalCase ;
* singulier.

### Exemples

```text id="yo2pvf"
User

Product

Order

Invoice
```

---

# Repositories

Convention :

```text id="ef3d0w"
UserRepository

ProductRepository

OrderRepository
```

---

# Services

Convention :

```text id="ap4wqz"
UserService

OrderService

CatalogService
```

---

# Actions

Convention :

```text id="6kngzb"
CreateUserAction

UpdateProductAction

DeleteOrderAction

ApproveInvoiceAction
```

Chaque action représente une seule opération métier.

---

# DTO

Convention :

```text id="8mb0zs"
UserData

ProductData

OrderData
```

Ou, selon le besoin :

```text id="vf4p2z"
CreateUserData

UpdateUserData
```

---

# Requests

Convention :

```text id="0o7twy"
StoreUserRequest

UpdateUserRequest

LoginRequest
```

---

# Resources

Convention :

```text id="fjlwm5"
UserResource

ProductResource

OrderResource
```

---

# Policies

Convention :

```text id="bbm8r7"
UserPolicy

OrderPolicy

InvoicePolicy
```

---

# Tests

Les classes de test suivent la classe testée.

### Exemples

```text id="szmf9q"
UserServiceTest

CreateUserActionTest

ProductRepositoryTest
```

---

# Documentation

Les documents utilisent des noms explicites en majuscules avec des underscores.

### Exemples

```text id="sp4qzy"
DATABASE_ARCHITECTURE.md

MIGRATION_STANDARD.md

AUDIT_STANDARD.md

QUALITY_CHECKLIST.md
```

---

# Interdictions

Sont interdits :

* espaces dans les noms ;
* accents ;
* caractères spéciaux ;
* abréviations ambiguës ;
* préfixes techniques (`tbl_`, `db_`, etc.) ;
* mélange de plusieurs conventions dans un même projet.

---

# Validation

Avant validation d'un développement, vérifier que :

* les tables sont au pluriel ;
* les modèles sont au singulier ;
* les colonnes utilisent `snake_case` ;
* les classes utilisent `PascalCase` ;
* les clés étrangères suivent la convention `<entité>_id` ;
* les migrations sont correctement nommées ;
* les index et contraintes respectent les conventions officielles.

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
* ENUM_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

Une convention de nommage uniforme est essentielle à la qualité du framework MAHLINE.

Elle facilite la lecture du code, les revues techniques, la maintenance et l'intégration de nouveaux développeurs, tout en assurant une cohérence durable entre tous les domaines du projet.

---

# Historique

## Version 1.0

* Définition des conventions officielles de nommage.
* Standardisation des tables, colonnes, modèles, services, actions et migrations.
* Adoption des conventions Laravel lorsque pertinentes.
* Publication du standard officiel de nommage du framework MAHLINE.
