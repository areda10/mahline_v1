# MAHLINE V1 — Plan directeur des modules complets

- **Branche de travail :** `feature/Marketplace`
- **Référence d'inventaire :** `1d80c26b131310e8d22842b35a813a1332e0bd69`
- **Date :** 18 novembre 2026
- **Objectif produit :** SaaS de gestion des coopératives + marketplace publique, avec un parcours commercial complet et traçable.
- **Statut :** plan directeur initial issu de l'inventaire Git et des décisions fonctionnelles déjà enregistrées. Ce document ne certifie pas que les domaines existants sont terminés.

## 1. Cible fonctionnelle V1

MAHLINE V1 doit permettre de gérer les acteurs et les coopératives, publier un catalogue, vendre les produits, gérer le stock, traiter les commandes multi-coopératives, expédier via des transporteurs, facturer, suivre les encaissements et reversements, et fournir des vues de pilotage et de sécurité.

Le parcours principal est :

`Client → Catalogue → Panier → Checkout → Commande globale → Sous-commandes coopératives → Réservation de stock → Préparation → Attribution transporteur → Livraison / encaissement → Transfert transporteur vers MAHLINE → Commission → Reversement coopérative → Facture et historique → Statistiques`

Les événements, notifications, médias, droits et audits traversent ce parcours sans déplacer la propriété des données métier.

## 2. Inventaire technique constaté

À la référence Git indiquée, les répertoires de domaines présents incluent :

- `Audit`
- `Catalog`
- `Categories`
- `Cooperatives`
- `Countries`
- `Dashboard`
- `Identity`
- `Localization`
- `Media`
- `Orders` (README de cadrage uniquement à cette date)
- `Products`

Les domaines métier suivants ne figuraient pas comme répertoires autonomes dans l'inventaire :

- `Sales`
- `Inventory`
- `Delivery`
- `Finance`
- `Statistics`
- `Incidents`
- `Communication`

**Important :** présence d'un répertoire ≠ fonctionnalité complète. Chaque domaine existant doit encore être audité fichier par fichier (migrations, modèles, relations, services, policies, routes, contrôleurs, vues, jobs/events, factories et tests). L'absence d'un répertoire ne prouve pas à elle seule l'absence de tout code transversal ou ancien.

## 3. Modules cibles et responsabilités

| Module | Périmètre V1 | État initial constaté |
|---|---|---|
| Identity | Comptes, rôles, permissions, authentification, sessions multiples révocables, tokens API, cycle de vie des comptes | Présent — audit et complétion à poursuivre |
| Cooperatives | Fiche coopérative, paramètres, affectations, statut, utilisateurs et périmètre organisationnel | Présent — audit et complétion à poursuivre |
| Catalog / Categories / Products | Produits, variantes, SKU, catégories, prix, promotions, publication et consultation catalogue | Présents — audit d'intégration à poursuivre |
| Media | Images, documents, stockage privé/public contrôlé, quotas, traitement, rétention, incidents média | Présent — ne pas dupliquer le stockage dans les autres domaines |
| Localization / Countries | Pays, adresses, régions, devise et règles de localisation | Présents — audit du carnet d'adresses et snapshots à poursuivre |
| Sales | Panier multi-coopératives, lignes, prix calculés, checkout, adresse choisie, validation commerciale et orchestration | À monter |
| Orders | Commande globale, sous-commandes par coopérative, lignes et snapshots, états, annulations, propositions de modification et décisions client | README créé ; domaine métier à monter |
| Inventory | Stocks par coopérative et produit/variante, lots si spécifiés, entrées/sorties, ajustements, réservations, quarantaine, seuils et mouvements immuables | À monter |
| Delivery | Transporteurs, contrats et tarifs, éligibilité, missions, attribution, collecte, expédition, tracking, preuve de livraison, retours et encaissements transporteur | À monter |
| Finance | Contrats financiers versionnés, montants dus, commissions, paiements/encaissements, transferts, reversements, rapprochements et soldes | À monter |
| Invoicing | Factures par commande, numérotation par coopérative/année, PDF immuable, envoi email et historique | Sous-domaine fonctionnel de Finance pour V1 ; pas de second moteur financier |
| Communication | Notifications in-app et email, modèles, files, tentatives, résultats et relances | À monter ou compléter après audit des mécanismes existants |
| Statistics | Indicateurs et tableaux de bord par rôle, coopérative, période, commandes, ventes, stocks, livraisons et finances | À monter après stabilisation des événements et sources |
| Incidents | Signalements opérationnels/sécurité, cycle de traitement, preuves et pièces jointes privées | À monter ; s'appuie sur Media et Audit |
| Audit | Journal métier append-only, auteur, date, action, cible, motif, contexte et accès aux traces | Présent — audit de couverture et raccordement à poursuivre |
| Dashboard | Espaces et vues propres aux rôles, agrégation de données autorisées | Présent — à relier aux domaines et Statistics |
| Administration / Settings | Paramètres globaux et par coopérative, contrats, règles et paramètres fonctionnels versionnés | À auditer ; créer seulement les sous-modules réellement absents |

