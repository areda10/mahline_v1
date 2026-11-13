# MAHLINE V7.0 — Feuille de route globale des domaines

- **Version :** 3.1
- **Date :** 12 novembre 2026
- **Branche de référence :** `feature/Identity`
- **Commit audité :** `63b9d4cb767643fc30b1a03ef992d3aa1985ae05`
- **Nature :** référentiel fonctionnel cible et feuille de route, à confirmer par audits détaillés
- **Statut :** PROPOSITION DE SUIVI — les statuts doivent être confirmés par audit fonctionnel et tests.

## 1. Objet et règles de suivi

Ce document centralise la feuille de route de développement de tous les domaines MAHLINE. Il complète le backlog spécialisé Identity et ne remplace ni les décisions métier validées, ni les documents d'architecture.

**Registre des décisions fonctionnelles du premier release :** [`docs/roadmap/FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md`](FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md). Toutes les questions, réponses validées, conséquences et questions ouvertes concernant le SaaS de gestion + marketplace doivent être consignées dans ce fichier au fil du cadrage.

Le premier objectif produit est désormais défini comme un **SaaS de gestion pour les coopératives + marketplace publique**. Le développement des domaines nécessaires au premier release sera cadré question par question. L'harmonisation approfondie des frontières et le renforcement global des événements transversaux sont prévus dans une phase ultérieure dédiée, après le développement fonctionnel du premier release. Les exigences essentielles de sécurité, d'autorisation, d'isolation des coopératives et d'intégrité restent obligatoires à chaque étape.

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

### 1.1 Procédure obligatoire de cadrage, architecture et développement V1

La procédure suivante s'applique à **chaque domaine** et à chaque fonctionnalité du premier release. Elle constitue une règle de travail obligatoire afin qu'aucune fonction validée ne soit oubliée pendant le développement.

1. **Cadrer les fonctions du domaine**
   - Identifier les fonctions métier nécessaires au premier release, y compris les cas normaux, exceptions, erreurs, droits d'accès, états et règles de validation.
   - Poser les questions de décision une par une, avec recommandation et validation explicite.
   - Ne pas considérer une fonction comme décidée tant que sa règle n'est pas validée et consignée.

2. **Concevoir l'architecture**
   - Définir la responsabilité et les limites du domaine.
   - Identifier ses entités, relations, statuts, règles métier, services, événements, interfaces, permissions, routes et dépendances avec les autres domaines.
   - Définir les contrats inter-domaines et les flux de données avant le codage.
   - Distinguer les fonctions V1 des fonctions reportées aux versions ultérieures.

3. **Établir l'inventaire fonctionnel exhaustif du domaine**
   - Attribuer un identifiant stable à chaque fonction (par exemple MED-001 ou SAL-001).
   - Documenter pour chaque fonction : objectif, acteur, déclencheur, préconditions, règles, résultat attendu, erreurs/exception, autorisations, dépendances et critères d'acceptation.
   - Maintenir une liste des fonctions V1, des fonctions existantes à auditer, des fonctions à compléter, des fonctions à développer et des fonctions explicitement reportées.
   - Relier chaque fonction aux fichiers, migrations, routes, permissions et tests concernés dès que leur emplacement est défini.

4. **Coder à partir du registre, jamais de mémoire**
   - Avant de coder une fonctionnalité, relire le document officiel du domaine et la fiche de la fonction concernée.
   - Vérifier les critères d'acceptation et les dépendances ; suivre la liste fonction par fonction.
   - Mettre à jour le registre si une précision ou une décision change, avant de poursuivre le code concerné.
   - Ne pas ajouter, supprimer ou modifier une règle métier implicitement : toute modification doit être validée et documentée.

5. **Vérifier et clôturer avec traçabilité**
   - Relier chaque fonction à ses tests unitaires, Feature, HTTP et intégration pertinents.
   - Exécuter les tests requis et les contrôles qualité ; conserver les résultats réels.
   - Marquer une fonction comme terminée uniquement lorsque son implémentation, ses critères d'acceptation et ses tests requis sont vérifiés.
   - Faire une revue finale de couverture : chaque fonction V1 doit être soit implémentée et testée, soit explicitement bloquée avec raison ; aucune fonction ne doit disparaître du suivi.


6. **Auditer les domaines et leurs liaisons**
   - Pour le domaine étudié, inventorier les fonctions et composants réels, puis auditer ses relations avec chaque domaine dépendant ou consommateur : responsabilités, données échangées, déclencheurs, contrats, événements, permissions et gestion des erreurs.
   - Vérifier les dépendances entrantes et sortantes afin d'identifier les fonctions manquantes, les responsabilités mal placées, les incohérences et les risques de duplication entre domaines.

