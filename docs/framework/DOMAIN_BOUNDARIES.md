# MAHLINE — DOMAIN BOUNDARIES

**Version:** 1.1
**Status:** Proposed for Freeze
**Project:** MAHLINE_v1

---

## 1. Purpose

This document defines the official boundaries, responsibilities, ownership rules, and dependency rules of the MAHLINE application domains.

Its purpose is to prevent:

* duplicated business logic;
* misplaced models;
* circular dependencies;
* uncontrolled coupling between domains;
* progressive corruption of the architecture.

This document must be considered a mandatory architectural reference for all future development.

---

# 2. Architectural Layers

MAHLINE is organized into four principal levels:

```text
Core
  ↓
Shared
  ↓
Domains
  ↓
HTTP / Application entry points
```

Each level has a specific responsibility.

---

# 3. Core

## Responsibility

`Core` contains framework-level and technical foundations that are not specific to one business domain.

Examples:

```text
BaseModel
BaseService
BaseAction
BaseDTO
BaseRequest
BaseResource
BasePolicy
BaseException
BaseValueObject

Database helpers
Database macros
Foundation enums
Security infrastructure
Generic contracts
Generic traits
```

## Core MUST NOT contain

Business concepts such as:

```text
Product
Order
Cooperative
Invoice
Customer
Payment
```

Business rules belong to Domains.

---

# 4. Shared

## Responsibility

`Shared` contains reusable components that are genuinely shared by multiple business domains.

Examples may include:

```text
Shared Traits
Shared Value Objects
Shared Validators
Shared Rules
Shared Collections
Shared Casts
Shared generic enums
```

A component belongs in `Shared` only when at least two domains genuinely require it.

## Rule

Do not use `Shared` as a second `Core`.

If a component is technical infrastructure:

```text
Core
```

If it is a reusable business concept shared by several domains:

```text
Shared
```

If it belongs specifically to one business domain:

```text
Domain
```

---

# 5. Identity

```text
app/Domains/Identity/
```

## Responsibility

Identity owns the identity and access lifecycle of application users.

## Owned concepts

```text
User
UserStatus
Authentication
AuthenticationSession
LoginHistory
Roles
Permissions
UserRoles
API Tokens
Browser / Device authentication context
Password management
```

## Identity owns

```text
users
authentication_sessions
login_histories
roles
permissions
role_permissions
user_roles
api_tokens
```

as defined by the approved Identity specifications.

## Identity MUST NOT own

```text
Product
Order
Invoice
Payment
Cooperative
Stock
CMS content
```

---

# 6. Localization

```text
app/Domains/Localization/
```

## Responsibility

Localization owns global geographic, linguistic, monetary and timezone reference data.

## Owned concepts

```text
Country
Language
Currency
Timezone
Address
```

## Owned tables

Expected initial scope:

```text
countries
languages
currencies
timezones
addresses
```

## Consumers

Localization may be used by:

```text
Identity
Cooperative
Sales
Finance
CMS
```

Localization must remain independent from those domains.

---

# 7. Cooperative

```text
app/Domains/Cooperative/
```

## Responsibility

Cooperative represents the organizations that sell products through MAHLINE.

## Owned concepts

```text
Cooperative
CooperativeStatus
CooperativeProfile
CooperativeUser relationship
Cooperative documents
```

depending on the approved functional specification.

## Cooperative owns

```text
cooperatives
```

and other cooperative-specific tables approved by its specification.

## Cooperative MUST NOT own

```text
products
orders
payments
invoices
inventory
```

These belong to their respective domains.

## Fundamental relationship

```text
Cooperative
     │
     └── Product ownership
```

Catalog owns the Product entity.

Cooperative owns the Cooperative entity.

---

# 8. Catalog

```text
app/Domains/Catalog/
```

## Responsibility

Catalog owns the commercial product catalog.

## Owned concepts

```text
Product
ProductVariant
Category
Attribute
AttributeValue
Brand
Collection
ProductPrice
ProductStatus
```

according to the approved Catalog specification.

## Catalog owns

Expected tables:

```text
products
product_variants
categories
attributes
attribute_values
product_attributes
product_media
```

