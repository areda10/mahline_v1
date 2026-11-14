# MAHLINE V7.0 — Feuille de route globale des domaines

- **Version :** 5.47
- **Date :** 12 novembre 2026
- **Branche de référence :** `feature/Marketplace`
- **Commit audité :** `63b9d4cb767643fc30b1a03ef992d3aa1985ae05`
- **Nature :** référentiel fonctionnel cible et feuille de route, à confirmer par audits détaillés
- **Statut :** PROPOSITION DE SUIVI — les statuts doivent être confirmés par audit fonctionnel et tests.

## 1. Objet et règles de suivi

Ce document centralise la feuille de route de développement de tous les domaines MAHLINE. Il complète le backlog spécialisé Identity et ne remplace ni les décisions métier validées, ni les documents d'architecture.

**Registre des décisions fonctionnelles du premier release :** [`docs/roadmap/FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md`](FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md). Toutes les questions, réponses validées, conséquences et questions ouvertes concernant le SaaS de gestion + marketplace doivent être consignées dans ce fichier au fil du cadrage.

Le premier objectif produit est désormais défini comme un **SaaS de gestion pour les coopératives + marketplace publique**. Le développement des domaines nécessaires au premier release sera cadré question par question. L'harmonisation approfondie des frontières et le renforcement global des événements transversaux sont prévus dans une phase ultérieure dédiée, après le développement fonctionnel du premier release. Les exigences essentielles de sécurité, d'autorisation, d'isolation des coopératives et d'intégrité restent obligatoires à chaque étape.

## 2. Stratégie de livraison du premier release — parcours vertical prioritaire (Q252 VALIDÉE)

**Complément workflow Q253 :** une coopérative peut proposer une réduction de quantité ou un produit de remplacement, mais Sales ne modifie la commande qu'après acceptation explicite du client. La proposition reste limitée à la sous-commande concernée ; Inventory revalide la disponibilité et réserve le stock après confirmation. Le refus, l'absence de réponse, le délai et le traitement partiel restent à spécifier.

**Complément Q254 :** si le client refuse, seule la ligne concernée est annulée ; les autres lignes de la même sous-commande continuent. Sales recalcule les montants, Inventory libère uniquement le stock de la ligne annulée, Finance reçoit les montants définitifs et Communication informe le client. Les frais de livraison et le cas d'annulation de toutes les lignes restent à préciser.

**Complément Q255 :** sans réponse à l'échéance configurable, la proposition expire et seule la ligne concernée est annulée automatiquement ; les autres lignes continuent. Notifications préalables, expiration serveur, idempotence et arbitrage des réponses concurrentes sont obligatoires.

La priorité officielle de MAHLINE V1 est de livrer un parcours métier complet et utilisable **client → coopérative → transporteur**. Il n'est pas requis de terminer la spécification exhaustive de toutes les fonctions avancées de chaque domaine avant de commencer ce parcours. Chaque tranche livrée doit toutefois être suffisamment spécifiée, auditée et testée sur ses règles essentielles, autorisations, états, erreurs, dépendances et contrats inter-domaines.