7. **Comparer l'inventaire avec les documents officiels avant toute nouvelle question**
   - Relire le registre de découverte, la roadmap, les spécifications du domaine et les décisions déjà validées avant de poser une question.
   - Rechercher les formulations et décisions équivalentes, y compris dans les domaines liés ; ne pas reposer une question déjà tranchée.
   - Si une règle existante est partielle ou contradictoire, poser uniquement une question ciblée sur le point réellement non résolu et référencer la décision antérieure.
   - Maintenir une matrice de couverture reliant chaque fonction et chaque règle aux documents, décisions, relations inter-domaines et preuves d'audit. Une fonction déjà documentée doit être vérifiée et complétée, pas recréée sous un nouvel identifiant sans justification.

8. **Décider du passage au développement ou poursuivre le cadrage**
   - Comparer l'inventaire fonctionnel exhaustif, l'audit technique réel, les relations inter-domaines et les spécifications versionnées.
   - Si toutes les fonctions indispensables au premier release sont définies, cohérentes, attribuées à un domaine et assorties de critères d'acceptation et de dépendances connues, valider le périmètre V1 et passer au codage.
   - Sinon, poursuivre les questions/réponses uniquement sur les lacunes identifiées, puis mettre à jour les documents et refaire la comparaison.
   - Ne pas retarder le passage au codage pour des fonctions explicitement reportées hors V1 ; elles doivent être conservées dans la liste des évolutions futures.

**Règles de documentation :**
- Le registre global de roadmap indique l'état des domaines et l'ordre de travail.
- Le registre de découverte fonctionnelle conserve les décisions question/réponse.
- Chaque domaine doit disposer d'un inventaire/specification fonctionnelle détaillée et versionnée avant son implémentation ; ce document devient la checklist obligatoire pendant le codage.
- Toute réponse validée est enregistrée dans le document officiel approprié sur la branche de travail, puis relue depuis GitHub pour confirmer sa présence.
- **Correspondance obligatoire des références :** le registre général `FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md` conserve la vue transversale et les décisions ; chaque spécification de domaine est la checklist détaillée de codage. Les identifiants stables des fonctions doivent être repris sans divergence dans la roadmap, les spécifications et les liens vers les fichiers, migrations, routes, permissions et tests.
- Avant chaque tâche de développement, consulter la fiche de la fonction dans la spécification du domaine ainsi que les décisions et dépendances transversales du registre général. Toute évolution doit être répercutée dans les documents concernés avant la poursuite du code.
- Aucun développement applicatif ni aucune fusion de branche n'est implicite dans une validation de cadrage.

- **Contenu obligatoire des spécifications de domaine :** chaque fonction possède un identifiant stable, objectif, périmètre V1, acteurs, rôles/permissions, préconditions, règles métier, états/transitions, scénarios nominaux et alternatifs, cas limites, erreurs, dépendances, contrats et échanges inter-domaines, événements, données et contraintes, composants techniques concernés, critères d'acceptation et tests associés. Les éléments non applicables sont justifiés ; reports, exclusions et décisions en attente sont explicites.
- **Traçabilité de bout en bout :** relier chaque identifiant fonctionnel à la décision du registre général, à sa spécification, aux modèles/migrations/services/contrôleurs/routes/permissions/jobs ou notifications concernés, aux tests et aux preuves de validation. Aucun intitulé isolé ne suffit à autoriser le codage.

- **Nomenclature des identifiants fonctionnels :** utiliser une séquence stable par domaine avec préfixe propre (ex. `MED-001`, `SAL-001`). Autoriser des sous-identifiants hiérarchiques pour les sous-fonctions (ex. `SAL-001.01`) sans remplacer l'identifiant parent. Toute fonction inter-domaines conserve un propriétaire fonctionnel canonique ; les autres domaines référencent cet identifiant et documentent leurs propres responsabilités/contrats sans dupliquer la fonction. Un identifiant attribué n'est jamais réutilisé ; les remplacements et abandons gardent une trace.

### 1.2 Référentiel fonctionnel et propriété des domaines MAHLINE V1

Cette matrice aligne la roadmap avec le registre de découverte. Elle fixe le propriétaire fonctionnel de chaque famille de fonctions ; elle ne remplace pas les spécifications détaillées, qui devront attribuer les identifiants stables par domaine et préciser les critères d'acceptation.

