# MAHLINE V7.0 — Feuille de route globale des domaines

- **Version :** 1.0
- **Date :** 11 novembre 2026
- **Branche de référence :** `feature/Identity`
- **Commit audité :** `63b9d4cb767643fc30b1a03ef992d3aa1985ae05`
- **Nature :** inventaire structurel initial, en lecture seule
- **Statut :** PROPOSITION DE SUIVI — les statuts doivent être confirmés par audit fonctionnel et tests.

## 1. Objet et règles de suivi

Ce document centralise la feuille de route de développement de tous les domaines MAHLINE. Il complète le backlog spécialisé Identity et ne remplace ni les décisions métier validées, ni les documents d'architecture.

Un dossier ou une classe présente ne signifie pas qu'un domaine est terminé. Un domaine ne peut être marqué **VALIDÉ** qu'après :
1. audit des fichiers réels, appels, dépendances, routes, migrations et permissions ;
2. spécification des règles métier et des contrats inter-domaines ;
3. tests unitaires/Framework, Feature, HTTP et intégration pertinents exécutés avec succès ;
4. contrôle qualité (dont Pint) ;
5. mise à jour de la documentation officielle avec commit de preuve.

Aucune validation globale de suite de tests n'est déduite du simple nombre de fichiers de tests.

### États utilisés

- **À auditer** : état fonctionnel et technique à établir.
- **À compléter** : fonctionnalités ou intégrations identifiées comme manquantes.
- **À démarrer** : domaine absent du dépôt audité.
- **À valider** : code présent, validation complète non établie.
- **Bloqué / décision requise** : dépend d'une décision architecturale ou métier.
- **Validé** : réservé aux tests requis verts et à une preuve consignée.

## 2. Inventaire structurel au commit audité

Dossiers présents dans `app/Domains` :

| Domaine | Fichiers PHP | Tests Feature | Tests Framework | État initial |
|---|---:|---:|---:|---|
| Identity | 73 | 20 | 45 | À compléter / valider |
| Products | 8 | 3 | 13 | À compléter / valider |
| Cooperatives | 5 | 1 | 9 | À compléter / valider |
| Catalog | 6 | 5 | 4 | À compléter / valider |
| Categories | 5 | 4 | 6 | À compléter / valider |
| Audit | 8 | 0 | 2 | À auditer / renforcer |
| Countries | 1 | 0 | 2 | À compléter |
| Localization | 1 | 0 | 3 | À compléter |
| Dashboard | 2 | 1 | 0 | À compléter ultérieurement |

**Total recensé :** 109 fichiers PHP applicatifs, 38 fichiers de tests Feature et 84 fichiers de tests Framework. Ces chiffres sont un inventaire de fichiers, pas une mesure de couverture ni une preuve de réussite des tests.

### Domaines absents au commit audité

Media, Sales, Inventory, Finance, Communication et CMS ne possèdent pas de dossier applicatif ni de tests dédiés dans l'arborescence examinée.

Les périmètres Abonnements/Billing SaaS, Export/Compliance et éventuellement Logistics doivent être spécifiés avant de décider s'ils constituent des domaines autonomes ou des sous-périmètres d'autres domaines.

## 3. Liste de suivi par domaine

### 3.1 Identity — À compléter et valider

**Responsabilité :** utilisateurs, authentification, sessions, rôles, permissions, sécurité et gestion des comptes.

**Déjà présents :** authentification, sessions multi-appareils, tokens API, invitations, gestion des utilisateurs, rôles/permissions, mots de passe, sécurité et historique.

**Travaux à suivre :**
- [ ] USR-02 : adresse personnelle, photo de profil et préférences de notification.
- [ ] USR-03 : consultation et modification du profil personnel.
- [ ] USR-04 : résoudre la divergence entre la décision 91 (absence d'accès de cooperative_admin aux données personnelles d'autres utilisateurs) et les autorisations/tests actuellement présents.
- [ ] Reprendre les fonctionnalités explicitement reportées dans le backlog Identity.
- [ ] Auditer les liens Identity ↔ Media, Localization, Communication, Audit et Cooperatives.
- [ ] Exécuter la suite Identity complète et l'audit final avant clôture.

**Dépendances :** Media pour les photos ; Localization pour les adresses ; Communication pour les préférences et notifications. Ne pas réutiliser le service administratif de modification de compte pour les modifications ordinaires du profil personnel.