### Démarrage du développement Marketplace V1 — périmètre initial validé par demande utilisateur
- Objectif : rendre le catalogue consultable et achetable, avec photos, prix et quantités/disponibilités cohérents, et intégrer la gestion de ces données dans les espaces des rôles.
- Coopérative / cooperative_admin : gérer ses produits autorisés, leurs photos, prix et données de stock selon les droits de son espace.
- Supervision : super_admin_root et super_admin selon périmètre d'affectation, sans contourner l'isolation des coopératives.
- Client / public : consulter la marketplace, les fiches produits, les photos, les prix et la disponibilité ; les quantités affichées ne doivent jamais révéler le stock interne sensible.
- Sources de vérité : Catalog/Products pour les données descriptives et prix publiés ; Media pour les fichiers/photos ; Inventory pour stock physique, réservations et quantité disponible ; Sales consomme un instantané cohérent au panier/checkout.
- **Seuil « Presque épuisé » (Q282) :** seuil entier configurable par produit par le `cooperative_admin` propriétaire ; indicateur « Presque épuisé » si la disponibilité nette est positive et inférieure ou égale au seuil, « Indisponible » à zéro ou moins, sinon « Disponible ». Le seuil n'affecte ni le stock ni la commandabilité. Une valeur par défaut de **5 unités** s'applique si le seuil est absent/null. Un seuil explicitement configuré à **0** désactive « Presque épuisé » pour le produit : statut « Disponible » tant que la disponibilité nette est positive, puis « Indisponible » à zéro ou moins. Les valeurs négatives et décimales sont refusées ; Products distingue null de 0. Products possède le paramètre, Inventory la quantité disponible, Catalog l'indicateur et Sales la revalidation ; validation serveur, isolation coopérative et audit obligatoires.
- **Indicateur public de stock (Q281) :** ne jamais exposer la quantité exacte au client. Catalog affiche « Indisponible » si la quantité disponible nette est nulle ou négative, « Presque épuisé » sous un seuil bas centralisé à définir avant implémentation, sinon « Disponible ». Inventory demeure source de vérité ; Sales revalide et réserve côté serveur. Les informations internes plus détaillées restent protégées par les permissions et l'isolation coopérative.
- **Retour de disponibilité après réapprovisionnement (Q280) :** Catalog rétablit automatiquement l'état disponible dès qu'Inventory confirme une quantité disponible strictement positive après prise en compte des réservations et engagements ; aucune validation manuelle n'est requise. Inventory demeure la source de vérité, Catalog n'expose pas le stock physique et Sales revalide/réserve côté serveur. Prévoir propagation fiable, idempotence, prévention des états obsolètes, isolation coopérative et audit ; aucun changement de prix ou de version promotionnelle.
- **Promotion sans variante disponible (Q279) :** conserver la promotion visible pendant sa validité, mais afficher « Indisponible » et bloquer toute commande. Inventory reste source de vérité ; Catalog expose l'indisponibilité sans révéler le stock physique ; Sales revalide disponibilité et réservation côté serveur au panier/checkout. Le retour du stock ne modifie pas la promotion ni ses versions.
- **Réactivation d'une promotion annulée (Q278) :** une promotion annulée ne peut pas être réactivée par remise en service d'une ancienne version. Une nouvelle version, avec nouvelle période et paramètres validés, peut relancer la promotion logique ; la version annulée reste clôturée et immuable. Catalog expose la nouvelle version pendant sa période applicable ; Sales conserve les snapshots des opérations déjà enregistrées. Prévoir identité/version distincte, contrôles de dates et de chevauchement, droits coopératifs et audit.
- **Modification d'une annulation planifiée (Q277) :** le `cooperative_admin` peut remplacer la date/heure prévue avant exécution. Chaque ancienne programmation reste historisée avec auteur et échéances ; la promotion demeure applicable jusqu'à l'échéance courante. Prévoir validation serveur, isolation coopérative, traitement atomique/idempotent des échéances et arbitrage des courses modification/exécution. Les snapshots Sales restent immuables.
- **Prise d'effet de l'annulation d'une promotion (Q276) :** annulation immédiate ou planifiée au choix du `cooperative_admin`. Une annulation planifiée ne désactive pas la promotion avant son instant d'effet ; Catalog continue d'exposer la version applicable jusque-là, puis cesse de l'exposer. Products conserve programmation, auteur, dates et événements ; Sales préserve les snapshots historiques. Prévoir validation chronologique, traitement fiable à l'échéance, isolation coopérative et journalisation.
- **Suppression/annulation des promotions (Q275) :** aucune suppression physique. La coopérative annule logiquement la promotion selon ses droits ; Products conserve versions, paramètres, auteurs, dates et événements. Catalog cesse de l'exposer à compter de l'effet de l'annulation ; Sales préserve tous les snapshots et commandes déjà enregistrés. Contrôles serveur, isolation coopérative, journalisation et absence de suppression en cascade obligatoires.
- **Remplacement d'une version planifiée (Q274) :** si une version future n'est pas encore entrée en vigueur, une nouvelle modification remplace la version planifiée courante. La version remplacée est conservée dans l'historique avec son état, ses paramètres, son auteur et ses dates ; la version actuellement active reste applicable jusqu'à la nouvelle date d'effet. Products gère le cycle de vie et Catalog expose la seule version applicable ; Sales ne réécrit pas les commandes enregistrées. Contrôles serveur, chronologie cohérente, isolation coopérative et journalisation obligatoires.
- **Entrée en vigueur des versions de promotion (Q273) :** le `cooperative_admin` choisit entre application immédiate après validation serveur ou planification à une date/heure future. Jusqu'à l'entrée en vigueur planifiée, la version précédente reste applicable ; Catalog expose la version correspondant à l'instant courant. Products conserve l'historique et les bornes d'effet ; Sales ne réécrit aucun panier/commande déjà enregistré. Validation chronologique, isolation coopérative et journalisation obligatoires.
- **Versionnement des promotions actives (Q272) :** toute modification d'une promotion active crée une nouvelle version ; l'ancienne version et ses paramètres sont conservés dans l'historique, avec auteur et date. Products possède le versionnement ; Catalog expose la version applicable ; Sales conserve les prix figés des paniers/commandes et aucune commande déjà enregistrée n'est réécrite. La règle exacte d'entrée en vigueur d'une nouvelle version (immédiate ou planifiée) reste à décider avant implémentation.
- **Publication des promotions V1 (Q271) :** publication immédiate sans approbation préalable dès que les validations serveur sont satisfaites ; une promotion planifiée reste inactive jusqu'à sa date de début et cesse d'être exposée à sa date de fin. Products valide et conserve les paramètres, Catalog n'expose que les promotions actives dans leur période, Sales revalide et fige le prix. Les actions sont journalisées ; les contrôles/suspensions de supervision restent possibles selon habilitations, sans écriture directe des paramètres commerciaux.
- **Gestion des promotions V1 — autorisations (Q270) :** seul le `cooperative_admin` crée, modifie et supprime les promotions des produits de sa propre coopérative. `super_admin` affecté et `super_admin_root` disposent d'une supervision selon leurs droits, sans écriture directe des paramètres commerciaux au premier release. Contrôles serveur et isolation stricte obligatoires. Products possède les paramètres et l'historique ; Catalog expose ; Sales revalide et fige le prix appliqué.
- **Affichage des promotions V1 (Q269) :** prix normal barré + prix promotionnel + badge de pourcentage pendant la seule période active. Pour les produits à variantes, le prix affiché et le taux doivent correspondre à la même variante disponible ; Catalog expose les données, Sales revalide et fige la variante/prix choisis.
- **Arrondi des promotions V1 (Q268) :** arrondi commercial au centime de MAD le plus proche, demi-centime vers le haut ; calculs intermédiaires décimaux exacts. La règle doit être centralisée et testée pour garantir des résultats identiques dans Products, Catalog, Sales et Finance. Cet arrondi de calcul ne modifie jamais les règles de rejet des prix/taux saisis invalides.
- **Précision du taux promotionnel V1 (Q267) :** jusqu'à deux décimales ; taux strictement positif et plafonné à 90 %. Validation serveur, stockage/calcul décimal exacts, aucun arrondi/troncature du taux saisi. Le prix promotionnel calculé suit l'arrondi commercial au centime le plus proche, demi-centime vers le haut (Q268).
- **Plafond des promotions V1 (Q266) :** le taux de réduction doit être strictement positif et inférieur ou égal à 90 % ; rejet obligatoire côté serveur au-delà de 90 %. La précision du taux est limitée à deux décimales (Q267) et le prix promotionnel calculé suit l'arrondi commercial au centime le plus proche, demi-centime vers le haut (Q268).
- **Calcul des promotions V1 (Q265) :** un taux unique défini au niveau du produit est appliqué au prix normal applicable de chaque variante (prix variante ou prix de base hérité). Le résultat est exposé par Catalog et revalidé/figé par Sales. La règle d'arrondi, la plage/précision du taux et les cumuls restent à décider avant codage.
- **Portée des promotions V1 (Q264) :** la promotion est portée par le produit et s'applique à toutes ses variantes ; aucune promotion propre à une variante en V1. Le mécanisme exact de calcul du prix promotionnel par variante reste ouvert et devra être validé avant implémentation.
- **Promotions V1 (Q263) :** promotion simple par produit avec prix normal, prix promotionnel inférieur et période de validité début/fin. Products possède les paramètres, Catalog expose le prix applicable, Sales revalide et fige le prix de vente. Les coupons, campagnes complexes et cumuls sont exclus ; fuseau/bornes, chevauchements et variantes restent à préciser.
- **Saisie des prix V1 (Q262) :** refuser côté serveur toute valeur comportant plus de deux décimales significatives ; ne jamais arrondir ni tronquer silencieusement. Cette validation couvre les prix de base et de variantes, quelle que soit l'interface/API utilisée.
- **Précision monétaire V1 (Q261) :** les prix en MAD sont limités à deux décimales ; utiliser une représentation décimale exacte, jamais des flottants binaires pour les calculs monétaires. Les règles d'arrondi détaillées pour taxes, remises, frais et répartitions restent à cadrer.
- **Devise V1 (Q260) :** MAD est la devise du premier release. Le modèle de prix doit permettre une extension ultérieure à d'autres devises, mais aucune conversion automatique ni mélange de devises n'est autorisé ; Sales fige la devise avec le montant et Finance consomme ces données sans conversion implicite.
- Prix V1 : prix de base obligatoire par produit ; prix spécifique facultatif par variante ; à défaut, la variante hérite du prix de base. Catalog affiche le prix applicable et Sales le fige dans le panier/commande ; toute validation de prix est serveur.
- Affichage V1 : lorsqu'un produit possède plusieurs variantes commercialisables avec des prix différents, la carte affiche le prix applicable le plus bas avec la mention « À partir de ». La fiche produit présente les prix des variantes ; Sales revalide le prix de la variante sélectionnée et le fige dans le panier/commande. Le calcul du minimum est côté serveur.
- Pour un produit sans variante, la carte affiche directement le prix de base, sans la mention « À partir de » ; Catalog l'expose depuis Products et Sales le revalide puis le fige côté serveur.
- Affichage V1 : lorsqu'un produit possède plusieurs variantes commercialisables avec des prix différents, la carte affiche le prix applicable le plus bas avec la mention « À partir de ». La fiche produit présente les prix des variantes ; Sales revalide le prix de la variante sélectionnée et le fige dans le panier/commande. Le calcul du minimum est côté serveur.
- Calcul du prix minimum : exclure les variantes en rupture ou indisponibles ; seules les variantes commercialisables et disponibles participent au prix « À partir de ». Inventory fournit l'information de disponibilité, et Sales la revalide au panier/checkout. Si aucune variante n'est disponible, afficher l'indisponibilité sans prix d'appel achetable.
- Première étape obligatoire : audit du code existant et des routes, contrôleurs, services, modèles, migrations, policies/gates, vues, tests et liaisons inter-domaines avant toute modification.
- Développement incrémental sur une branche de travail dédiée, tests ciblés puis suite requise ; aucune fusion vers main sans demande explicite.

