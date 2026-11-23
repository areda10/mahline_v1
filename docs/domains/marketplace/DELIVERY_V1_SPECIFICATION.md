# MAHLINE V1 — Spécification fonctionnelle et technique Delivery

- **Domaine :** Delivery
- **Version :** 0.1 — consolidation initiale des décisions existantes
- **Date :** 20 novembre 2026
- **Branche :** feature/Marketplace
- **Statut :** périmètre autonome confirmé ; plusieurs règles V1 validées ; spécification partielle, non gelée ; aucun code Delivery implémenté.
- **Références métier :** docs/roadmap/FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md (FR-005 à FR-009, FR-014), docs/domains/marketplace/ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md.
- **Références architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

> Cette spécification consolide les décisions déjà consignées. Les points ouverts restent explicitement bloquants ; aucune règle manquante n'est inventée pour autoriser une migration ou une transition métier.

## 1. Ownership et frontières

Delivery est un domaine autonome du monolithe modulaire. Il possède exclusivement :
- les sociétés/partenaires de livraison dans leur représentation opérationnelle Delivery ;
- les contrats opérationnels de service et grilles de tarifs de trajet ;
- les devis logistiques et leur contexte de calcul ;
- les missions, affectations transporteurs, expéditions et colis ;
- le suivi logistique, événements de transport, preuves de remise et de livraison ;
- les incidents logistiques, retours physiques, anomalies et dossiers de litige de transport.

Delivery ne possède pas :
- les commandes, sous-commandes, lignes et statuts commerciaux (Orders) ;
- les stocks, réservations, mouvements et décisions de remise en stock (Inventory) ;
- le panier et l'orchestration précommande (Sales) ;
- les factures, paiements, écritures, commissions et reversements marketplace (Finance) ;
- les comptes, sessions, rôles et permissions générales (Identity) ;
- les fichiers et traitements médias (Media) ;
- les notifications et journaux d'envoi (Communication).

**Frontière critique :** Delivery publie des faits logistiques. Orders reste seul responsable de traduire ces faits en transitions du cycle de vie commercial de la commande. Finance consomme les faits de collecte/livraison et les références COD sans modifier le workflow logistique ou commercial.

## 2. Périmètre V1 et règles validées

### 2.1 Relation coopérative / transporteurs
- Une coopérative peut travailler simultanément avec plusieurs sociétés de livraison.
- Chaque commande expédiée est associée à la société qui accepte ou reçoit la mission.
- Les contrats, tarifs, transferts et historiques sont suivis par société.
- La sélection des sociétés éligibles, les zones desservies et les critères de qualification doivent être consultables côté serveur ; la définition complète de l'éligibilité reste à valider.

### 2.2 Tarification par trajet — FR-005
- Les tarifs sont établis selon le trajet entre la ville de départ et la ville de destination.
- La grille tarifaire doit être fournie par la société de livraison et enregistrée dans Delivery.
- Exemples communiqués pendant la découverte : Casablanca–Casablanca 30 DH, Casablanca–Rabat 40 DH, Casablanca–Tanger 70 DH. **Ces valeurs sont des exemples et ne constituent pas une grille réelle à intégrer.**
- La grille tarifaire réelle est attendue ; aucune valeur ou route manquante ne doit être inventée.
- Devise V1 : MAD, précision monétaire à deux décimales selon les règles transversales validées.
- Les dates d'effet et versions de tarifs doivent être conservées pour que le devis et le snapshot d'une commande restent explicables.

### 2.3 Panier multi-coopératives — FR-006
- Les frais de livraison sont calculés séparément pour chaque expédition : ville de départ de la coopérative → ville de destination du client.
- Le total des frais de livraison du panier est la somme des frais des expéditions concernées.
- Chaque sous-commande coopérative porte son propre périmètre d'expédition et le montant de livraison associé.
- La règle est celle du premier release et peut évoluer ultérieurement ; elle ne doit pas être codée comme une contrainte architecturale immuable.
- La manière de recalculer les frais lorsque l'une des sous-commandes est refusée/annulée ou lorsque toutes le sont reste à préciser.

