# DATABASE_CHECKLIST

## MAHLINE Framework

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit la checklist officielle de validation de la couche base de données du framework **MAHLINE**.

Elle doit être utilisée avant toute validation d'une migration, d'une table ou d'un domaine métier afin de garantir le respect des standards du framework.

---

# Règles générales

Avant toute validation, vérifier que la base de données respecte :

* l'architecture officielle ;
* les standards de développement ;
* les conventions de nommage ;
* les règles de sécurité ;
* les exigences de performance ;
* les contraintes d'intégrité référentielle.

Aucun développement ne peut être considéré comme terminé tant que cette checklist n'est pas validée.

---

# 1. Architecture

* [ ] La table appartient à un domaine clairement identifié.
* [ ] Une seule responsabilité métier est représentée.
* [ ] La structure respecte `DATABASE_ARCHITECTURE.md`.
* [ ] Aucune logique métier n'est placée dans les migrations.

---

# 2. Migration

* [ ] Une migration = une responsabilité.
* [ ] Les méthodes `up()` et `down()` sont complètes.
* [ ] La migration est réversible.
* [ ] Aucune donnée métier n'est insérée dans la migration.

---

# 3. Clé primaire

* [ ] La clé primaire est un ULID.
* [ ] La colonne est nommée `id`.
* [ ] La clé primaire est déclarée avec :

```php id="ewlfmq"
$table->ulid('id')->primary();
```

---

# 4. Clés étrangères

* [ ] Toutes les relations utilisent `foreignUlid()`.
* [ ] Toutes les contraintes sont définies.
* [ ] Les actions `onUpdate` et `onDelete` sont explicites.
* [ ] Les noms suivent la convention `<entité>_id`.

---

# 5. Colonnes

* [ ] Les colonnes sont ordonnées selon le standard officiel.
* [ ] Les noms sont explicites.
* [ ] Les types de données sont adaptés.
* [ ] Les colonnes inutiles sont absentes.
* [ ] Les valeurs par défaut sont justifiées.

---

# 6. Tables

* [ ] Le nom est au pluriel.
* [ ] Le nom est en `snake_case`.
* [ ] La table possède une responsabilité unique.
* [ ] Les relations sont cohérentes.

---

# 7. Audit

* [ ] Les colonnes `created_by`, `updated_by` et `deleted_by` sont présentes lorsque requises.
* [ ] Les colonnes `created_at` et `updated_at` sont présentes.
* [ ] Les contraintes des colonnes d'audit sont définies.
* [ ] Le remplissage automatique est assuré par la Foundation.

---

# 8. Soft Delete

* [ ] `softDeletes()` est présent si la table est concernée.
* [ ] Le modèle utilise le trait `SoftDeletes`.
* [ ] Les règles de restauration sont définies.
* [ ] Les suppressions définitives sont limitées aux cas autorisés.

---

# 9. Index

* [ ] Les clés primaires sont indexées.
* [ ] Les clés étrangères sont indexées.
* [ ] Les index de recherche sont présents.
* [ ] Les index composites sont justifiés.
* [ ] Aucun index redondant n'est créé.

---

# 10. Enums

* [ ] Aucun `ENUM` SQL n'est utilisé.
* [ ] Les Enums PHP sont utilisés.
* [ ] Les colonnes utilisent un type compatible (`string` recommandé).
* [ ] Les valeurs sont validées.
* [ ] Les casts Eloquent sont configurés.

---

# 11. Nommage

* [ ] Les tables sont au pluriel.
* [ ] Les modèles sont au singulier.
* [ ] Les colonnes utilisent `snake_case`.
* [ ] Les classes utilisent `PascalCase`.
* [ ] Les migrations sont correctement nommées.
* [ ] Les index et contraintes respectent les conventions officielles.

---

# 12. Performance

* [ ] Les index répondent à un besoin réel.
* [ ] Les colonnes inutiles sont évitées.
* [ ] Les duplications de données sont justifiées.
* [ ] Les requêtes critiques ont été prises en compte lors de la conception.

---

# 13. Sécurité

* [ ] Les données sensibles sont protégées.
* [ ] Les mots de passe sont hachés.
* [ ] Les données confidentielles sont chiffrées si nécessaire.
* [ ] Les autorisations d'accès sont définies.

---

# 14. Documentation

* [ ] La migration est documentée.
* [ ] Les décisions importantes sont consignées dans un ADR si nécessaire.
* [ ] Les exceptions aux standards sont documentées.
* [ ] Les documents du domaine sont mis à jour.

---

# 15. Tests

* [ ] Les migrations sont exécutées avec succès.
* [ ] Les relations fonctionnent correctement.
* [ ] Les contraintes sont vérifiées.
* [ ] Les suppressions logiques sont testées.
* [ ] Les restaurations sont testées.
* [ ] Les Enums sont couverts par des tests.
* [ ] Les performances des requêtes critiques sont vérifiées lorsque nécessaire.

---

# Validation finale

La base de données d'un domaine est validée lorsque :

* toutes les migrations sont conformes ;
* toutes les tables respectent les standards ;
* les conventions de nommage sont respectées ;
* les tests sont validés ;
* la documentation est complète ;
* aucune anomalie bloquante n'est identifiée.

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
* NAMING_STANDARD.md

---

# Conclusion

Cette checklist constitue le point de contrôle final de toute évolution de la base de données du framework MAHLINE.

Elle garantit que chaque domaine métier est développé selon les mêmes exigences de qualité, de sécurité, de performance et de maintenabilité. Son utilisation systématique contribue à préserver la cohérence globale du projet sur le long terme.

---

# Historique

## Version 1.0

* Création de la checklist officielle de validation de la base de données.
* Intégration de l'ensemble des standards de la phase **F-03**.
* Définition des critères de validation technique, fonctionnelle et documentaire.
* Publication de la première version officielle.