### Phasage prioritaire

1. **Socle d'accès et acteurs — Identity, Cooperatives, Localization :** compte customer, authentification, espaces et habilitations, identité des coopératives et adresses nécessaires.
2. **Offre marketplace — Catalog, Categories, Products :** gestion et publication des produits, consultation publique, disponibilité et données indispensables à l'achat.
3. **Parcours commercial — Sales :** profil commercial, panier, checkout, commande multi-coopératives, sous-commandes par coopérative et consultation de l'historique.
4. **Traitement des commandes — Cooperatives, Sales, Inventory :** réception des sous-commandes, examen par la coopérative, éventuelle proposition de modification de quantité, décision client lorsque requise, confirmation, réservation de stock et préparation.
5. **Expédition — Delivery :** affectation/prise en charge par le transporteur, expédition par coopérative, statuts, preuves, suivi client et données de collecte COD nécessaires.
6. **Finance minimale — Finance :** montants de commande et ventilations, frais de livraison, mode COD, montants à encaisser, commissions applicables et figées, enregistrements des encaissements/remises et références de reversement indispensables à la traçabilité du premier parcours.
7. **Soutien opérationnel — Media, Communication :** stockage et accès sécurisé aux documents/preuves ainsi que les e-mails et notifications indispensables au parcours.

### Périmètre Finance V1 minimal

Finance n'a pas besoin d'avoir toutes ses fonctions avancées spécifiées et développées pour permettre le lancement du parcours prioritaire. Son socle V1 doit néanmoins garantir l'enregistrement exact et traçable des montants, commissions, frais, encaissements COD et références de reversement. Les rapprochements avancés, contestations complexes, crédits/recouvrements, automatisation complète des reversements et rapports approfondis restent au backlog progressif ; ils ne sont ni supprimés ni considérés comme validés.

### Règles de pilotage

- Les domaines restent propriétaires de leurs responsabilités : Sales (panier/commandes), Inventory (stock/réservations), Delivery (expéditions/transporteurs/événements de livraison et collecte), Finance (écritures/fonds/commissions/reversements marketplace), Subscriptions / SaaS Billing (abonnements dus à MAHLINE).
- Les fonctions non indispensables au parcours sont conservées dans les inventaires et traitées après le socle opérationnel, selon leurs dépendances et risques.
- Sécurité, autorisations côté serveur, isolation des données par coopérative, audit, intégrité, versionnement et idempotence ne sont jamais reportés.
- Le changement de quantité proposé par une coopérative doit être cadré comme une règle dédiée, notamment pour déterminer si l'accord explicite du client est requis avant confirmation définitive ; Q252 ne tranche pas ce point.
- Le passage d'une tranche au codage exige une spécification suffisante et des critères d'acceptation ; le domaine complet n'a pas besoin d'être exhaustivement finalisé si le périmètre de la tranche est maîtrisé.


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
- [ ] Gérer les propositions de modification de sous-commande par la coopérative : réduction de quantité ou remplacement de produit, avec acceptation explicite du client avant toute modification ; conserver la commande initiale tant que la proposition n'est pas acceptée (Q253).
- [ ] Isoler les propositions et réponses par sous-commande et lignes concernées ; historiser les valeurs initiales/proposées, les différences de prix, l'auteur, la notification et la réponse ; définir le refus, l'absence de réponse, les délais et le traitement des autres lignes avant codage (Q253).
- [ ] En cas de refus explicite du client, annuler uniquement la ligne concernée et poursuivre le traitement des autres lignes de la sous-commande ; conserver l'historique, recalculer les totaux et transmettre les montants définitifs à Finance (Q254).
- [ ] Coordonner avec Inventory la non-réservation ou la libération idempotente du stock de la ligne annulée, sans affecter les autres lignes ; notifier le client de l'annulation partielle (Q254).
- [ ] Définir et implémenter l'échéance configurable des propositions de modification ; sans réponse à expiration, Sales annule uniquement la ligne concernée, notifie le client et déclenche une libération de stock ciblée et idempotente (Q255).
- [ ] Définir Delivery et son intégration avec les transporteurs et Inventory.
- [ ] Définir les contrats avec Identity, Localization, Catalog, Inventory, Finance et Audit.
- [ ] Prévoir tests de concurrence, disponibilité, propriété et isolation.
- [ ] Ne pas placer les factures, paiements ou commissions dans Sales : ils appartiennent à Finance.