CMS, marketing avancé, paiement en ligne, intégrations API transporteurs, multi-devises et automatisations avancées ne sont pas des prérequis du parcours V1 minimal, sauf décision fonctionnelle déjà enregistrée ailleurs. Le premier release conserve le paiement à la livraison ; les extensions doivent être ajoutées sans casser ce circuit.

## 4. Décisions V1 à préserver

### 4.1 Commerce et commandes
- Panier multi-coopératives et paiement unique au checkout ; les commandes/factures sont segmentées par coopérative selon les règles fonctionnelles.
- Une commande globale possède une seule adresse de livraison commune, issue d'un snapshot autonome et immuable après confirmation.
- Toutes les commandes V1 exigent une adresse de livraison ; aucun retrait en coopérative au premier release.
- Carnet client : maximum cinq adresses actives ; suppression logique d'une adresse du carnet sans altérer les snapshots de commandes.
- MAD est la devise du premier release ; montants saisis avec deux décimales au maximum, sans conversion implicite.
- Paiement à la livraison uniquement au premier release ; paiement en ligne ultérieur.
- La coopérative peut proposer une réduction de quantité ou un remplacement ; la modification exige l'acceptation explicite du client. En cas de refus ou d'expiration, seule la ligne concernée est annulée ; les autres lignes continuent. Expiration serveur, notifications préalables, idempotence et arbitrage des réponses concurrentes sont requis.
- Après remise du colis au transporteur, l'annulation n'est plus possible ; le refus à la livraison ouvre un processus de retour. La coopérative contrôle le retour et décide explicitement d'une éventuelle remise en stock.

### 4.2 Inventory
- Stock isolé par coopérative.
- Stock rattaché à la variante lorsqu'elle existe ; sinon au produit parent. Pas de stock actif simultané au niveau parent et variantes.
- Stock jamais négatif ; mouvements immuables et auditables.
- Entrées/sorties manuelles immédiates, quantité positive et motif obligatoire.
- Sortie commerciale obligatoirement liée à une commande Orders existante ; sortie manuelle avec motif.
- Réservations et opérations concurrentes protégées par transactions et verrouillage.
- Sortie de quarantaine réservée à `cooperative_admin` : libération, destruction/rebut ou maintien, avec motif, auteur et date.

### 4.3 Livraison et flux financier
- Une coopérative peut travailler avec plusieurs sociétés de livraison.
- Les sociétés éligibles disposent de 12 heures pour accepter une mission ; première acceptation valide = attribution exclusive.
- Sans acceptation sous 12 heures, attribution automatique à la société éligible au tarif le moins cher. Départage en cas d'égalité encore à préciser.
- Le client paie le total à la société de livraison à la remise.
- La société conserve sa part de livraison selon son contrat puis transfère le solde encaissé à MAHLINE.
- MAHLINE calcule sa commission selon le contrat versionné avec la coopérative, puis reverse le solde à celle-ci.
- Transferts transporteur → MAHLINE selon périodes de sept jours ancrées au 1er du mois (ex. 7, 14, 21, 28) ; reversement coopérative selon contrat hebdomadaire.
- Les taux, assiettes, tarifs et calendriers ne sont pas universels : ils sont contractuels, datés, versionnés et explicables.