### 2.4 Parcours de mission et attribution transporteur — FR-008 / règles livraison
1. La coopérative prépare sa sous-commande.
2. Lorsque la commande est prête, la coopérative la marque prête pour expédition.
3. MAHLINE notifie les sociétés de livraison concernées/éligibles.
4. Les sociétés éligibles disposent de **12 heures** pour accepter librement la mission.
5. La première acceptation valide obtient l'attribution exclusive ; les autres transporteurs ne peuvent plus accepter cette mission.
6. À l'expiration des 12 heures, si aucune acceptation valide n'a déjà obtenu la mission, MAHLINE identifie le ou les transporteurs éligibles proposant le tarif minimal. Si un seul transporteur détient ce tarif, la mission lui est attribuée automatiquement. Si plusieurs transporteurs sont exactement ex æquo au tarif minimal, seuls ces transporteurs restent candidats et **le premier qui enregistre une acceptation valide remporte la mission**. L'attribution est exclusive et atomique : une seule acceptation concurrente peut réussir ; les autres sont rejetées.
7. Le transporteur attribué récupère physiquement la commande auprès de la coopérative et la remet au client.
8. Le client règle le montant total au transporteur selon le mode COD V1.

Les notifications, acceptations, refus, attribution, motif et horodatages sont historisés. En cas d'égalité du tarif minimal après les 12 heures, le départage est effectué par la première acceptation valide parmi les transporteurs ex æquo. L'ordre d'arrivée est déterminé côté serveur et protégé contre les courses concurrentes par verrouillage/contrainte d'unicité dans la transaction d'attribution.

### 2.5 Accès transporteur au premier release
- Le transporteur dispose d'un accès dédié à MAHLINE pour consulter et mettre à jour les informations des seules expéditions qui lui sont attribuées.
- Le transporteur ne doit pas voir les autres commandes, clients, coopératives ou expéditions qui ne sont pas nécessaires à ses missions.
- La transmission automatique par API avec les systèmes transporteurs est une évolution post-release, lorsque le transporteur le permet.
- Les mises à jour transporteur sont historisées et contrôlées avant de publier un fait logistique.
- Le choix technique entre compte Identity avec rôle dédié, identité partenaire séparée ou mécanisme d'accès externe fédéré n'est pas encore validé ; ne pas ajouter un rôle ni une table d'identité sans décision.

### 2.6 Preuves photographiques — FR-009
- Avant la remise initiale : la coopérative prend des photos du colis avant remise au transporteur.
- À la prise en charge : le transporteur prend ses propres photos du colis.
- Pendant un retour : le transporteur photographie le colis au moment de la prise en charge du retour.
- À la réception du retour : la coopérative prend ses propres photos et effectue le contrôle.
- Le nombre de photos dépend de l'étape et de l'état du colis ; un minimum d'une photo est obligatoire pour chaque étape concernée.
- MAHLINE bloque le passage à l'étape suivante tant que le nombre requis pour l'étape n'est pas atteint.
- La matrice précise étape × acteur × nombre de photos est encore à définir.
- Les fichiers sont stockés et contrôlés par Media via son contrat public ; Delivery conserve le lien métier, l'étape, l'auteur et les métadonnées nécessaires. Aucun stockage privé parallèle ne doit être créé dans Delivery.

### 2.7 Refus à la livraison, retour et responsabilité des frais — FR-009
- Pour le COD, le client peut annuler tant que la coopérative n'a pas confirmé la sous-commande.
- Après confirmation de la coopérative, cette fenêtre d'annulation COD est terminée.
- Après remise physique du colis au transporteur, la commande ne peut plus être annulée par le parcours d'annulation ordinaire.
- Le client peut refuser le colis au moment de la livraison ; ce refus déclenche une procédure de retour.
- La responsabilité des frais de transport retour dépend du motif :
  - erreur imputable à la coopérative : frais retour à la charge de la coopérative ;
  - produit endommagé pendant le transport : frais retour à la charge du transporteur ;
  - changement d'avis du client alors que le produit est conforme et intact : frais retour à la charge du client.
- Si le produit retourné est endommagé/invendable :
  - dommage pendant le transport retour : responsabilité transporteur ;
  - défaut préexistant avant expédition : responsabilité coopérative ;
  - détérioration causée par le client après réception : responsabilité client.
- Les règles ci-dessus déterminent la responsabilité de principe, mais le mode de calcul, de facturation et de recouvrement des frais reste à définir par Finance/contrats.

