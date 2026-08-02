# NAMING CONVENTIONS

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL

---

# Objectif

Ce document définit les conventions de nommage du framework **MAHLINE**.

L'objectif est d'assurer une nomenclature cohérente, prévisible et uniforme pour l'ensemble du projet.

Toutes les classes, fichiers, méthodes, propriétés, variables et espaces de noms doivent respecter ces conventions.

---

# Principes généraux

Les noms doivent être :

* explicites ;
* cohérents ;
* lisibles ;
* orientés métier ;
* en anglais pour le code source.

Les noms abrégés ou ambigus sont interdits.

---

# Langue

## Code

Le code source est entièrement rédigé en **anglais**.

Exemples :

```text id="5kxy6e"
Product
Order
Invoice
Country
User
Money
CreateOrderAction
```

---

## Documentation

La documentation peut être rédigée en français ou en anglais.

Les noms des classes, méthodes et fichiers restent toujours en anglais.

---

# Classes

Toutes les classes utilisent le **PascalCase**.

Exemples :

```text id="g3y64v"
Product
Order
Invoice
Country
Email
Money
```

---

# Interfaces

Les interfaces utilisent le suffixe `Interface`.

Exemples :

```text id="5eycmv"
RepositoryInterface
PaymentGatewayInterface
LoggerInterface
```

---

# Traits

Les Traits commencent par `Has` ou décrivent clairement le comportement.

Exemples :

```text id="g88g6q"
HasAudit
HasMedia
HasSlug
HasTranslations
LogsActivity
```

---

# Enums

Les Enums utilisent le suffixe `Enum`.

Exemples :

```text id="1kag3b"
OrderStatusEnum
PaymentStatusEnum
CountryCodeEnum
```

---

# Exceptions

Toutes les exceptions se terminent par `Exception`.

Exemples :

```text id="lszv8n"
ProductNotFoundException
UnauthorizedActionException
PaymentException
```

---

# Value Objects

Les Value Objects portent le nom du concept métier.

Exemples :

```text id="r1u1ci"
Money
Email
PhoneNumber
Address
Currency
Percentage
```

Le suffixe `ValueObject` est inutile.

---

# Actions

Toutes les Actions se terminent par `Action`.

Les noms commencent par un verbe.

Exemples :

```text id="lp2ht6"
CreateProductAction
UpdateOrderAction
DeleteCategoryAction
GenerateInvoiceAction
```

---

# Services

Toutes les classes métier se terminent par `Service`.

Exemples :

```text id="pbbw44"
ProductService
InvoiceService
PaymentService
NotificationService
```

---

# Repositories

Les Repositories se terminent par `Repository`.

Exemples :

```text id="jvxtlo"
ProductRepository
CountryRepository
OrderRepository
```

---

# DTO

Les DTO se terminent par `DTO`.

Exemples :

```text id="hnq0eu"
ProductDTO
CreateOrderDTO
InvoiceDTO
```

---

# Requests

Les Requests décrivent l'action de validation.

Exemples :

```text id="j9h7w6"
StoreProductRequest
UpdateOrderRequest
LoginRequest
```

---

# Resources

Les Resources représentent une ressource exposée.

Exemples :

```text id="xzh1ec"
ProductResource
OrderResource
InvoiceResource
```

---

# Policies

Les Policies utilisent le nom de la ressource suivi de `Policy`.

Exemples :

```text id="83u5mf"
ProductPolicy
OrderPolicy
UserPolicy
```

---

# Models

Les Models utilisent le nom de l'entité métier.

Exemples :

```text id="cag1zz"
User
Product
Order
Invoice
Country
```

Éviter les préfixes ou suffixes inutiles.

---

# Méthodes

Les méthodes utilisent le **camelCase**.

Le nom commence par un verbe.

Exemples :

```text id="5twqag"
create()
update()
delete()
findById()
calculateTotal()
sendInvoice()
```

---

# Variables

Les variables utilisent le **camelCase**.

Exemples :

```text id="xkys2z"
$product
$order
$invoiceNumber
$totalAmount
$countryCode
```

---

# Constantes

Les constantes utilisent le format **UPPER_SNAKE_CASE**.

Exemples :

```text id="7bpkxv"
DEFAULT_CURRENCY
MAX_UPLOAD_SIZE
CACHE_TTL
```

---

# Fichiers

Le nom du fichier est identique au nom de la classe.

Exemples :

```text id="4gjg7k"
Product.php
ProductService.php
ProductRepository.php
ProductDTO.php
```

---

# Dossiers

Les dossiers utilisent le **PascalCase**.

Exemples :

```text id="cjlwmj"
Core
Domains
Shared
Foundation
Catalog
Identity
Localization
```

---

# Base de données

## Tables

Les tables utilisent le **snake_case** au pluriel.

Exemples :

```text id="3k6ap8"
users
products
orders
countries
invoice_items
```

---

## Colonnes

Les colonnes utilisent le **snake_case**.

Exemples :

```text id="qfphic"
created_at
updated_at
deleted_at
created_by
country_code
total_amount
```

---

# Routes

Les URI utilisent le **kebab-case**.

Exemples :

```text id="b67nxt"
/products
/product-categories
/customer-orders
```

---

# Tests

Les classes de tests utilisent le suffixe `Test`.

Exemples :

```text id="sw4of2"
ProductServiceTest
BaseModelTest
CreateOrderActionTest
```

---

# Acronymes

Les acronymes conservent leur casse habituelle dans les noms de classes.

Exemples :

```text id="hhrzoc"
APIClient
PDFGenerator
XMLParser
HTMLSanitizer
URLBuilder
BaseDTO
ULIDGenerator
```

Les fichiers portent exactement le même nom que la classe afin de respecter PSR-4.

---

# Noms interdits

Éviter les noms vagues ou génériques.

Exemples à éviter :

```text id="rcx7sr"
Manager
Helper
Data
Util
Object
Misc
Temp
Test1
MyClass
```

Préférer des noms reflétant clairement la responsabilité métier.

---

# Conclusion

Une convention de nommage uniforme améliore la lisibilité du code, facilite la navigation dans le projet et réduit les ambiguïtés.

Toutes les nouvelles classes et tous les nouveaux composants du framework MAHLINE doivent respecter ce document.

---

# Historique

## Version 1.0

* Définition des conventions officielles de nommage.
* Harmonisation des suffixes des composants.
* Standardisation des noms de classes, méthodes, fichiers, dossiers et tables.
* Publication de la première version officielle.