### 3.2 Cooperatives — À compléter et valider

**Responsabilité :** cycle de vie, paramètres, affectations et périmètre d'accès des coopératives.

- [ ] Auditer les règles de création, modification, statut et suppression par rôle.
- [ ] Vérifier l'isolation stricte entre coopératives sur routes, requêtes et services.
- [ ] Vérifier les relations avec Identity, Localization, Media, Catalog et Audit.
- [ ] Compléter les tests HTTP, permissions, cas directs par URL et régressions.
- [ ] Vérifier les vues et formulaires, dont la sélection d'adresse.
- [ ] Confirmer la suite requise verte avant clôture.

### 3.3 Categories — À compléter et valider

**Responsabilité :** taxonomie globale et catégories propres aux coopératives.

- [ ] Auditer la hiérarchie et les règles global/local.
- [ ] Vérifier suppression de branches, détachement des enfants locaux et traçabilité Eloquent.
- [ ] Vérifier les produits orphelins, désactivation et réactivation.
- [ ] Vérifier l'isolation inter-coopératives et les contraintes de données.
- [ ] Vérifier les relations et contrats avec Products/Catalog.
- [ ] Confirmer les tests Framework et Feature requis verts.

### 3.4 Products — À compléter et valider

**Responsabilité :** définition des produits et variantes.

- [ ] Auditer création, modification, activation, désactivation et réactivation.
- [ ] Vérifier propriété coopérative et relations catégories/catalogues.
- [ ] Spécifier puis intégrer les médias via Media ; ne pas créer de stockage d'images indépendant.
- [ ] Définir le contrat d'intégration avec Inventory pour stock et disponibilité.
- [ ] Vérifier prix, variantes, statuts et validation métier.
- [ ] Compléter les tests d'intégration et de sécurité.

### 3.5 Catalog — À compléter et valider

**Responsabilité :** présentation, publication et exposition des produits dans les catalogues.

- [ ] Auditer visibilité publique, publication et statut des produits.
- [ ] Vérifier les droits de gestion par coopérative et les accès root.
- [ ] Vérifier l'intégration Products, Categories, Cooperatives et Media.
- [ ] Tester les parcours publics et back-office.
- [ ] Clarifier la frontière avec Categories et Products avant tout déplacement de code.

### 3.6 Audit — À auditer et renforcer

**Responsabilité :** journalisation métier transversale et consultation autorisée.

- [ ] Cartographier les événements réellement émis par chaque domaine.
- [ ] Vérifier avant/après, acteur, motif, session et sujet lorsque pertinents.
- [ ] Vérifier l'absence de secrets, hashes, données sensibles ou fichiers binaires dans les journaux.
- [ ] Vérifier intégrité, rétention, consultation et permissions.
- [ ] Ajouter les tests d'intégration transversaux manquants.
- [ ] Ne pas confondre colonnes techniques d'audit et journal métier.

### 3.7 Localization — À compléter

**Responsabilité :** adresses et référentiels de localisation.

- [ ] Auditer le modèle Address, migrations, relations, validation et ownership.
- [ ] Définir l'association d'une adresse à un utilisateur sans permettre l'accès à l'adresse d'une autre coopérative.
- [ ] Vérifier pays, région, ville, code postal et coordonnées.
- [ ] Définir les besoins langues, devises et fuseaux horaires selon les frontières officielles.
- [ ] Tester les intégrations Identity, Cooperatives, Sales et Finance.

### 3.8 Countries — À compléter, dans le périmètre géographique

**Responsabilité :** référentiel des pays.

- [ ] Auditer modèle, migration, factory, seeders et unicité des codes.
- [ ] Vérifier la relation avec Address.
- [ ] Fiabiliser le référentiel utilisé pour les livraisons nationales et internationales.
- [ ] Définir si Countries reste un domaine distinct ou est regroupé fonctionnellement sous Localization ; ne pas déplacer sans ADR.

### 3.9 Dashboard — À compléter après les domaines métier

**Responsabilité :** indicateurs et tableaux de bord adaptés aux rôles.

- [ ] Auditer les tableaux de bord existants et leur périmètre d'autorisation.
- [ ] Définir les indicateurs par rôle et coopérative.
- [ ] Ajouter les indicateurs ventes, commandes, stock et finance après stabilisation des domaines sources.
- [ ] Vérifier les agrégations, performances et isolation des données.
- [ ] Compléter les tests de vues et contrôleurs.