| Domaine propriétaire | Fonctions principales à inventorier et spécifier pour V1 | Hors de sa responsabilité |
|---|---|---|
| Identity | comptes internes et clients, inscription, authentification, rôles/permissions, sessions, tokens, profils d'identité, vérification e-mail, récupération, sécurité et cycle de vie des comptes | paniers, commandes, adresses commerciales, paiements |
| Cooperatives | création et cycle de vie des coopératives, profils, coordonnées, utilisateurs/affectations, paramètres, statut, documents institutionnels | produits, commandes, factures, stock |
| Localization | pays, régions, villes, adresses structurées, validation et référentiels géographiques | propriété commerciale des adresses et calcul financier |
| Countries | référentiel pays si son autonomie est confirmée ; sinon fonctions intégrées à Localization après ADR | adresses et règles de livraison métier |
| Categories | taxonomie globale/locale, hiérarchie, rattachement, publication, suppression/détachement et règles de conservation | définition du produit et stock |
| Products | définition métier du produit si cette responsabilité est confirmée distincte de Catalog : attributs, variantes, prix de vente, informations réglementaires et cycle de vie | publication du catalogue, stock, facturation |
| Catalog | présentation et publication des produits, recherche/filtrage, visibilité publique, catalogues par coopérative et exposition marketplace | propriété des données produits si Products est confirmé propriétaire ; stock et commandes |
| Media | upload, validation, métadonnées, stockage privé/public, association technique, accès, remplacement, suppression, antivirus/traitement si retenus et quotas techniques | règles métier déterminant qui peut utiliser un média |
| Inventory | quantités, disponibilité, mouvements, réservations, ajustements, entrepôts, seuils et libération de stock | commande commerciale et expédition physique |
| Sales | profil commercial client, panier, checkout, commande multi-coopératives, sous-commandes par coopérative, lignes, prix figés, confirmation/refus, annulation et historique commercial | factures, paiements, commissions, reversements, exécution transport |
| Finance | facturation marketplace par coopérative/commande, documents financiers client et coopérative, encaissement COD par le transporteur puis reversement périodique des fonds à MAHLINE selon le calendrier contractuel, répartition entre coopératives, répartition proportionnelle des remises globales multi-coopératives uniquement sur les montants des produits éligibles à la promotion avant remise (produits exclus ignorés), affectation des centimes résiduels au plus fort reste, avec départage des restes égaux par identifiant stable de coopérative en ordre croissant, et total exact, puis calcul des commissions selon un taux contractuel par coopérative, configurable exclusivement par `super_admin_root`, sur le montant net des produits après déduction des remises, hors livraison, avec historique des versions, justification contractuelle et date d'effet ; chaque commande conserve le taux figé lors de sa création, sans recalcul rétroactif, remboursements/avoirs avec correction proportionnelle des commissions sur les montants de produits remboursés (écritures correctives liées à l'origine, historique conservé), reversements, rapprochement et états financiers | panier, commande commerciale, abonnement SaaS |
| Delivery | expéditions par coopérative, colis, tarifs de trajet, transporteurs, affectation, suivi, preuve de livraison, incidents, refus, retours et réexpéditions | panier, commande commerciale et comptabilisation des paiements |
| Subscriptions / SaaS Billing | plans SaaS, souscriptions, renouvellements, changements de plan, périodes de grâce, impayés, factures et paiements dus à MAHLINE, entitlements, quotas et suspension/réactivation | factures marketplace et reversements aux coopératives |
| Communication | modèles, e-mails, notifications, préférences techniques, files, retries, délivrabilité et historique d'envoi | règles métier déclenchant la communication |
| Audit | événements d'audit métier, acteur, sujet, horodatage, avant/après, motif, consultation autorisée, intégrité et rétention | stockage des fichiers et logique métier source |
| CMS | pages, sections, bannières, menus, FAQ, SEO, brouillons, publication, planification et contenus multilingues | médias, produits, coopératives et commandes |
| Dashboard | indicateurs et tableaux de bord par rôle/périmètre, agrégations des domaines sources et contrôles d'accès | propriété des données opérationnelles ou financières |

**Décisions de frontière à appliquer :**