### 3.12 Inventory — À démarrer

**Responsabilité :** stock physique, mouvements et réservations.

- [ ] Spécifier inventaires, articles, mouvements, réservations, entrepôts et ajustements.
- [ ] Définir les règles de stock disponible et réservé.
- [ ] Lorsqu'une proposition de modification est acceptée par le client, vérifier à nouveau la disponibilité du produit/quantité retenu et ne réserver le stock qu'au moment de la confirmation coopérative selon les règles COD ; aucune réservation ne doit être fondée sur une modification non acceptée (Q253).
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
- [ ] Pour chaque remboursement confirmé, recalculer la commission à annuler sur le montant net cumulé des produits remboursés après remise et hors livraison, puis comptabiliser uniquement la différence avec les corrections déjà enregistrées ; chaque correction reste distincte et liée à la commission initiale et au remboursement déclencheur.
- [ ] Déclencher l'enregistrement de la correction immédiatement après la confirmation définitive du remboursement transmise par Sales ; ne pas attendre le rapprochement périodique ni la réception effective des fonds par le client. Une demande non confirmée ne déclenche aucune correction.
- [ ] Préserver l'immutabilité et l'historique des commissions initiales et corrections ; plafonner le cumul à la commission initiale, empêcher le double comptage d'un remboursement et définir précision/arrondi au centime, concurrence et idempotence.
- [ ] Régulariser les corrections de commission sur les reversements coopératives : ajuster le montant si le reversement n'est pas exécuté ; après exécution, créer un crédit distinct au bénéfice de la coopérative. Imputer automatiquement les crédits sur les reversements successifs, dans la limite du montant de chacun, reporter les soldes jusqu'à épuisement et appliquer un ordre FIFO (crédit le plus ancien en premier) lorsque plusieurs crédits coexistent (Q227-Q229) ; suspendre uniquement un crédit contesté, sans bloquer l'imputation des autres crédits non contestés (Q230) ; si la contestation est rejetée, réactiver le crédit en conservant son rang FIFO d'origine, sans réimputation rétroactive des reversements exécutés (Q231) ; si une contestation est acceptée, annuler le solde restant du crédit invalide par une écriture corrective distincte, en conservant le crédit initial et les imputations antérieures (Q232) ; reconnaître les montants déjà imputés et versés comme une créance distincte sur la coopérative, récupérable par déduction sur ses prochains reversements (Q233), avec traçabilité et sans double récupération ; récupérer prioritairement ces créances avant l'imputation des crédits ordinaires (Q234) ; lorsque plusieurs créances MAHLINE coexistent pour une même coopérative, les recouvrer selon FIFO, en apurant la plus ancienne avant la suivante et en reportant son solde prioritaire si le reversement est insuffisant (Q235) ; déterminer l'ancienneté par la date de création initiale immuable de chaque créance, sans réinitialisation lors d'une validation, contestation, suspension ou modification de statut ; départager les créances de date et heure identiques par l'identifiant ULID de la créance en ordre croissant, selon une comparaison normalisée et déterministe (Q237) ; suspendre uniquement une créance MAHLINE contestée et l'exclure temporairement du recouvrement, sans bloquer les autres créances non contestées ; conserver le montant, l'historique et le rang FIFO initial de la créance suspendue, distincte des crédits ordinaires contestés (Q238) ; après rejet formel de la contestation, réactiver automatiquement la créance validée sans créer de nouvelle créance ni modifier son rang FIFO initial, et sans réimputation rétroactive des reversements exécutés (Q239) ; si la contestation est acceptée et la créance déclarée invalide, annuler uniquement son solde restant par une écriture corrective distincte, en conservant la créance initiale et toutes les imputations exécutées ; les montants déjà versés restent une créance distincte à récupérer conformément à Q233, sans double annulation (Q240) ; conserver dans les rapports financiers la créance initiale, l'écriture corrective, le motif, les justificatifs et l'auteur de la décision, avec accès historique traçable (Q241) ; autoriser la consultation des détails et justificatifs à `super_admin_root` pour toutes les coopératives, au `super_admin` affecté et au `cooperative_admin` de la coopérative concernée uniquement ; refuser par défaut l'accès aux rôles `user` et `customer`, appliquer les contrôles serveur à chaque lecture/téléchargement/export et journaliser les accès (Q242) ; permettre explicitement au `cooperative_admin` de consulter et télécharger les justificatifs de sa propre coopérative, sans accès transversal, avec journalisation de chaque téléchargement et liens privés temporaires (Q243) ; limiter le premier release au téléchargement individuel de chaque justificatif, sans archive ZIP ni téléchargement groupé (Q244) ; conserver toutes les versions successives des justificatifs avec date, auteur et historique, sans écraser les anciennes versions ; seule la dernière est présentée comme actuelle, et l'historique reste accessible selon les habilitations (Q245) ; autoriser l'ajout et le remplacement des justificatifs à `super_admin_root` pour toutes les coopératives, au `super_admin` affecté et au `cooperative_admin` de sa propre coopérative ; refuser par défaut ces opérations aux rôles `user` et `customer`, contrôler et journaliser chaque opération, et créer une nouvelle version sans écraser l'historique (Q246) ; rendre chaque justificatif immédiatement visible aux utilisateurs autorisés avec le statut initial « à vérifier », sans valider financièrement le dépôt ; suivre le statut par version et journaliser les changements (Q247) ; autoriser la validation ou le refus par `super_admin_root` pour toutes les coopératives et par `super_admin` affecté uniquement dans son périmètre, sans permettre au `cooperative_admin` de valider ses propres dépôts ; contrôler et journaliser chaque décision par version (Q248) ; après refus, permettre au `cooperative_admin` de déposer une nouvelle version au statut « à vérifier », en conservant la version refusée, son motif et le lien d'historique, puis soumettre la nouvelle version au même circuit de validation (Q249) ; autoriser le remplacement d'une pièce déjà validée, mais créer obligatoirement une nouvelle version au statut « à vérifier », conserver la version validée antérieure et repasser par le circuit de validation Q247-Q248 avant toute nouvelle acceptation (Q250) ; maintenir l'ancienne version validée comme preuve officielle et utilisable tant que la nouvelle est en attente ou refusée ; lorsque la nouvelle est validée, marquer l'ancienne « remplacée » sans la supprimer et distinguer dernière version déposée et dernière version validée (Q251) ; après apurement des créances, appliquer le solde aux crédits ordinaires selon leur propre FIFO et reporter les soldes non réglés sans modifier leur ancienneté ; définir les règles de plafond et les cas de reversements insuffisants ; conserver les liens d'origine et l'historique de chaque imputation.
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