### 4.4 Facturation
- Une facture unique par commande validée, émise après validation et réservation effective du stock.
- Numérotation indépendante par coopérative : `FAC-AAAA-NNNNNN`, compteur annuel par coopérative ; aucun numéro réutilisé.
- Facture émise/validée et son PDF immuables ; conservation même après annulation.
- Correction d'une commande déjà facturée : annulation motivée puis nouvelle commande et nouvelle facture, jamais réécriture de l'historique.
- Accès : client à ses factures ; `cooperative_admin` à sa coopérative ; `super_admin` aux coopératives assignées ; `super_admin_root` global.
- PDF généré et conservé à l'émission, envoyé par email ; date, destinataire et résultat d'envoi tracés ; échec réessayable sans rendre la facture indisponible.

### 4.5 Incidents et sécurité
- Pièces jointes d'incident privées, accessibles uniquement à `super_admin_root`.
- Contrôle antivirus, limites de taille, vérification réelle du type de fichier et journalisation des consultations.
- Les incidents ne déclenchent pas automatiquement un verrouillage de compte ; le verrouillage d'urgence reste une action distincte et autorisée.
- Aucune donnée sensible (mot de passe, token, OTP ou secret) dans les journaux.

## 5. Architecture et propriété des données

- Sales possède panier, checkout et orchestration commerciale précommande.
- Orders possède commande globale, sous-commandes coopératives, lignes/snapshots, transitions et propositions de modification.
- Inventory possède stock, mouvements, réservations et quarantaine.
- Delivery possède transporteurs, contrats de livraison, missions, expéditions, suivi, retours et événements de collecte.
- Finance possède les événements financiers, contrats de commission, encaissements, transferts, reversements et rapprochements.
- Invoicing est le sous-périmètre de Finance chargé des factures et PDF.
- Catalog/Products possède les définitions produits et variantes ; Media possède les fichiers.
- Identity possède comptes et autorisations ; Cooperatives possède l'organisation coopérative.
- Communication distribue les notifications sans posséder les commandes/factures qui les déclenchent.
- Statistics lit des projections autorisées et ne devient jamais source de vérité.
- Incidents orchestre les signalements et références vers Media/Audit, sans dupliquer leur stockage ou journal.
- Audit conserve les traces métier selon ses règles append-only.
- Les domaines échangent par contrats, DTO et événements explicites, idempotents et traçables. Aucun domaine consommateur n'écrit directement dans les tables d'un autre domaine.

## 6. Ordre de construction

L'ordre fonctionnel demandé est conservé, avec les dépendances techniques intégrées au bon moment :

1. **Orders** — compléter les règles, état/matrice de droits, contrats, puis schéma, modèles, services, tests.
2. **Inventory** — construire les stocks, mouvements, réservations, verrouillages et raccordement à Orders.
3. **Sales** — panier, calculs, checkout, adresse snapshot et orchestration vers Orders.
4. **Delivery** — contrats/tarifs, missions, attribution 12 h, suivi, retours et événements d'encaissement.
5. **Finance** — contrats versionnés, ledger/événements, commissions, transferts, reversements et rapprochements.
6. **Invoicing** — intégré à Finance : numérotation, facture immuable, PDF, envoi et droits.
7. **Communication** — raccorder les événements de commande, livraison, facture et sécurité aux notifications in-app/email.
8. **Statistics** — projections et indicateurs après stabilisation des sources.
9. **Incidents** — workflow de signalement, pièces jointes privées Media, accès root-only et audit.
10. **Intégration finale** — Dashboard, Audit, Identity, Cooperatives, Catalog, Media et Localization ; tests de parcours complet et de non-régression.

Les domaines transversaux existants (Identity, Cooperatives, Catalog, Products, Media, Localization, Audit, Dashboard) sont audités en parallèle avant chaque raccordement ; ils ne sont pas recréés.

## 7. Méthode obligatoire par module

Pour chaque domaine :
1. Inventorier tous les fichiers existants et leurs dépendances.
2. Relier le registre de découverte, les règles fonctionnelles, les décisions validées et les spécifications.
3. Produire un audit : existant, incomplet, absent, doublon, risque et écart.
4. Établir frontières, cas d'usage, états, matrice de permissions et contrats inter-domaines.
5. Ajouter les migrations uniquement ; ne jamais modifier une migration déjà exécutée.
6. Implémenter modèles/relations/Enums, services transactionnels, événements/jobs, puis policies, routes, contrôleurs et interfaces.
7. Écrire des tests unitaires, intégration, sécurité, isolation coopérative, concurrence et parcours métier.
8. Exécuter tests ciblés, Pint puis suite complète ; noter les résultats réels et les SHAs.
9. Ne déclarer le module terminé qu'après validation des règles et tests requis.