### 2.8 Contrôle de retour et anomalies
- À réception du colis retourné, la coopérative effectue un contrôle complet avec photos, vérification des quantités, de l'état et de la conformité du produit.
- Le contrôle et la décision sont effectués **produit par produit**.
- Les produits conformes sont acceptés ; un produit conforme ne doit pas être bloqué uniquement parce qu'un autre produit du colis est non conforme.
- Chaque produit non conforme déclenche une anomalie traçable liée à la commande, au colis et au produit concerné.
- L'analyse détermine si le dommage existait avant expédition, s'est produit pendant le transport ou est imputable au client après réception.
- La coopérative et le transporteur fournissent les preuves relevant de leurs étapes ; MAHLINE centralise l'enquête et applique la décision.
- Un désaccord sur la responsabilité place le dossier en litige ; une intervention humaine est obligatoire avant toute décision finale.

### 2.9 Gouvernance des litiges et contestations
- Le super_admin responsable de la coopérative concernée est l'autorité normale de décision.
- Un super_admin non affecté ne peut ni consulter ni trancher le dossier.
- Si aucun super_admin n'est affecté à la coopérative, le litige reste en attente jusqu'à désignation.
- Si le responsable est impliqué ou conteste la décision, le super_admin_root reprend prioritairement le dossier ; s'il ne peut intervenir, un autre super_admin affecté et non impliqué peut être désigné.
- Le client peut déposer une contestation et des preuves dans les **48 heures suivant la notification** de la décision.
- Une seule contestation client est autorisée pour un même litige.
- Le client peut ajouter des preuves jusqu'à l'ouverture effective du dossier par le responsable désigné ; cette ouverture marque le début officiel du réexamen et ferme les ajouts.
- Le super_admin_root peut annuler une ouverture accidentelle, avec justification et audit. Cette annulation peut être répétée sans limite prédéfinie ; chaque annulation réactive l'ajout de preuves jusqu'à la prochaine ouverture. Aucune notification dédiée n'est envoyée lors de cette réactivation.
- Le réviseur peut confirmer, modifier ou annuler la décision initiale selon les preuves et circonstances.
- La décision après réexamen est définitive ; aucune seconde contestation n'est possible.
- Le super_admin_root décide au cas par cas si l'exécution de la décision initiale doit être suspendue pendant le réexamen.
- La décision définitive est notifiée au client, à la coopérative, au transporteur impliqué et au super_admin responsable, dans leur espace MAHLINE et par e-mail ; le contenu indique le résultat et un résumé du motif, sans exposer automatiquement le dossier complet.
- L'échec d'e-mail ne bloque pas la notification in-app. Les tentatives automatiques suivent 5 min, 15 min, 30 min, 1 h puis toutes les 2 h, jusqu'à 10 tentatives. Après 10 échecs, l'envoi automatique s'arrête et le super_admin_root est alerté. Il peut relancer, corriger l'adresse/le destinataire, clôturer après vérification ou faire un e-mail de test. Le test n'est considéré réussi que si la réception effective en boîte est confirmée ; sinon, vérification manuelle root avant reprise.
- L'historique complet de ces incidents de notification est conservé ; sa consultation et son export complet sont réservés au super_admin_root et journalisés.

## 3. Modèle technique candidat — non gelé

Les noms et champs seront alignés sur les conventions de modèles ULID, BaseModel, traits d'audit, soft deletes et migrations du dépôt avant création :

- Carrier : partenaire transporteur, état opérationnel, coordonnées et références logiques vers les comptes d'accès si validé.
- CarrierCooperativeAgreement : relation d'éligibilité/accord coopérative–transporteur, état et période.
- CarrierRateCard / CarrierRouteRate : version de grille, ville origine, ville destination, prix MAD, dates d'effet.
- DeliveryQuote / DeliveryQuoteLine : périmètre d'expédition, route, transporteur/grille, prix, devise, validité, empreinte et référence de commande après conversion.
- DeliveryMission : sous-commande cible, état logistique, fenêtre d'acceptation, méthode d'attribution et transporteur attribué.
- CarrierOffer / CarrierAcceptance : offres/acceptations horodatées et unicité de la première acceptation.
- Shipment / ShipmentParcel : expédition par sous-commande et colis éventuels ; la granularité définitive colis/sous-commande reste à valider.
- ShipmentEvent : journal append-only des faits de suivi.
- DeliveryEvidence : références Media, étape, acteur, horodatage et contrôle de complétude.
- DeliveryIncident, ReturnCase, ReturnItemInspection, ProductAnomaly, DeliveryDispute, DisputeAppeal, DisputeEvidence : incidents, retours physiques, contrôles produit, litiges et contestations.

Les références Orders, Inventory, Finance, Cooperatives, Localization et Media sont des IDs logiques/DTOs. Aucune relation Eloquent directe vers leurs modèles privés.

## 4. Contrats publics et événements attendus