Ordre de travail prioritaire adopté par Q252 — parcours vertical client → coopérative → transporteur :

1. Identity + Cooperatives + Localization : socle des acteurs, comptes, accès et adresses nécessaires.
2. Catalog + Categories + Products : création, publication, consultation et disponibilité des produits.
3. Sales : profil commercial, panier, checkout, commandes multi-coopératives et sous-commandes.
4. Cooperatives + Sales + Inventory : réception et traitement des commandes, ajustement éventuel des quantités, confirmation et réservation de stock.
5. Delivery : préparation des expéditions, transporteurs, prise en charge, suivi, preuves et collecte COD.
6. Finance : socle minimal des montants, ventilations, frais, commissions, encaissements COD et références de reversement.
7. Media + Communication : fichiers/preuves, documents et notifications nécessaires au parcours.
8. Audit et intégration transversale : contrôles de sécurité, isolation, contrats, événements, tests d'intégration et préparation release.
9. Après stabilisation du parcours : compléter progressivement les fonctions avancées de Finance, Identity, Inventory, Delivery, Media, Communication, CMS, Dashboard, Subscriptions / SaaS Billing et Export / Compliance selon dépendances et priorité produit.

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

## Parcours directeur de développement — Client → Coopérative → Transporteur
Le développement fonctionnel du premier release suit le parcours opérationnel transversal défini dans le registre de découverte :
1. Client / Identity : inscription en statut pending, vérification e-mail puis activation automatique ; connexion, profil et adresses après activation.
2. Marketplace / Catalog + Products : consultation des coopératives et découverte des produits, prix et disponibilités.
3. Panier / commande / Sales : panier multi-coopératives, quantités, validation et passage de commande.
4. Réception / espace coopérative : consultation de la sous-commande, des lignes, quantités et informations client autorisées.
5. Confirmation / Sales + Inventory : acceptation, refus ou ajustement des lignes, confirmation de sous-commande et réservation de stock selon les règles métier.
6. Préparation / remise / Delivery : préparation du colis, déclaration prêt et remise au transporteur.
7. Suivi / Delivery + Communication : statuts d'expédition et notifications au client et à la coopérative.

**Identité client (Q286) :** l'inscription crée un compte client en attente ; la vérification e-mail réussie active automatiquement ce compte sans validation administrative. Avant activation et vérification, pas d'accès authentifié à l'espace client ni de commande. La consultation publique du catalogue reste possible. Cette activation automatique est propre au parcours d'inscription client et ne modifie pas les règles d'activation/réactivation des comptes internes. Les liens de vérification sont sécurisés, expirants et invalidés après succès ; protection contre abus et énumération ; contrôle serveur et audit.

**Modification des adresses utilisées (Q290) : une modification du carnet d'adresses ne s'applique qu'aux commandes futures. Toutes les commandes déjà créées conservent leur snapshot de livraison initial, même si elles ne sont pas encore expédiées. Le client peut modifier l'adresse enregistrée ; aucune propagation vers les commandes existantes n'est autorisée. Une éventuelle correction de livraison d'une commande en cours relève d'un processus Sales/Delivery distinct, contrôlé et tracé.

Coordonnées de livraison (Q289) :** chaque adresse enregistrée possède son propre nom de destinataire et son propre numéro de téléphone, indépendants du profil client. Sales conserve l'association au client et copie l'adresse complète ainsi que les coordonnées du destinataire dans le snapshot de livraison de la commande au checkout. Une modification ultérieure du carnet d'adresses ou du profil ne réécrit pas la commande. L'adresse doit appartenir au client authentifié ; les coordonnées du destinataire ne confèrent aucun droit sur son compte. Les règles détaillées de validation des champs restent à préciser.

**Adresses client (Q288) :** le client peut enregistrer plusieurs adresses de livraison et désigner une adresse par défaut, proposée automatiquement à la prochaine commande ; il peut en choisir une autre au checkout. Localization possède les adresses structurées ; Sales gère leur association au client, le choix par défaut et le snapshot de l'adresse retenue dans la commande. Contrôle serveur strict de propriété et isolation entre clients ; les modifications ultérieures du carnet d'adresses ne réécrivent pas les commandes existantes. Le nombre maximal d'adresses et les champs/règles de validation restent à préciser.

**Vérification e-mail client (Q287) :** le lien de vérification est valable 24 heures à compter de son émission. Après expiration, le client peut demander un nouveau lien dans la limite de 5 demandes par heure, puis blocage temporaire d'une heure ; chaque nouveau lien invalide immédiatement tous les liens précédents. Un seul lien reste utilisable par compte. L'expiration, l'invalidation et la validation sont contrôlées côté serveur ; aucun jeton ou secret n'est journalisé. Cette durée de 24 heures remplace l'ancienne durée de 60 minutes des décisions Q6-Q7.

**Règle de pilotage :** développer les capacités nécessaires étape par étape dans cet ordre, avec audit préalable des implémentations existantes, contrats entre domaines, tests ciblés et régression. Ne pas attendre qu'un domaine entier soit achevé pour avancer dans le parcours. Les domaines conservent la propriété de leurs données : Identity (comptes), Products (données commerciales et seuils), Inventory (stock/réservations), Catalog (exposition marketplace), Sales (panier/commandes/sous-commandes), Delivery (préparation/transport), Communication (notifications).