1. **Sales ≠ Finance.** Sales est propriétaire du cycle commercial et des commandes. Finance est propriétaire des transactions monétaires, factures marketplace, remboursements, commissions et reversements. Sales transmet à Finance des références et données de commande figées par un contrat ; il ne crée ni ne modifie les documents comptables.
2. **Finance ≠ Subscriptions / SaaS Billing.** Finance traite les flux marketplace. Subscriptions / SaaS Billing gère les créances et abonnements coopérative ↔ MAHLINE. Toute intégration financière éventuelle passe par un contrat explicite, sans transférer la propriété des abonnements à Finance.
3. **Sales ≠ Delivery.** Sales crée et suit l'état commercial de la commande ; Delivery exécute les expéditions et retours physiques. Les deux domaines échangent via des contrats/événements.
4. **Inventory ≠ Catalog/Products.** Catalog/Products décrivent ce qui est vendu ; Inventory détient les quantités et mouvements.
5. **Facturation marketplace appartient à Finance.** La génération PDF et l'envoi sont des capacités techniques consommées via Media/Communication ; ces domaines ne deviennent pas propriétaires de la facture.
6. **Core / Shared ne possèdent aucune règle métier de domaine.** Une fonction partagée conserve un propriétaire canonique et un identifiant ; les consommateurs référencent ce propriétaire sans duplication.
7. **Products / Catalog / Categories et Countries / Localization** nécessitent une décision de frontière documentée avant déplacement ou duplication de code. La roadmap ne présume pas que leurs responsabilités actuelles sont déjà correctement séparées.
8. **Finance est un domaine autonome distinct de Sales, Delivery et Subscriptions / SaaS Billing.** Finance possède exclusivement les flux financiers de la marketplace ; il ne devient pas un sous-module de Sales ni un service partagé.
9. **Facturation n'est pas un domaine métier autonome dans cette architecture V1.** La facture marketplace et les avoirs relèvent de Finance ; les factures d'abonnement dues à MAHLINE relèvent de Subscriptions / SaaS Billing. Media fournit le stockage/PDF et Communication l'envoi, sans posséder la règle de facturation.
10. **Core / Shared restent techniques.** Ils ne possèdent ni factures, ni paiements, ni commandes, ni règles de tarification métier ; les contrats explicites relient les domaines propriétaires.

**Spécifications de domaine attendues :** une spécification versionnée par domaine, avec identifiants stables (préfixes dédiés), fonctions V1, reports, acteurs, règles, états, permissions, scénarios, erreurs, critères d'acceptation, contrats inter-domaines, données, événements, composants et tests. Les identifiants sont attribués après comparaison des décisions et inventaires existants, jamais à partir d'une liste supposée exhaustive.

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

Les périmètres **Delivery** et **Subscriptions / SaaS Billing** sont retenus comme domaines fonctionnels autonomes pour MAHLINE V1 (décision A, validée le 13 novembre 2026). Export / Compliance reste à spécifier avant de décider de son autonomie.

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
- [ ] Appliquer la commission sur le montant net des produits après remises, hors livraison ; conserver le taux contractuel figé à la création de la commande.
- [ ] Pour les remboursements, annuler la commission totale correspondante ou, en cas de remboursement partiel, la part calculée sur le montant net des produits remboursés après remise et hors livraison ; enregistrer une correction distincte liée à l'écriture initiale et au remboursement.
- [ ] Préserver l'immutabilité et l'historique des commissions initiales et corrections ; définir les règles de centimes, remboursements successifs et plafonnement du cumul des corrections.
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

### 3.16 Delivery — À démarrer

**Responsabilité :** expéditions physiques et exécution de la livraison des commandes marketplace.

- [ ] Spécifier Shipment / Delivery, colis, transporteurs, affectation et statuts.
- [ ] Définir les étapes de prise en charge, expédition, suivi, livraison et preuve de livraison.
- [ ] Définir les incidents, échecs, refus du destinataire, retours et réexpéditions.
- [ ] Définir les tarifs de livraison par trajet/ville et la répartition par coopérative.
- [ ] Définir l'espace et les accès transporteur, sans exposer les données d'autres coopératives.
- [ ] Intégrer Sales, Inventory, Finance, Localization et Communication par contrats explicites.
- [ ] Prévoir tests de transitions, permissions, traçabilité, concurrence et idempotence.

**Frontière :** Sales possède le panier et les commandes commerciales ; Delivery possède l'exécution et le suivi des expéditions. La livraison n'est pas un sous-module interne de Sales.

### 3.17 Subscriptions / SaaS Billing — À démarrer

**Responsabilité :** commercialisation, cycle de vie et facturation de l'abonnement SaaS payé à MAHLINE par les coopératives.

