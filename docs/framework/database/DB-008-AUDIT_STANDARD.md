# AUDIT_STANDARD

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit le standard officiel d'audit de la base de données du framework **MAHLINE**.

L'objectif est d'assurer une traçabilité complète des opérations effectuées sur les données métier, afin de répondre aux besoins de sécurité, de conformité, de maintenance et d'analyse.

---

# Principes

Toutes les entités métier doivent être auditables.

L'audit doit permettre de répondre aux questions suivantes :

* Qui a créé la donnée ?
* Qui l'a modifiée ?
* Qui l'a supprimée ?
* Quand ces actions ont-elles eu lieu ?
* Quel utilisateur était connecté ?
* Depuis quel navigateur et quelle session l'action a-t-elle été réalisée (lorsque ces informations sont disponibles) ?

L'audit ne doit jamais modifier le comportement métier de l'application.

---

# Colonnes d'audit

Par défaut, toutes les tables métier contiennent :

```php
$table->foreignUlid('created_by')->nullable();
$table->foreignUlid('updated_by')->nullable();
$table->foreignUlid('deleted_by')->nullable();

$table->timestamps();
$table->softDeletes();
```

Ce qui produit les colonnes suivantes :

```text
created_by
updated_by
deleted_by
created_at
updated_at
deleted_at
```

---

# Signification

| Colonne      | Description                                             |
| ------------ | ------------------------------------------------------- |
| `created_by` | Utilisateur ayant créé l'enregistrement                 |
| `updated_by` | Dernier utilisateur ayant modifié l'enregistrement      |
| `deleted_by` | Utilisateur ayant supprimé logiquement l'enregistrement |
| `created_at` | Date et heure de création                               |
| `updated_at` | Date et heure de la dernière modification               |
| `deleted_at` | Date et heure de suppression logique                    |

---

# Clés étrangères

Les colonnes d'audit référencent la table `users`.

Exemple :

```php
$table->foreign('created_by')
    ->references('id')
    ->on('users')
    ->cascadeOnUpdate()
    ->nullOnDelete();
```

Le même principe s'applique à `updated_by` et `deleted_by`.

---

# Remplissage automatique

Les colonnes d'audit sont alimentées automatiquement par la couche Foundation (par exemple `BaseModel` et les événements Eloquent).

Les développeurs ne doivent pas renseigner ces colonnes manuellement, sauf cas exceptionnel documenté.

---

# Tables concernées

L'audit est obligatoire pour :

* utilisateurs ;
* coopératives ;
* produits ;
* catégories ;
* commandes ;
* factures ;
* paiements ;
* stocks ;
* médias ;
* et toute autre entité métier.

---

# Tables exemptées

Les tables suivantes peuvent être exemptées :

* tables pivot simples ;
* tables techniques Laravel (`cache`, `jobs`, `sessions`, etc.) ;
* tables temporaires.

Toute exception doit être documentée.

---

# Journal d'audit

Les colonnes d'audit ne remplacent pas un journal d'événements.

Le domaine **Audit** pourra enregistrer des informations complémentaires, telles que :

* type d'action ;
* anciennes et nouvelles valeurs (si nécessaire) ;
* adresse IP ;
* navigateur (User-Agent) ;
* identifiant de session ;
* jeton d'API utilisé ;
* domaine concerné.

Ces informations seront stockées dans des tables dédiées (`audit_logs`, `activity_logs`, etc.).

---

# Connexions utilisateurs

Le framework MAHLINE prévoit un journal dédié aux connexions.

Chaque connexion pourra enregistrer :

* utilisateur ;
* date et heure ;
* adresse IP ;
* navigateur ;
* système d'exploitation ;
* appareil ;
* session ;
* jeton d'API (le cas échéant).

Ces informations ne sont pas stockées dans les colonnes d'audit mais dans le domaine **Identity**.

---

# Suppression logique

Lors d'un Soft Delete :

* `deleted_at` est renseigné ;
* `deleted_by` est renseigné lorsque l'utilisateur est connu.

Les données restent disponibles pour les besoins d'audit et de restauration.

---

# Suppression définitive

Une suppression physique (`forceDelete`) doit rester exceptionnelle.

Elle ne doit être utilisée que :

* pour des traitements techniques ;
* pour des obligations réglementaires ;
* pour des opérations d'administration contrôlées.

---

# Sécurité

Les données d'audit sont sensibles.

Elles doivent :

* être protégées par les politiques d'autorisation ;
* ne jamais être modifiées directement ;
* être conservées conformément aux besoins métier et réglementaires.

---

# Interdictions

Sont interdits :

* la suppression manuelle des informations d'audit sans justification ;
* la modification directe des colonnes d'audit ;
* la désactivation de l'audit sur une table métier sans décision d'architecture documentée.

---

# Validation

Avant validation d'une migration, vérifier que :

* les colonnes `created_by`, `updated_by` et `deleted_by` sont présentes si la table est auditée ;
* les contraintes de clés étrangères sont définies ;
* les horodatages (`created_at`, `updated_at`) sont présents ;
* le Soft Delete est correctement configuré lorsque nécessaire ;
* le remplissage automatique est assuré par la Foundation.

---

# Documents associés

* DATABASE_ARCHITECTURE.md
* MIGRATION_STANDARD.md
* TABLE_STANDARD.md
* COLUMN_STANDARD.md
* ULID_STANDARD.md
* FOREIGN_KEY_STANDARD.md
* INDEX_STANDARD.md
* SOFT_DELETE_STANDARD.md
* ENUM_STANDARD.md
* NAMING_STANDARD.md
* DATABASE_CHECKLIST.md

---

# Conclusion

L'audit constitue un pilier du framework MAHLINE.

Il garantit une traçabilité fiable des opérations métier tout en restant transparent pour les développeurs. Associé au domaine **Audit** et au journal des connexions du domaine **Identity**, il fournit une vision complète des actions réalisées dans le système.

---

# Historique

## Version 1.0

* Définition du standard officiel d'audit.
* Standardisation des colonnes d'audit.
* Intégration des règles de remplissage automatique.
* Séparation entre audit des données et journal des connexions.
* Publication du standard officiel d'audit du framework MAHLINE.