### 4.1 Contrats synchrones fournisseurs consommés par Delivery
- Orders : obtenir un snapshot minimal et immuable de la sous-commande prête, des lignes et du destinataire ; valider qu'elle est dans l'état commercial permettant la préparation logistique.
- Cooperatives : statut et informations opérationnelles de la coopérative propriétaire ; adresse d'expédition via Localization.
- Localization : résolution ville/zone d'origine et destination ; aucun parsing libre de l'adresse dans Delivery.
- Media : créer/lier et vérifier les pièces privées ; les contrôles antivirus/type/taille sont assurés par Media.
- Identity : contexte d'acteur et permissions générales pour l'accès transporteur, coopérative et back-office.

### 4.2 Contrats exposés par Delivery
- DeliveryQuoteProvider : devis pour une expédition/route donnée, avec montant MAD, version de tarif, contexte, validité et empreinte.
- CarrierEligibilityReader : sociétés de livraison éligibles pour une coopérative et une route.
- ShipmentAssignmentReader : attribution et statut d'affectation pour une sous-commande.
- DeliveryEvidenceReader : statut de complétude des preuves, sans exposer les fichiers bruts sans autorisation.
- ReturnInspectionOutcomeReader : résultat par produit d'un contrôle de retour pour orchestration Inventory/Orders.
- Les contrats définitifs sont versionnés selon ADR-017 ; aucune interface n'est créée avant validation de la forme exacte des DTO.

### 4.3 Événements publics émis par Delivery
Exemples de faits versionnés, minimisés et publiés après commit :
- delivery.quote_issued.v1, delivery.quote_expired.v1 ;
- delivery.mission_ready.v1, delivery.carrier_accepted.v1, delivery.carrier_assigned.v1 ;
- delivery.shipment_handed_over.v1, delivery.shipment_picked_up.v1, delivery.shipment_in_transit.v1, delivery.shipment_delivered.v1 ;
- delivery.delivery_refused.v1, delivery.return_started.v1, delivery.return_received.v1 ;
- delivery.product_anomaly_opened.v1, delivery.dispute_opened.v1, delivery.dispute_decided.v1.

Ce sont des noms candidats, pas des événements existants. Les événements critiques doivent utiliser Outbox ; la consommation est idempotente. Orders reçoit les faits et décide lui-même de toute transition commerciale. Inventory consomme les faits explicitement retenus pour sortie/retour ; Finance consomme les faits de livraison et de collecte COD.

## 5. Intégrations et règles transactionnelles
- Sales demande un devis Delivery pour chaque expédition coopérative avant de présenter un total final incluant livraison. Sales ne calcule jamais les tarifs.
- Orders crée une sous-commande par coopérative et conserve son snapshot de montant/frais de livraison ; Delivery ne crée pas la commande.
- La coopérative signale la sous-commande prête ; Delivery ouvre la mission et les offres transporteurs.
- L'acceptation est atomique : une seule acceptation gagne, protégée par transaction/verrou/contrainte d'unicité. Les tentatives concurrentes perdantes reçoivent un refus métier.
- Le job d'attribution automatique après 12 h doit être idempotent et verrouiller la mission ; il ne peut pas écraser une acceptation déjà valide.
- Le fait exact de consommation de la réservation Inventory (confirmation, remise physique ou événement de départ) reste à arbitrer. Delivery ne modifie jamais directement Inventory.
- La réception d'un retour doit produire un résultat de contrôle par produit. Inventory reste seul autorisé à réintégrer une quantité acceptée ou à placer une quantité refusée en quarantaine.
- Finance reçoit des événements de livraison et d'encaissement COD ; Delivery ne tient pas le grand livre, ne calcule pas les commissions et ne réalise aucun reversement.
- Les appels aux transporteurs, e-mails et autres effets externes ne sont pas exécutés à l'intérieur d'une transaction DB ; ils passent par jobs/outbox et mécanismes de reprise.

## 6. Routes, middleware et Policies — cible à auditer au codage

Les noms ci-dessous sont des propositions, pas des routes déclarées :
- Espace coopérative : backoffice.cooperatives.delivery.* — préparation logistique, consultation de mission, dépôt des preuves coopérative et inspection de retour.
- Espace transporteur : carrier.delivery.* — missions proposées/attribuées, acceptation, collecte, événements, photos et preuve de livraison/retour.
- Espace client : delivery.tracking.* — suivi de ses propres expéditions, événements autorisés, refus à livraison et informations de retour.
- Espace root : root.backoffice.delivery.* — gouvernance globale, litiges, incidents et accès administratif selon permission.
- Espace super_admin : backoffice.delivery.* — litiges et incidents dans le périmètre des coopératives affectées.