### 3.10 Media — À démarrer (priorité transversale)

**Responsabilité :** propriété et cycle de vie des fichiers et médias utilisés par les autres domaines.

- [ ] Définir les contrats MediaAsset et l'association d'un média à son domaine consommateur.
- [ ] Définir stockage public/privé, disques, chemins et règles de nommage.
- [ ] Implémenter upload sécurisé, validation MIME réelle, extension, taille et limites.
- [ ] Gérer métadonnées, remplacement, suppression, nettoyage après échec et cohérence transaction/fichier.
- [ ] Contrôler l'accès et la livraison des médias privés.
- [ ] Journaliser les opérations sans stocker le contenu binaire dans Audit.
- [ ] Prévoir tests sécurité, autorisations, fichiers invalides, remplacement, suppression et échec.
- [ ] Fournir des contrats réutilisables à Identity, Cooperatives, Products et CMS.

**Règle :** Media ne porte pas les règles métier du profil, de la coopérative ou du produit ; le domaine consommateur décide qui peut associer et consulter un média.

### 3.11 Sales — À démarrer

**Responsabilité :** parcours d'achat des clients.

- [ ] Spécifier Customer, Cart, CartItem, Order, OrderItem et statuts.
- [ ] Définir la commande multi-coopératives et son découpage coopératif avant gel du schéma.
- [ ] Définir panier, prix figés, taxes/frais, annulation et transitions d'état.
- [ ] Définir Delivery et son intégration avec les transporteurs et Inventory.
- [ ] Définir les contrats avec Identity, Localization, Catalog, Inventory, Finance et Audit.
- [ ] Prévoir tests de concurrence, disponibilité, propriété et isolation.
- [ ] Ne pas placer les factures, paiements ou commissions dans Sales : ils appartiennent à Finance.

### 3.12 Inventory — À démarrer

**Responsabilité :** stock physique, mouvements et réservations.

- [ ] Spécifier inventaires, articles, mouvements, réservations, entrepôts et ajustements.
- [ ] Définir les règles de stock disponible et réservé.
- [ ] Définir verrouillage/concurrence et prévention du stock négatif.
- [ ] Définir événements de réservation, confirmation, annulation et restitution.
- [ ] Intégrer Catalog/Products et Sales par contrats/événements.
- [ ] Prévoir tests de concurrence et intégrité des mouvements.

### 3.13 Finance — À démarrer

**Responsabilité :** documents financiers et mouvements monétaires.

- [ ] Spécifier factures clients et documents/factures coopératives.
- [ ] Définir paiements, transactions, remboursements, commissions et reversements.
- [ ] Définir les règles de calcul, arrondis, devise, numérotation et immutabilité comptable.
- [ ] Définir le traitement des commandes multi-coopératives.
- [ ] Définir génération PDF et intégration Communication.
- [ ] Séparer facturation marketplace et facturation des abonnements Mahline.
- [ ] Prévoir tests comptables, idempotence, erreurs de paiement et audit.

### 3.14 Communication — À démarrer

**Responsabilité :** communications sortantes et suivi de livraison.

- [ ] Spécifier e-mails, notifications, modèles, journaux et préférences.
- [ ] Définir files d'attente, retries, idempotence et gestion des échecs.
- [ ] Définir les notifications critiques obligatoires et celles désactivables.
- [ ] Intégrer les événements Identity, Sales, Finance et CMS.
- [ ] Réutiliser les besoins Identity existants sans dupliquer les préférences.
- [ ] Tester confidentialité, rendu multilingue et délivrabilité.

### 3.15 CMS — À démarrer

**Responsabilité :** contenu public et éditorial.

- [ ] Spécifier pages, sections, bannières, menus, FAQ et métadonnées SEO.
- [ ] Définir brouillon, publication, planification et historique.
- [ ] Définir localisation/multilingue.
- [ ] Utiliser Media pour les images et documents.
- [ ] Définir permissions éditoriales et audit.
- [ ] Tester rendu public et gestion back-office.

## 4. Périmètres complémentaires à spécifier

Ces périmètres ne doivent pas devenir des domaines autonomes sans décision d'architecture :