Aucun test ne doit être affaibli ou supprimé pour faire passer l'implémentation. Aucun statut « terminé » ne doit être déclaré sur la seule base de la présence de fichiers.

## 8. Points ouverts à traiter sans inventer de règles

Les questions ouvertes des registres restent ouvertes jusqu'à décision : délai de réponse client à une proposition coopérative ; départage des transporteurs à tarif égal ; refus/échec de livraison, colis refusé, encaissement incomplet, retours et frais associés ; arrondis/assiettes/plafonds contractuels ; traitement des commandes en cours lors d'un changement de contrat ; annulation de toutes les lignes et recalcul des frais de livraison ; rapprochement et justificatifs des transferts.

Ces points peuvent être isolés par cas d'usage. Ils ne doivent pas bloquer le montage des parties déjà suffisamment définies, mais ne doivent pas être codés avec des valeurs inventées.

## 9. État d'avancement initial

À la référence de l'inventaire initial (avant les premiers commits Orders) :
- domaines existants repérés : Identity, Cooperatives, Catalog, Categories, Products, Media, Localization, Countries, Audit, Dashboard ;
- Orders : README, migration additive initiale (5 tables), 5 Enums et 5 modèles ajoutés ; services, autorisations, routes, interfaces, intégrations et tests restent à réaliser. Le domaine n'est pas terminé ni testé ;
- Sales, Inventory, Delivery, Finance, Statistics, Incidents et Communication : montage à réaliser après audit des éventuels composants transversaux ;
- aucune suite de tests n'est déclarée exécutée par ce plan.

**Ce plan est la référence de coordination de la V1. Les statuts techniques seront mis à jour module par module avec commit, fichiers, SHA et résultats de tests vérifiables.**


## 10. Journal d'exécution — 18 novembre 2026

- Orders : migration additive initiale, modèles et Enums créés sur `feature/Marketplace`.
- Commit de la fondation Orders : `1843a8ea4f8fa7d7832904ada3976f96fc9a78f2`.
- La règle de délai coopérative Q307–Q310 a été intégrée au README Orders : référence 12 h, plafond 24 h, dépassement sans refus automatique, intervention admin autorisée et auditée.
- Les tests PHP/MySQL n'ont pas été exécutés dans cette session. La fondation Orders est donc en cours, pas validée.


## 11. Complément — prix du catalogue Products

- Migration additive `2026_11_18_000004_add_prices_to_products_and_variants.php` : prix du produit en `DECIMAL(12,2)`, devise `MAD`, prix de variante enregistré nullable pour compatibilité historique.
- Règle métier mise à jour : chaque nouvelle variante doit obligatoirement porter son propre prix MAD positif (maximum deux décimales) ; aucun héritage du prix parent pour les nouvelles variantes. Les valeurs historiques nulles restent à traiter lors de la reprise des données.
- Formulaires coopérative et root : saisie du prix produit en MAD ; validation serveur positive et limitée à deux décimales.
- Modèles, factories, service Product, audit snapshot, affichages et tests de modèle mis à jour.
- Les prix restent nullable en base uniquement pour préserver les fiches historiques existantes ; les nouveaux formulaires exigent un prix. Le checkout devra refuser tout article historique sans prix exploitable.
- La gestion d'interface des prix de variantes reste à raccorder : le dépôt ne contient pas encore de contrôleur ni de vues de gestion des variantes.
- Migration et tests non exécutés dans cette session ; le module Products n'est pas déclaré terminé.

## 12. Journal d'exécution — Orders et prix Products

- Products : migration additive des prix du produit et de la surcharge facultative de variante ; prix MAD, stockage décimal, formulaires et affichages root/coopérative raccordés.
- Products : tests ajoutés pour la conservation décimale et le calcul du prix effectif d'une variante.
- Orders : OrderCreationService ajouté pour créer atomiquement commande globale, sous-commandes coopératives, lignes snapshotées et historique initial ; prix relus depuis Products, quantités/prix calculés en unités entières, articles sans prix refusés.
- Orders : CooperativeOrderResponseService ajouté pour confirmer/refuser une sous-commande, recalculer le statut global, tracer l'acteur et permettre une substitution root/super_admin affecté uniquement après expiration de l'échéance et avec motif.
- Tests Feature ajoutés pour la création multi-coopérative, les snapshots/prix, l'isolation de réponse et l'intervention root.
- Limites restantes : interface de gestion des prix variantes absente ; Sales/panier/checkout absent ; configuration centralisée des échéances à compléter (extension administrative bornée à 24 h ajoutée) ; propositions de changement client, expiration, Inventory, livraison, notifications et routes/policies restent à faire.
- Les tests PHP/MySQL et Pint ne sont pas exécutés dans cette session. Aucun module n'est déclaré terminé.