Additional tables require architectural approval.

## Relationship with Cooperative

A product may belong to a cooperative.

However:

```text
Catalog owns Product.
Cooperative owns Cooperative.
```

Neither domain absorbs the other.

---

# 9. Inventory

```text
app/Domains/Inventory/
```

## Responsibility

Inventory owns physical stock and stock movements.

## Owned concepts

```text
Inventory
InventoryItem
StockMovement
StockReservation
Warehouse
InventoryAdjustment
```

where applicable.

## Inventory owns

Expected tables:

```text
inventories
inventory_items
inventory_movements
stock_reservations
warehouses
```

## Rule

Catalog defines what a product is.

Inventory defines how many units are available.

Therefore:

```text
Catalog → Product
Inventory → Stock
```

Inventory MUST NOT duplicate product definitions.

---

# 10. Sales

```text
app/Domains/Sales/
```

## Responsibility

Sales owns the customer purchasing lifecycle.

## Owned concepts

```text
Customer
Cart
CartItem
Order
OrderItem
OrderStatus
Delivery
DeliveryStatus
```

as defined by the approved Sales specification.

## Sales owns

Expected tables:

```text
customers
carts
cart_items
orders
order_items
deliveries
```

Additional tables require specification approval.

---

# 11. Multi-Cooperative Order Rule

MAHLINE is a marketplace.

One customer order may contain products belonging to multiple cooperatives.

Example:

```text
Order #1001

Cooperative A
 ├── Product A1
 └── Product A2

Cooperative B
 ├── Product B1
 └── Product B2
```

The architecture MUST support this scenario.

The final implementation must define a stable concept for cooperative-level order segmentation before the Sales database is frozen.

Possible implementation concepts include:

```text
Order
 ├── Order
 └── CooperativeOrder / SubOrder
```

The final name must be determined by the Sales specification.

---

# 12. Finance

```text
app/Domains/Finance/
```

## Responsibility

Finance owns monetary transactions and financial documents.

## Owned concepts

```text
Invoice
InvoiceItem
Payment
PaymentTransaction
Refund
Commission
Payout
FinancialTransaction
```

as defined by the approved Finance specification.

## Finance owns

Expected scope:

```text
invoices
invoice_items
payments
payment_transactions
refunds
commissions
payouts
financial_transactions
```

---

# 13. MAHLINE Billing Rule

MAHLINE requires two financial perspectives:

```text
Customer
    ↓
Customer Invoice
```

and:

```text
Cooperative
    ↓
Cooperative Invoice
```

For a multi-cooperative order:

```text
Order
 ├── Customer financial document
 ├── Cooperative A financial document
 └── Cooperative B financial document
```

The exact document model must be defined in the Finance specification before implementation.

---

# 14. Commission

Marketplace commission belongs to:

```text
Finance
```

It must NOT be implemented inside:

```text
Catalog
Sales
Cooperative
```

Conceptually:

```text
Customer payment
       ↓
Gross amount
       ↓
Commission calculation
       ↓
MAHLINE share
       ↓
Cooperative share
```

The financial calculation rules must be defined in Finance.

---

# 15. Communication

```text
app/Domains/Communication/
```

## Responsibility

Communication owns outbound communication.

## Owned concepts

```text
Email
Notification
Message
Template
DeliveryLog
```

where applicable.

Communication handles:

```text
Email delivery
Notifications
Communication templates
Queue-based communication
Delivery tracking
```

Communication MUST NOT own the business entity that triggered the communication.

Example:

```text
Finance
   ↓
Invoice generated
   ↓
Communication
   ↓
Invoice email sent
```

---

# 16. Media

```text
app/Domains/Media/
```

## Responsibility

Media owns files and media assets used by multiple domains.

Examples:

```text
Product images
Cooperative documents
CMS images
Marketing media
```

## Rule

Catalog MUST NOT implement its own independent media storage system.

CMS MUST NOT implement another independent media storage system.

Instead:

```text
Catalog ─────┐
Cooperative ─┼──→ Media
CMS ─────────┘
```

Media owns the media asset.

The consuming domain owns the business meaning of that asset.

---

# 17. Audit