- **Abonnements / SaaS Billing :** plans START, PRO et BUSINESS, quotas, droits, renouvellement, suspension et paiement de l'abonnement Mahline.
- **Export / Compliance :** exigences réglementaires, documents d'export et conformité par pays cible.
- **Logistics :** à étudier si les règles de transporteurs, expéditions et suivi deviennent trop importantes pour Sales.

Le périmètre Abonnements doit être distingué de Finance marketplace (factures clients, commissions et reversements).

## 5. Ordre de développement recommandé

Ordre de travail proposé, à confirmer après audit des dépendances réelles et arbitrage des documents d'architecture :

1. Audit de cohérence des frontières, Shared et contrats transversaux.
2. Localization + Countries : audit et consolidation.
3. Media : spécification, socle, tests et validation.
4. Audit : renforcer les événements transversaux et les garanties de confidentialité.
5. Identity : USR-02, USR-03, USR-04 et backlog restant.
6. Cooperatives : audit final et intégrations.
7. Categories + Products + Catalog : validation de la chaîne catalogue.
8. Sales : clients, panier, commandes multi-coopératives, livraison.
9. Inventory : stock et réservations.
10. Finance : facturation, paiements, commissions, reversements.
11. Communication : événements, e-mails et notifications.
12. CMS : contenu et publication.
13. Dashboard : indicateurs issus des domaines stabilisés.
14. Spécifier et développer Abonnements/SaaS Billing et Export/Compliance selon les décisions approuvées.
15. Intégration globale, audit de sécurité, staging et préparation production.

Les dépendances peuvent permettre du travail parallèle, mais aucun domaine ne doit contourner les règles de propriété et les contrats inter-domaines.

## 6. Points d'architecture à arbitrer avant les nouveaux domaines

### 6.1 Catalog / Categories / Products

Le dépôt possède trois domaines séparés (Catalog, Categories, Products), alors que le document de frontières attribue la propriété du Product et de Category à Catalog. Il faut décider si cette séparation est une organisation technique sous une propriété logique Catalog, ou si les frontières doivent être révisées. Aucun déplacement ni renommage ne doit être fait sans décision documentée.

### 6.2 Architecture officielle et Domain Boundaries

- `docs/framework/ARCHITECTURE.md` : architecture officielle v1.0.
- `docs/framework/DOMAIN_BOUNDARIES.md` : version 1.1, statut « Proposed for Freeze ».
- `docs/framework/ARCHITECTURE_AUDIT.md` : audit Foundation v1.0 validé, mais ne constitue pas une validation fonctionnelle de tous les domaines.

Avant de créer les domaines absents, harmoniser les listes et l'ordre de développement entre ces documents et l'Architecture MAHLINE V7.0 gelée.

### 6.3 Audit de code non encore réalisé

Le présent inventaire est structurel. Il ne constitue pas encore :
- un audit ligne par ligne des 109 fichiers PHP ;
- une vérification exhaustive des appels entrants/sortants ;
- une validation de toutes les migrations et contraintes MySQL ;
- une exécution des suites de tests ;
- une certification de sécurité ou de préparation à la production.

Les états « à compléter » et « à auditer » sont donc des états de suivi, pas des verdicts de défaut précis sur chaque fonction.

## 7. Préparation au lancement et à l'hébergement

Le lancement production intervient seulement après :
- validation des parcours métier de bout en bout ;
- suite de tests globale requise verte et contrôle qualité ;
- audit sécurité, autorisations et isolation multi-coopératives ;
- vérification des migrations et stratégie de rollback/sauvegarde ;
- environnement de staging proche de l'hébergement cible ;
- compatibilité confirmée de l'offre Heberjahiz avec PHP/Laravel requis, Composer/CLI, cron, stockage, HTTPS et workers nécessaires ;
- configuration des e-mails, files d'attente, scheduler, journaux, monitoring et sauvegardes ;
- tests de restauration et plan de retour arrière.

Ne pas présumer que toute offre mutualisée fournit les workers persistants ou l'accès CLI requis ; vérifier les capacités exactes de l'offre.

## 8. Journal des mises à jour

### Version 1.0 — 11 novembre 2026
- Création de l'inventaire global des 9 domaines présents et des 6 domaines absents au commit audité.
- Ajout d'une liste de suivi par domaine, de l'ordre recommandé et des décisions architecturales à arbitrer.
- Aucun fichier applicatif, migration ou test applicatif modifié dans le cadre de cet inventaire.