Middleware commun :
- authentification et session active via Identity ;
- permission générale et rôle de l'espace concerné ;
- throttling sur acceptations, mises à jour de suivi, dépôts de preuves et contestations ;
- middleware carrier-specific seulement après décision sur le modèle de compte/role transporteur.

Policies possédées par Delivery :
- DeliveryMissionPolicy, ShipmentPolicy, DeliveryEvidencePolicy, ReturnCasePolicy, DeliveryDisputePolicy, CarrierPolicy.
- Chaque Policy vérifie la mission/expédition, la coopérative, l'affectation, le transporteur attribué, le client propriétaire et l'étape courante.
- Le transporteur n'accède qu'aux missions attribuées à sa société ; il ne peut pas choisir un carrier_id dans la requête.
- cooperative_admin ne gère que ses sous-commandes/retours ; super_admin ne traite que les coopératives affectées ; super_admin_root a la portée globale.
- Le traitement d'un litige respecte l'impartialité : un responsable impliqué ne peut pas instruire son propre dossier.
- Les accès aux preuves sont contrôlés par Delivery Policy puis Media ; connaître un identifiant Media n'accorde aucun droit.

## 7. Dépendances orientées

| Consommateur | Fournisseur | Surface attendue | Finalité |
|---|---|---|---|
| Sales | Delivery | DeliveryQuoteProvider | Devis par sous-commande/expédition |
| Delivery | Orders | ReadySubOrderReader + événements commandes | Snapshot et readiness |
| Delivery | Cooperatives | statut/identité opérationnelle | Éligibilité et préparation |
| Delivery | Localization | résolution d'adresse/ville | Tarification trajet et livraison |
| Delivery | Identity | contexte d'acteur/permission | Authentification et rôles |
| Delivery | Media | contrats médias privés | Photos/preuves |
| Orders | Delivery | événements publics | Mise à jour du cycle commercial par Orders |
| Inventory | Delivery | événements/contrats de sortie et retour | Consommer/libérer/réintégrer stock après arbitrage |
| Finance | Delivery | événements collecte COD/livraison/retour | Fonds, frais, responsabilité et rapprochement |
| Communication | Delivery | événements métier | Notifications aux destinataires |

Aucune dépendance Delivery → Finance n'est requise pour exécuter une expédition ; Finance consomme les faits de Delivery. Aucune dépendance circulaire Orders ↔ Delivery par appels de services privés n'est autorisée.

## 8. Sécurité et confidentialité
- Le transporteur reçoit le minimum d'informations nécessaires à la collecte et à la livraison.
- Les coordonnées client, adresse et contenu du colis ne sont visibles que pour les acteurs affectés à cette mission et pendant la période nécessaire.
- Les photos de preuve sont privées ; les autorisations de lecture sont évaluées par étape, acteur et mission.
- Les preuves de litige ne sont pas transmises automatiquement dans les notifications ; les résumés sont adaptés au rôle.
- Toutes les acceptations, attributions, transitions, consultations sensibles, dépôts de preuves, décisions et exports sont audités.
- Aucun jeton, mot de passe, secret ou donnée sensible non nécessaire ne doit apparaître dans les logs ou événements.

## 9. Tests obligatoires
- Grilles tarifaires versionnées, trajets absents, devise MAD, arrondis et snapshots.
- Somme des frais par expédition pour un panier multi-coopératives.
- Isolation entre missions, coopératives, transporteurs et clients.
- Première acceptation concurrente unique ; refus des acceptations ultérieures.
- Attribution automatique à 12 h, idempotence, course acceptation/job et égalité tarifaire (après décision de départage).
- Preuves obligatoires avant transitions, types/taille/antivirus via Media, accès interdit aux non-participants.
- Refus client, retour, frais selon responsabilité et contrôles produit par produit.
- Anomalies, litiges, conflit d'intérêts, absence de super_admin affecté, contestation 48 h, une contestation unique, fenêtre de dépôt des preuves et décision définitive.
- Échecs e-mail, 10 tentatives, temporisation, arrêt, alerte root et reprise manuelle.
- Contrats/événements Orders, Inventory, Finance et Communication ; absence de mutations directes dans les domaines fournisseurs.
- Routes, middleware, Policies, permissions, audit et tests d'architecture inter-domaines.