### Version 5.47 — 14 novembre 2026
- Enregistrement de Q290 : modifications du carnet d'adresses applicables uniquement aux commandes futures ; snapshots historiques immuables même avant expédition.
- Toute correction de livraison d'une commande en cours doit passer par un processus Sales/Delivery distinct, contrôlé et tracé.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.46 — 14 novembre 2026
- Enregistrement de Q289 : coordonnées de destinataire propres à chaque adresse et snapshot complet au checkout.
- Les règles détaillées de validation du nom et du téléphone restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.45 — 14 novembre 2026
- Enregistrement de Q288 : plusieurs adresses de livraison par client, adresse par défaut et choix alternatif au checkout ; isolation et snapshot dans la commande.
- Le nombre maximal d'adresses et les champs/règles de validation restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.44 — 14 novembre 2026
- Enregistrement de Q287 : lien de vérification e-mail client valable 24 heures, renouvelable sous limite anti-abus ; émission d'un nouveau lien invalidant immédiatement les précédents.
- Harmonisation de la durée indiquée dans Q6-Q7 ; maintien de la limite de renvoi Q8.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.43 — 14 novembre 2026
- Enregistrement de Q286 : inscription client en attente, activation automatique après vérification e-mail réussie.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.42 — 14 novembre 2026
- Enregistrement de Q285 : changement du seuil appliqué immédiatement après validation serveur.
- Ajout du parcours directeur Client → Coopérative → Transporteur en sept étapes et de la règle de développement transversal progressif.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.41 — 14 novembre 2026
- Enregistrement de Q284 : seuil personnalisé à zéro = désactivation de « Presque épuisé », distincte d'une absence de configuration (défaut 5 unités).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.40 — 14 novembre 2026
- Enregistrement de Q283 : seuil « Presque épuisé » fixé par défaut à 5 unités, configurable individuellement par produit.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.39 — 14 novembre 2026
- Enregistrement de Q282 : seuil « Presque épuisé » configurable par produit par le cooperative_admin, avec valeur par défaut centrale à définir.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.38 — 14 novembre 2026
- Enregistrement de Q281 : indicateur public de disponibilité sans quantité exacte, avec seuil « Presque épuisé » à définir avant codage.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.37 — 14 novembre 2026
- Enregistrement de Q280 : disponibilité marketplace rétablie automatiquement à la confirmation Inventory d'un stock disponible net des réservations.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.36 — 13 novembre 2026
- Enregistrement de Q279 : promotion visible mais non achetable lorsque toutes les variantes sont indisponibles.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.35 — 13 novembre 2026
- Enregistrement de Q278 : reprise d'une promotion annulée via une nouvelle version, sans réactivation de l'ancienne version.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.34 — 13 novembre 2026
- Enregistrement de Q277 : modification d'une annulation planifiée avant son effet, avec historique des échéances remplacées.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.33 — 13 novembre 2026
- Enregistrement de Q276 : choix entre annulation immédiate et planifiée d'une promotion active.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.32 — 13 novembre 2026
- Enregistrement de Q275 : annulation logique uniquement, sans suppression physique des promotions ni de leur historique.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.31 — 13 novembre 2026
- Enregistrement de Q274 : remplacement de la version promotionnelle future non encore active, avec conservation de la version remplacée dans l'historique.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.30 — 13 novembre 2026
- Enregistrement de Q273 : choix entre application immédiate ou planifiée des nouvelles versions de promotion ; maintien de l'ancienne version jusqu'à la date d'effet.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.29 — 13 novembre 2026
- Enregistrement de Q272 : versionnement obligatoire des modifications d'une promotion active, conservation de l'historique et protection des commandes déjà enregistrées ; entrée en vigueur d'une nouvelle version à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.28 — 13 novembre 2026
- Enregistrement de Q271 : publication immédiate sans validation préalable après contrôles serveur ; activation selon les dates, journalisation et supervision.

### Version 5.27 — 13 novembre 2026
- Enregistrement de Q270 : gestion des promotions réservée au `cooperative_admin` dans sa coopérative ; supervision sans modification directe des paramètres commerciaux par les rôles supérieurs.
- Confirmation des contrôles serveur et de l'isolation stricte des coopératives.

### Version 5.26 — 13 novembre 2026
- Enregistrement de Q269 : présentation visible du prix normal barré, prix promotionnel et pourcentage de remise.
- Ajout de la règle de cohérence d'affichage pour les produits à variantes.

### Version 5.25 — 13 novembre 2026
- Enregistrement de Q268 : arrondi commercial du résultat promotionnel au centime le plus proche, demi-centime vers le haut.
- Exigence de calcul décimal exact, de centralisation et de tests transversaux ajoutée.

### Version 5.24 — 13 novembre 2026
- Enregistrement de Q267 : précision du taux limitée à deux décimales ; validation serveur et représentation décimale exacte.
- Règle d'arrondi du prix final promotionnel maintenue comme point ouvert avant codage.

### Version 5.23 — 13 novembre 2026
- Enregistrement de Q266 : plafond de réduction fixé à 90 % et validation serveur obligatoire.
- Précision du taux et règles d'arrondi conservées comme points ouverts avant codage.

### Version 5.22 — 13 novembre 2026
- Enregistrement de Q265 : réduction en pourcentage appliquée au prix applicable de chaque variante.
- Règle d'arrondi et bornes de saisie du taux conservées comme décisions préalables au codage.

### Version 5.21 — 13 novembre 2026
- Enregistrement de Q264 : promotion au niveau du produit, applicable à toutes les variantes ; pas de promotion distincte par variante au premier release.
- Calcul exact du prix promotionnel des variantes conservé comme décision préalable au développement.

### Version 5.20 — 13 novembre 2026
- Enregistrement de Q263 : promotions simples limitées à un prix promotionnel et une période de validité.
- Frontières Products/Catalog/Sales/Finance documentées ; questions techniques de temporalité et chevauchement conservées comme ouvertes.

### Version 5.19 — 13 novembre 2026
- Enregistrement de Q262 : rejet des prix ayant plus de deux décimales ; aucune correction automatique.
- Validation serveur obligatoire sur les prix de base et de variantes.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.18 — 13 novembre 2026
- Enregistrement de Q261 : précision maximale de deux décimales pour les prix en MAD.
- Exigence de calcul monétaire en décimal exact ; règles d'arrondi détaillées à cadrer.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.17 — 13 novembre 2026
- Enregistrement de Q260 : MAD comme devise du premier release, avec extension multi-devises possible ultérieurement.
- Les montants figés par Sales conservent leur devise ; aucune conversion implicite ni mélange de devises.
- Alignement de la feuille de route avec le registre de découverte pour Q257 à Q259 et suppression d'une ligne d'affichage Q257 dupliquée.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.16 — 13 novembre 2026
- Enregistrement de Q259 : un produit sans variantes affiche directement son prix de base, sans « À partir de ».
- Alignement Catalog/Products/Sales : exposition du prix de base, revalidation serveur et gel dans le panier/la commande.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.15 — 13 novembre 2026
- Enregistrement de Q258 : le prix minimum exclut les variantes en rupture ou indisponibles ; il reflète une variante réellement achetable.
- Inventory fournit la disponibilité et Sales la revalide au panier/checkout ; si aucune variante n'est disponible, la carte indique l'indisponibilité sans prix d'appel.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.14 — 13 novembre 2026
- Enregistrement de Q257 : carte produit avec variantes à prix différents = prix applicable le plus bas, précédé de « À partir de ».
- Précision du calcul côté serveur, de l'affichage des prix par variante sur la fiche et de la revalidation/du gel par Sales.
- Devise, arrondis, variantes indisponibles, promotions et produits sans variantes restent à préciser.
- Aucun code applicatif modifié ; aucune fusion.