## 13. Décision et audit — stock produit / variantes (18 novembre 2026)

- Décision validée : un produit simple possède un stock au niveau du produit ; dès qu'il utilise des variantes, chaque variante possède son propre stock et le parent ne porte plus de stock actif.
- Conversion simple → variantes : la coopérative doit répartir la totalité du stock parent entre les variantes avant validation. Le transfert doit être atomique, verrouillé en base et tracé par des mouvements immuables ; le stock parent est soldé dans la même transaction. Aucun stock temporaire double n'est autorisé.
- Audit Git à `8a33f5ca736e2a16b514832351e7c757beec1d11` : aucun domaine `app/Domains/Inventory`, aucune migration de stock et aucun contrôleur/vue de gestion des variantes détectés. Le service de variantes existe mais autorisait encore un prix nul ; sa validation métier est renforcée.
- Quantités fractionnaires validées : précision fixe de trois décimales, stockage SQL `DECIMAL(15,3)` (ex. `1,250 kg`, `0,750 L`, `12,000 unités`).
- Unités : registre normalisé à codes stables et libellés ; sélection obligatoire dans une liste déroulante, aucune saisie libre ni unité personnalisée en V1 (reportée à V2).
- L'unité est définie sur le produit lorsqu'il n'a pas de variantes, ou sur chaque variante lorsqu'il en possède. Les soldes, réservations et mouvements utilisent l'unité de leur article.
- Aucune conversion automatique entre unités en V1. Les quantités ne sont additionnées ou comparées qu'à unité identique.
- Les options exactes du registre doivent être arrêtées avant le seeder définitif ; exemples de familles évoquées : masse, volume, longueur et unités de conditionnement.
- Les modifications de service et tests ci-dessus sont écrites sur `feature/Marketplace`. Tests non exécutés dans cet environnement ; aucune validation de suite n'est revendiquée.


## 14. Complément — unités de stock normalisées (18 novembre 2026)

- Décision confirmée : quantités fractionnaires avec trois décimales et colonne `DECIMAL(15,3)`.
- Le choix de l'unité se fait par liste déroulante issue d'un registre normalisé ; pas de champ libre ni d'unité personnalisée en V1.
- Produit sans variante : unité portée par le produit. Produit avec variantes : unité portée par chaque variante.
- Aucun calcul ni conversion automatique entre unités en V1 ; toutes les opérations de stock conservent l'unité de l'article concerné.
- Avant le seeder de référence, finaliser la liste exhaustive des unités autorisées et leurs libellés affichés.


## 15. Registre d'unités V1 — implémentation initiale (18 novembre 2026)

- Liste validée : kilogramme (\`kg\`), gramme (\`g\`), litre (\`L\`) et millilitre (\`ml\`). Les codes stockés sont stables et normalisés (\`kg\`, \`g\`, \`l\`, \`ml\`) ; le libellé conserve l'affichage usuel (\`Litre (L)\`).
- Migration additive \`2026_11_18_000005_create_product_units_and_attach_to_products.php\` : registre \`product_units\`, référence nullable \`unit_id\` sur \`products\` et \`product_variants\`, clés étrangères restrictives.
- Modèle \`ProductUnit\`, relations \`unit()\` sur Product et ProductVariant, seeder idempotent \`ProductUnitSeeder\` enregistré dans \`DatabaseSeeder\`.
- La nullabilité préserve les produits et variantes historiques. Le registre et les relations sont en place ; le raccordement des listes déroulantes et la validation obligatoire à la création/édition restent à réaliser avec les interfaces de produits et variantes.
- Aucun mécanisme de conversion n'est ajouté. Les tests de registre et de relations sont ajoutés mais ne sont pas exécutés dans cette session.