## 10. Décisions ouvertes bloquantes avant gel
1. Égalité de tarif après 12 h : critère de départage déterministe et équitable.
2. Éligibilité : critères de zone, capacité, disponibilité, contrat actif, horaires et suspension d'un transporteur.
3. Grilles réelles : import/saisie, approbation, date d'effet, version, tranches poids/volume éventuelles et cas de route absente.
4. Moment de calcul/confirmation du devis, durée de validité, évolution de tarif entre checkout et préparation, frais lors d'un refus/annulation partielle.
5. Modèle d'identité et cycle de vie des comptes transporteurs ; rôle et permissions exacts.
6. Granularité mission/expédition/colis : un colis ou plusieurs par sous-commande, regroupement et expéditions fractionnées.
7. Statuts logistiques exacts, transitions, acteurs, preuves exigées et gestion des événements hors ordre.
8. Échec de collecte, absence du client, livraison impossible, encaissement COD incomplet, perte du colis et nouvelle tentative.
9. Matrice photo étape × acteur × nombre minimum/maximum, formats et règles de conservation.
10. Application et calcul des frais de retour, justificatifs, recouvrement et effets financiers pour chaque motif.
11. Moment exact de consommation/libération de stock et articulation avec les réservations Inventory.
12. Résultat d'inspection produit par produit, délai de contrôle, critères d'acceptation et traitement des anomalies.
13. Effets opérationnels et financiers des décisions de litige, critères de suspension et délais de traitement.
14. FR-014 : périmètre de livraison nationale et internationale du premier release.
15. API transporteurs post-release : sécurité, idempotence, mapping des statuts et gestion des divergences.

## 11. Critères de gel et de clôture

Le domaine ne peut être déclaré gelé qu'après :
- arbitrage des points bloquants qui touchent le schéma, les transitions, les permissions ou les calculs ;
- validation des contrats publics et de leur version ;
- audit du code réel Orders, Inventory, Localization, Identity, Media et Communication ;
- migrations additives, modèles, services, routes, Policies, middleware, événements/jobs et tests ;
- tests MySQL transactionnels/concurrents, tests d'architecture, Pint et suite complète verte ;
- audit final des références, imports, routes, policies, middleware, permissions, événements et documentation.

Aucun code ou migration Delivery n'est créé par cette spécification.


### 2.3.1 Tarif initial provisoire — 40 DH par expédition coopérative (décision utilisateur du 23 novembre 2026)

**Statut : VALIDÉ pour le tarif initial V1 ; tarif futur configurable par société de livraison.**

- Le tarif actuellement appliqué est fixé à **40,00 MAD pour chaque expédition d'une coopérative vers le client**.
- L'unité de facturation est l'expédition / la sous-commande coopérative, et non la commande principale, l'article, la ligne ou la quantité.
- Tous les articles d'une même sous-commande coopérative expédiés ensemble vers le même client partagent un seul frais de 40,00 MAD.
- Une commande principale multi-coopératives additionne un frais par coopérative expéditrice : une coopérative = 40,00 MAD ; deux coopératives = 80,00 MAD ; trois coopératives = 120,00 MAD.
- Le total de livraison de la commande principale est la somme exacte des frais de livraison des sous-commandes qui la composent.
- Chaque sous-commande conserve son propre montant de livraison ; le total de la commande principale doit correspondre à la somme de ses sous-totaux produits et de ses frais de livraison.
- Le montant de 40,00 MAD est une valeur initiale provisoire et ne doit pas être codée comme une constante métier définitive.
- Lorsque les contrats et grilles des sociétés de livraison seront configurés, Delivery calculera le montant applicable à chaque expédition selon la société/les conditions tarifaires retenues et le trajet. Sales ne calcule ni ne choisit lui-même le tarif.
- Le tarif appliqué, sa devise, sa source (tarif initial ou grille/version de société de livraison), son contexte et son horodatage doivent être conservés dans le devis puis dans le snapshot immuable de la commande.
- Une modification future de la grille tarifaire ne doit pas modifier rétroactivement les devis acceptés ni les commandes déjà créées.
- Les exemples tarifaires de la section 2.2 (30/40/70 DH par trajet) restent des exemples de découverte et ne remplacent pas cette décision de tarif initial à 40,00 MAD par expédition.

**Exemples de calcul :**

| Expéditions vers le même client | Frais |
|---|---:|
| 1 coopérative, plusieurs articles | 40,00 MAD |
| 2 coopératives | 80,00 MAD |
| 3 coopératives | 120,00 MAD |