### Version 5.13 — 13 novembre 2026
- Enregistrement de Q256 : prix de base par produit et surcharge de prix facultative par variante, avec héritage du prix de base.
- Clarification des responsabilités Products, Catalog, Sales et Finance sur l'exposition et le gel du prix.
- Devise, arrondis, promotions et présentation des variantes à prix différents restent à définir.
- Aucun code applicatif modifié.

### Version 5.12 — 13 novembre 2026
- Enregistrement de Q255 : expiration configurable d'une proposition sans réponse et annulation automatique de la seule ligne concernée.
- Formalisation du périmètre de démarrage Marketplace V1 : photos via Media, prix via Catalog/Products, quantités disponibles via Inventory, vues par rôle et catalogue client.
- Audit du code existant obligatoire avant toute modification ; aucune fusion vers main.
- Aucun code applicatif modifié dans cette mise à jour documentaire.

### Version 5.11 — 13 novembre 2026
- Enregistrement de Q254 dans la feuille de route Sales/Inventory : refus client = annulation de la seule ligne concernée, poursuite des autres lignes.
- Ajout des exigences de recalcul des totaux, libération ciblée et idempotente du stock, transmission des montants à Finance et notification client.
- Les règles de frais de livraison et d'annulation de toutes les lignes restent ouvertes.
- Aucun code applicatif modifié et aucune fusion.

### Version 5.10 — 13 novembre 2026
- Enregistrement de Q253 dans le workflow Sales/Cooperatives : réduction de quantité ou remplacement uniquement sur proposition et acceptation explicite du client.
- Alignement de Sales et Inventory sur la conservation de la commande initiale avant acceptation, l'isolation par sous-commande et le contrôle du stock après accord.
- Les délais, refus/absence de réponse, traitement partiel et ajustements de prix/livraison restent ouverts.
- Aucun code applicatif modifié et aucune fusion.

### Version 5.9 — 13 novembre 2026
- Enregistrement de Q252 : priorité officielle au parcours vertical client → coopérative → transporteur, sans attendre la spécification exhaustive de toutes les fonctions avancées.
- Remplacement de l'ordre de travail antérieur par un phasage dépendant du parcours opérationnel ; définition du socle Finance V1 minimal.
- Les fonctions avancées restent au backlog progressif ; les exigences de sécurité, d'isolation, d'audit et d'intégrité restent obligatoires.
- Aucun code applicatif modifié et aucune fusion.

### Version 5.8 — 13 novembre 2026
- Enregistrement de Finance Q251 : l'ancienne version validée reste officielle et utilisable pendant la vérification d'un remplacement.
- À validation de la nouvelle version, l'ancienne devient « remplacée » sans suppression ; matrice Finance alignée.
- Aucun code applicatif modifié et aucune fusion.

### Version 5.7 — 13 novembre 2026
- Enregistrement de Finance Q250 : remplacer un justificatif déjà validé crée une nouvelle version au statut « à vérifier ».
- Conservation de la version validée antérieure et nouvelle validation obligatoire via Q247-Q248 ; matrice Finance alignée.
- Aucun code applicatif modifié et aucune fusion.

### Version 5.6 — 13 novembre 2026
- Enregistrement de Finance Q249 : nouvelle version possible après refus, au statut initial « à vérifier ».
- Conservation de la version refusée, de son motif et de l'historique ; retour obligatoire dans le circuit de validation Q247-Q248.
- Matrice Finance alignée ; aucun code applicatif modifié et aucune fusion.