- [ ] Spécifier les plans START, PRO et BUSINESS, leurs prix, périodicités et limites.
- [ ] Gérer souscriptions, renouvellements, changements de plan, échéances et historique.
- [ ] Définir les états d'abonnement, périodes de grâce, impayés, suspension et réactivation.
- [ ] Appliquer les entitlements : modules, nombre d'utilisateurs, quotas Media et limites d'usage.
- [ ] Définir factures SaaS, paiements d'abonnement et rapprochement.
- [ ] Définir les pouvoirs root et les notifications de renouvellement/impayé.
- [ ] Intégrer Cooperatives, Identity, Media, Finance (si nécessaire via contrats) et Communication.
- [ ] Prévoir tests de calcul, dates, renouvellement, idempotence, limites et accès après suspension.

**Frontière :** ce domaine gère uniquement la relation commerciale coopérative ↔ MAHLINE. Finance marketplace reste responsable des factures clients, paiements de commandes, commissions et reversements coopératives.

## 4. Périmètres complémentaires à spécifier

- **Export / Compliance :** exigences réglementaires, documents d'export et conformité par pays cible. Son autonomie comme domaine sera décidée après spécification.

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
14. Delivery : expéditions, transporteurs, suivi, incidents et retours.
15. Subscriptions / SaaS Billing : plans, renouvellements, entitlements, facturation et suspension.
16. Spécifier Export / Compliance selon les marchés et exigences réglementaires retenus.
17. Intégration globale, audit de sécurité, staging et préparation production.

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