```text
app/Domains/Audit/
```

## Responsibility

Audit owns business-level audit history.

It answers:

```text
Who?
What?
When?
Which entity?
Before?
After?
Why?
From which session?
```

Audit is different from technical database audit columns.

Technical audit:

```text
created_at
updated_at
created_by
updated_by
deleted_at
deleted_by
```

Business audit:

```text
User changed product price
User changed cooperative status
Administrator refunded payment
```

Audit must remain cross-domain.

---

# 18. CMS

```text
app/Domains/CMS/
```

## Responsibility

CMS owns public and administrative content.

## Owned concepts

```text
Page
Section
Banner
Menu
FAQ
SEO metadata
ContentBlock
```

CMS may consume:

```text
Media
Localization
```

but must not own their entities.

---

# 19. Dependency Rules

## Allowed dependency direction

```text
Core
  ↑
Shared
  ↑
Domains
```

More precisely:

```text
Domain
   ↓
Core contracts / Shared contracts
```

Domains should not depend on implementation details of other domains.

---

# 20. Domain-to-Domain Communication

When one domain needs information from another domain, prefer:

```text
Contract
Interface
DTO
Value Object
Domain Event
Application Event
```

over direct dependency on another domain's internal service.

Example:

```text
Sales
  ↓
Cooperative contract
  ↓
Cooperative
```

rather than:

```text
Sales
  ↓
CooperativeService::someInternalMethod()
```

---

# 21. Forbidden Ownership

The following ownership is forbidden:

```text
Catalog owns Cooperative
Catalog owns User
Catalog owns Invoice

Cooperative owns Product
Cooperative owns Order
Cooperative owns Payment

Sales owns Invoice
Sales owns Payment

Finance owns Product
Finance owns User

Communication owns Invoice
Communication owns Product

CMS owns Product
CMS owns Cooperative

Inventory owns Product definition
```

The domain may reference another domain's concepts without owning them.

---

# 22. Ownership Matrix

| Concept        | Owner         |
| -------------- | ------------- |
| User           | Identity      |
| UserStatus     | Identity      |
| Authentication | Identity      |
| Session        | Identity      |
| LoginHistory   | Identity      |
| Role           | Identity      |
| Permission     | Identity      |
| API Token      | Identity      |
| Country        | Localization  |
| Language       | Localization  |
| Currency       | Localization  |
| Timezone       | Localization  |
| Address        | Localization  |
| Cooperative    | Cooperative   |
| Product        | Catalog       |
| ProductVariant | Catalog       |
| Category       | Catalog       |
| Attribute      | Catalog       |
| Stock          | Inventory     |
| StockMovement  | Inventory     |
| Customer       | Sales         |
| Cart           | Sales         |
| Order          | Sales         |
| OrderItem      | Sales         |
| Delivery       | Sales         |
| Invoice        | Finance       |
| Payment        | Finance       |
| Refund         | Finance       |
| Commission     | Finance       |
| Payout         | Finance       |
| Email          | Communication |
| Notification   | Communication |
| MediaAsset     | Media         |
| Business Audit | Audit         |
| Page           | CMS           |
| Banner         | CMS           |
| Menu           | CMS           |

---

# 23. Dependency Matrix

| From ↓ / To → | Identity | Localization | Cooperative | Catalog | Inventory | Sales | Finance | Communication | Media | Audit | CMS |
| ------------- | -------: | -----------: | ----------: | ------: | --------: | ----: | ------: | ------------: | ----: | ----: | --: |
| Identity      |        — |            ✓ |           — |       — |         — |     — |       — |             — |     — |     ✓ |   — |
| Localization  |        — |            — |           — |       — |         — |     — |       — |             — |     — |     — |   — |
| Cooperative   |        ✓ |            ✓ |           — |       ✓ |         — |     — |       — |             — |     ✓ |     ✓ |   — |
| Catalog       |        — |            ✓ |           ✓ |       — |         ✓ |     — |       — |             — |     ✓ |     ✓ |   — |
| Inventory     |        — |            — |           ✓ |       ✓ |         — |     ✓ |       — |             — |     — |     ✓ |   — |
| Sales         |        ✓ |            ✓ |           ✓ |       ✓ |         ✓ |     — |       ✓ |             — |     — |     ✓ |   — |
| Finance       |        ✓ |            ✓ |           ✓ |       ✓ |         — |     ✓ |       — |             ✓ |     — |     ✓ |   — |
| Communication |        ✓ |            ✓ |           — |       — |         — |     ✓ |       ✓ |             — |     ✓ |     ✓ |   ✓ |
| Media         |        — |            — |           — |       — |         — |     — |       — |             — |     — |     ✓ |   — |
| Audit         |        ✓ |            ✓ |           ✓ |       ✓ |         ✓ |     ✓ |       ✓ |             ✓ |     ✓ |     — |   ✓ |
| CMS           |        — |            ✓ |           — |       ✓ |         — |     — |       — |             ✓ |     ✓ |     ✓ |   — |