### Version 5.5 — 13 novembre 2026
- Enregistrement de Finance Q248 : validation/refus des justificatifs par `super_admin_root` globalement et `super_admin` affecté dans son périmètre ; le `cooperative_admin` ne valide pas ses propres dépôts.
- Matrice Finance alignée sur la séparation dépôt/contrôle, avec contrôle serveur et journalisation par version.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.4 — 13 novembre 2026
- Enregistrement de Finance Q247 : les justificatifs déposés sont immédiatement visibles aux personnes habilitées, avec statut initial « à vérifier » jusqu'à validation.
- Le dépôt ne vaut pas acceptation financière ; suivi du statut par version et journalisation. Autorité de validation à définir.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.3 — 13 novembre 2026
- Enregistrement de Finance Q246 : ajout/remplacement des justificatifs par `super_admin_root`, `super_admin` affecté et `cooperative_admin` de la coopérative concernée.
- Maintien de l'isolation par coopérative, contrôle serveur, journalisation et versionnement sans écrasement ; matrice Finance alignée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.2 — 13 novembre 2026
- Enregistrement de Finance Q245 : versionner les justificatifs, conserver les versions antérieures avec date, auteur et historique, et identifier la dernière version comme actuelle.
- Maintenir les habilitations historiques et l'intégrité des pièces ; matrice Finance alignée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.1 — 13 novembre 2026
- Enregistrement de Finance Q244 : téléchargement individuel uniquement des justificatifs ; aucun ZIP ni téléchargement groupé dans le premier release.
- La matrice Finance conserve les habilitations Q242-Q243 et applique contrôles et audit à chaque pièce téléchargée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 5.0 — 13 novembre 2026
- Enregistrement de Finance Q243 : autoriser le `cooperative_admin` à consulter et télécharger les justificatifs de sa propre coopérative.
- Maintenir l'isolation stricte, contrôler et journaliser chaque téléchargement et utiliser des liens privés temporaires ; matrice Finance alignée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.9 — 13 novembre 2026
- Enregistrement de Finance Q242 : accès aux détails et justificatifs réservé à `super_admin_root`, au `super_admin` affecté et au `cooperative_admin` de la coopérative concernée.
- Isolation stricte par coopérative, aucun accès par défaut pour `user`/`customer`, vérification serveur des lectures et exports et journalisation des accès ; matrice Finance alignée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.8 — 13 novembre 2026
- Enregistrement de Finance Q241 : les rapports financiers conservent la créance initiale, l'écriture corrective, le motif, les justificatifs et l'auteur de la décision d'annulation.
- La matrice Finance distingue l'annulation des imputations déjà exécutées et des créances distinctes de Q233 ; l'historique reste consultable selon les habilitations.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.7 — 13 novembre 2026
- Enregistrement de Finance Q240 : en cas de contestation acceptée et de créance MAHLINE déclarée invalide, annuler uniquement le solde restant par une écriture corrective distincte.
- Préserver la créance initiale et les imputations exécutées ; les montants déjà versés restent une créance distincte à récupérer selon Q233, sans double annulation. Matrice Finance alignée sur Q240.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.6 — 13 novembre 2026
- Enregistrement de Finance Q239 : après rejet formel de la contestation, la créance confirmée valide est réactivée automatiquement en conservant son rang FIFO initial.
- Aucun reversement exécuté n'est recalculé ni réimputé rétroactivement ; la matrice Finance est alignée avec Q236-Q239.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.5 — 13 novembre 2026
- Enregistrement de Finance Q238 : seule la créance MAHLINE contestée est suspendue et exclue temporairement du recouvrement ; les autres créances non contestées restent récupérables.
- Distinction maintenue entre les créances MAHLINE (Q233-Q238) et les crédits ordinaires de coopérative (Q227-Q232).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.4 — 13 novembre 2026
- Enregistrement de Finance Q237 : les créances de même date et heure de création sont départagées par leur ULID en ordre croissant.
- Alignement de la matrice Finance : date de création initiale immuable comme premier critère FIFO (Q236), ULID de la créance comme critère secondaire déterministe.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.3 — 13 novembre 2026
- Enregistrement de Finance Q236 : le rang FIFO des créances repose sur leur date de création initiale, qui reste immuable malgré les changements ultérieurs de statut ou de traitement.
- Ajout à la matrice Finance du besoin d'un départage technique stable lorsque plusieurs créances portent la même date de création.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.2 — 13 novembre 2026
- Enregistrement de Finance Q235 : les créances MAHLINE multiples envers une même coopérative sont recouvrées selon FIFO, la plus ancienne étant apurée avant la suivante.
- Si le reversement est insuffisant, le solde de la créance la plus ancienne conserve sa priorité ; les créances plus récentes restent en attente.
- Alignement de la matrice Finance avec Q234-Q235 : priorité des créances sur les crédits ordinaires, puis FIFO indépendant pour les crédits ordinaires après apurement des créances.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.1 — 13 novembre 2026
- Enregistrement de Finance Q234 : les créances de MAHLINE sur la coopérative sont récupérées avant l'imputation des crédits ordinaires.
- Une fois les créances réglées, le solde du reversement est imputé aux crédits ordinaires selon FIFO ; les soldes non réglés conservent leur ancienneté.
- Alignement de la matrice Finance sur cette priorité d'imputation.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.0 — 13 novembre 2026
- Enregistrement de Finance Q233 : reconnaître les montants déjà versés au titre d'un crédit invalidé comme une créance distincte sur la coopérative.
- Récupérer cette créance sur les prochains reversements, séparément de l'annulation du solde de crédit non imputé (Q232).
- Alignement de la matrice Finance sur le suivi et la récupération traçable de cette créance.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.9 — 13 novembre 2026
- Enregistrement de Finance Q232 : en cas de contestation acceptée, annuler le solde restant du crédit invalide par une écriture corrective distincte.
- Préserver le crédit initial et toutes les imputations antérieures ; définir séparément le traitement des montants déjà imputés sur des reversements exécutés.
- Alignement de la matrice Finance sur l'annulation traçable du seul solde restant du crédit contesté.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.8 — 13 novembre 2026
- Enregistrement de Finance Q231 : après rejet d'une contestation, réactiver le crédit valide en conservant son rang FIFO d'origine.
- La suspension ne modifie pas l'ancienneté du crédit ; aucune réimputation rétroactive des reversements déjà exécutés.
- Alignement de la matrice Finance sur la réactivation du crédit contesté et la conservation de son rang FIFO.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.7 — 13 novembre 2026
- Enregistrement de Finance Q230 : suspendre uniquement le crédit faisant l'objet d'une contestation ; les autres crédits non contestés restent imputables selon FIFO.
- La suspension ne supprime ni n'annule le crédit ; les modalités de résolution, la conservation du rang FIFO après réactivation et les habilitations restent à préciser.
- Alignement de la matrice Finance avec l'isolation des contestations au niveau du crédit concerné.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.6 — 13 novembre 2026
- Enregistrement de Finance Q229 : les crédits coopérative multiples sont imputés selon FIFO, du plus ancien au plus récent ; un crédit ancien est apuré avant de commencer l'imputation du suivant.
- Alignement de la matrice Finance avec le report automatique des soldes défini en Q228 ; les crédits plus récents restent en attente tant que le plus ancien n'est pas épuisé.
- Horodatage de référence, départage des créations simultanées, concurrence et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.5 — 13 novembre 2026
- Enregistrement de Finance Q228 : un crédit coopérative supérieur au prochain reversement est imputé automatiquement sur plusieurs reversements successifs ; le solde restant est reporté jusqu'à épuisement.
- Alignement de la matrice Finance : imputation plafonnée au montant du reversement et au solde du crédit, historique de chaque application conservé, sans validation manuelle du report.
- Priorité entre crédits multiples, reversements nuls/annulés, concurrence, rapprochement et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.4 — 13 novembre 2026
- Enregistrement de Finance Q227 : correction imputée au reversement si celui-ci n'est pas encore exécuté ; après exécution, création d'un crédit coopérative imputable sur le prochain reversement.
- Alignement de la matrice Finance sur le suivi distinct du crédit, son rattachement aux écritures d'origine et la prévention des doubles régularisations.
- Règles d'imputation partielle/totale, report de solde, concurrence, rapprochement et idempotence à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.3 — 13 novembre 2026
- Enregistrement de Finance Q226 : correction de commission comptabilisée immédiatement après confirmation définitive du remboursement par Sales, sans attendre rapprochement périodique ni réception effective des fonds par le client.
- Alignement de la matrice Finance sur le déclenchement immédiat, sous réserve de la confirmation définitive Sales et des contrôles de cumul, plafonnement, traçabilité et idempotence déjà définis.
- Contrat de transmission Sales → Finance, reprise des événements et idempotence du déclenchement restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.2 — 13 novembre 2026
- Enregistrement de Finance Q225 : les remboursements partiels successifs sont traités par recalcul cumulatif de la commission à annuler ; seule la différence avec les corrections déjà comptabilisées est enregistrée.
- Alignement de la matrice Finance : correction distincte par remboursement, lien avec la commission initiale, plafond au montant de commission initial et prévention du double comptage.
- Précision/arrondi au centime, concurrence et idempotence restent à définir techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

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