### Version 3.1 — 13 novembre 2026
- Enregistrement de Finance Q224 : assiette de correction de commission après remboursement partiel = montant net des produits effectivement remboursés, après remise et hors livraison.
- Alignement de la matrice Finance avec Q218, Q223 et Q224 : taux figé à la commande, correction distincte liée au remboursement et conservation des écritures historiques.
- Les règles techniques d'arrondi au centime, de remboursements successifs et de plafonnement du cumul restent à spécifier.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.0 — 13 novembre 2026
- Enregistrement de Finance Q223 : les commissions sont corrigées proportionnellement aux montants de produits remboursés ; la correction est liée à l'écriture initiale et conserve l'historique.
- Alignement de la matrice Finance sur le traitement des remboursements partiels/totaux et la traçabilité des corrections de commission.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.9 — 13 novembre 2026
- Enregistrement de Finance Q222 : seuls les produits éligibles participent à l'assiette de prorata d'une remise globale ; les produits exclus de la promotion sont ignorés.
- Alignement de la matrice Finance avec Q219-Q222 : prorata calculé sur les seuls produits éligibles, puis répartition au plus fort reste avec départage Q221 et total exact.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.8 — 13 novembre 2026
- Enregistrement de Finance Q221 : départage des restes fractionnaires égaux par identifiant stable de coopérative, en ordre croissant.
- Alignement de la matrice Finance avec Q220-Q221 : méthode du plus fort reste, départage déterministe et total de remise exact.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.7 — 13 novembre 2026
- Enregistrement de Finance Q220 : affectation des centimes résiduels des remises globales selon la méthode du plus fort reste, avec départage déterministe.
- La ventilation par coopérative doit totaliser exactement la remise globale, sans écart d'arrondi.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.6 — 13 novembre 2026
- Enregistrement de Finance Q219 : les remises globales sur panier multi-coopératives sont ventilées au prorata des montants produits de chaque coopérative avant remise.
- Sales transmet la ventilation figée par sous-commande ; Finance calcule les montants nets et commissions par coopérative.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.5 — 13 novembre 2026
- Enregistrement de Finance Q218 : la commission est calculée sur le montant net des produits après déduction des remises, hors livraison.
- La traçabilité financière doit conserver les montants avant remise, les remises, l'assiette nette, le taux et la commission par commande.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.4 — 13 novembre 2026
- Enregistrement de Finance Q217 : chaque nouveau taux possède une date d'effet définie par `super_admin_root`.
- Les commandes déjà créées conservent le taux figé à leur création ; aucune modification rétroactive des commissions n'est appliquée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.3 — 13 novembre 2026
- Enregistrement de Finance Q216 : le taux de commission est défini contractuellement et seul `super_admin_root` peut le saisir ou le modifier.
- Le paramétrage doit conserver l'historique des versions, l'auteur, la date d'effet et la référence/justification contractuelle.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.2 — 13 novembre 2026
- Enregistrement de Finance Q215 : taux de commission configurable par coopérative, appliqué au montant des produits uniquement, hors livraison.
- Précision de l'assiette de commission dans la matrice Finance ; le taux appliqué doit être traçable pour chaque commande.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.1 — 13 novembre 2026
- Enregistrement de Finance Q214 : reversement périodique des encaissements COD par le transporteur à MAHLINE, selon le calendrier contractuel (par exemple hebdomadaire).
- Le suivi Finance doit distinguer les montants encaissés, attendus, reversés et reçus, avec rattachement aux commandes et au transporteur.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.0 — 13 novembre 2026
- Enregistrement de la décision Finance Q213 : le transporteur encaisse le COD puis reverse les fonds à MAHLINE, qui calcule et répartit les montants dus aux coopératives et suit les commissions/reversements.
- Précision du périmètre Finance : réception des fonds COD, allocation par commande/cooperative, calcul des commissions et suivi des reversements.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 1.9 — 13 novembre 2026
- Alignement du référentiel fonctionnel V1 avec Q72 : règles métier détenues par leur domaine propriétaire ; Core/Shared strictement techniques et contrats explicites.
- Confirmation de Finance comme domaine métier autonome distinct de Sales, Delivery et Subscriptions / SaaS Billing ; clarification de la propriété des factures marketplace versus factures d'abonnement.
- Clarification : Facturation n'est pas un domaine autonome dans cette architecture V1 ; la propriété dépend du flux (Finance marketplace, SaaS Billing abonnement), tandis que Media/Communication assurent les capacités techniques PDF et envoi.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 1.8 — 13 novembre 2026
- Ajout du référentiel de propriété fonctionnelle et des fonctions principales à inventorier pour chaque domaine V1.
- Clarification des frontières Sales / Finance / Delivery / Subscriptions-SaaS Billing ; Finance est un domaine autonome distinct de Sales.
- Ajout des règles de non-duplication et des frontières à arbitrer avant déplacement de code.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 1.7 — 13 novembre 2026
- Formalisation de la nomenclature Q71 : identifiants stables par domaine, sous-identifiants hiérarchiques et références canoniques inter-domaines sans duplication.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 1.6 — 13 novembre 2026
- Ajout du contenu minimum obligatoire des spécifications de domaine et de la traçabilité fonctionnelle jusqu'aux composants techniques, tests et preuves de validation, conformément à Q70.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 1.5 — 13 novembre 2026
- Alignement sur la décision Q69 : registre général pour la traçabilité transversale et spécification détaillée par domaine comme checklist obligatoire de codage ; identifiants fonctionnels stables et liens cohérents vers code, migrations, routes, permissions et tests.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 1.4 — 13 novembre 2026
- Renforcement de la procédure de cadrage : audit des relations inter-domaines, comparaison systématique avec les registres/specifications avant toute question afin d'éviter les doublons, matrice de couverture et critère explicite de passage au codage lorsque le périmètre V1 essentiel est complet.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 1.3 — 13 novembre 2026
- Formalisation de la procédure obligatoire : cadrage fonctionnel par domaine, architecture avant code, inventaire exhaustif avec identifiants, lecture obligatoire des spécifications avant chaque tâche de codage et traçabilité jusqu'aux tests.
- Confirmation de la séparation entre roadmap globale, registre des décisions et spécifications fonctionnelles détaillées de chaque domaine.

### Version 1.2 — 13 novembre 2026
- Décision architecturale A validée : Delivery et Subscriptions / SaaS Billing sont deux domaines fonctionnels autonomes pour MAHLINE V1.
- Création de leurs périmètres de responsabilité, tâches de cadrage et frontières avec Sales et Finance.
- Delivery possède l'exécution des expéditions ; Sales conserve le panier et les commandes commerciales.
- Subscriptions / SaaS Billing possède les abonnements et factures dues à MAHLINE ; Finance conserve la facturation marketplace, les commissions et reversements.

### Version 1.1 — 12 novembre 2026
- Définition du premier objectif produit : SaaS de gestion coopérative + marketplace publique.
- Ajout du lien vers le registre question/réponse du cadrage fonctionnel : `FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md`.
- Confirmation du phasage : domaines du premier release d'abord ; harmonisation approfondie des frontières et renforcement transversal des événements dans une phase ultérieure dédiée.

### Version 1.0 — 11 novembre 2026
- Création de l'inventaire global des 9 domaines présents et des 6 domaines absents au commit audité.
- Ajout d'une liste de suivi par domaine, de l'ordre recommandé et des décisions architecturales à arbitrer.
- Aucun fichier applicatif, migration ou test applicatif modifié dans le cadre de cet inventaire.