The matrix represents logical interaction, not permission to access another domain's internal implementation.

---

# 24. Controllers

Controllers are application entry points.

Controllers MUST NOT contain substantial business logic.

Expected flow:

```text
HTTP Request
    ↓
Request
    ↓
Controller
    ↓
Action / Service
    ↓
Domain
    ↓
Repository / Model
```

---

# 25. Models

A model belongs to the domain that owns the underlying business entity.

Examples:

```text
User
→ Identity

Product
→ Catalog

Cooperative
→ Cooperative

Order
→ Sales

Invoice
→ Finance
```

Models must not be duplicated across domains.

---

# 26. Repositories

Repositories belong to the domain owning the entity.

Example:

```text
Catalog/
└── Repositories/
    └── ProductRepository.php
```

not:

```text
Sales/
└── Repositories/
    └── ProductRepository.php
```

when Product belongs to Catalog.

---

# 27. Services

A service must belong to the domain owning the business operation.

Services MUST NOT become generic dumping grounds.

A service must not depend on:

```php
Illuminate\Http\Request
```

for domain operations.

HTTP context must be converted into DTOs/context objects before entering domain logic.

---

# 28. Events

Events may be used for cross-domain communication.

Example:

```text
Sales
  ↓
OrderPlaced
  ↓
Inventory
  ↓
StockReserved
```

or:

```text
Finance
  ↓
InvoiceGenerated
  ↓
Communication
  ↓
InvoiceEmailQueued
```

Events must contain stable contracts and must not expose unnecessary internal implementation details.

---

# 29. Architecture Freeze Rule

Once a domain reaches:

```text
Specification
    ↓
Database
    ↓
Implementation
    ↓
Tests
    ↓
Audit
```

and all tests pass, it may be marked:

```text
DOMAIN FROZEN
```

Changing ownership afterward requires an architectural decision record.

---

# 30. Current Implementation Status

## Frozen

```text
Core Foundation             ✅
Database Foundation         ✅
Identity Users              ✅
Identity Authentication     ✅
Identity Sessions           ✅
Identity Login History      ✅
Password / Hashing          ✅
```

## Next

```text
Identity Authorization      ⏳
Identity API Tokens         ⏳
Shared                       ⏳
Media                       ⏳
Localization                ⏳
Cooperative                 ⏳
Catalog                     ⏳
Inventory                   ⏳
Sales                       ⏳
Finance                     ⏳
Communication               ⏳
Audit                       ⏳
CMS                         ⏳
```

---

# 31. Mandatory Development Order

The official implementation order is:

```text
1. Identity Authorization
2. Identity API Tokens
3. Shared
4. Media
5. Localization
6. Cooperative
7. Catalog
8. Inventory
9. Sales
10. Finance
11. Communication
12. Audit
13. CMS
14. API
15. Frontend
16. Final Integration
```

No later domain should be implemented by bypassing the ownership rules defined here.

---

# 32. Final Principle

MAHLINE follows one fundamental architectural principle:

> **Every business concept has one authoritative owner.**

A domain may consume another domain's concepts, but it must never silently become their owner.

This rule exists to keep MAHLINE maintainable, testable, scalable and evolvable.

---

## Status

**Document status:** Proposed for Freeze

**Next architectural action:**

Validate this document before starting Identity Authorization.
