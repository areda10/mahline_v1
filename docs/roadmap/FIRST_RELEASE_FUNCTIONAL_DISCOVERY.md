# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 0.27
- **Date d'ouverture :** 12 novembre 2026
- **Branche :** `feature/Identity`
- **Statut :** EN COURS — décisions fonctionnelles recueillies progressivement
- **Périmètre :** premier release de MAHLINE (SaaS de gestion coopérative + marketplace publique)

> Ce document est le registre de référence des questions, réponses et décisions fonctionnelles prises pendant le cadrage. Chaque réponse validée doit être ajoutée ici avant de poursuivre. Les points non encore posés ou non validés restent ouverts ; aucune règle ne doit être supposée.

## 1. Objectif du premier release

Construire une première version exploitable de MAHLINE qui combine :
- un SaaS permettant aux coopératives de gérer leur activité ;
- une marketplace publique permettant aux clients de découvrir les coopératives et leurs produits et de commander ;
- un parcours cohérent entre la marketplace et les espaces de gestion des coopératives.

La cohérence approfondie des frontières entre domaines et le renforcement global des événements transversaux sont planifiés dans une phase ultérieure dédiée, après le développement fonctionnel des domaines du premier release. Les règles indispensables de sécurité, d'autorisation, de propriété des données et d'intégrité restent obligatoires pendant chaque développement.

## 2. Décisions fonctionnelles validées

### FR-001 — Nature du premier release

- **Question :** Quel doit être le fonctionnement de MAHLINE au premier release ?
- **Réponse :** SaaS de gestion pour les coopératives + marketplace publique.
- **Statut :** VALIDÉ
- **Conséquence :**
  - Les coopératives disposent d'un espace de gestion.
  - Les visiteurs peuvent découvrir les coopératives et leurs produits sur une marketplace publique.
  - Les commandes marketplace doivent être prises en charge dans l'espace de gestion des coopératives concernées.

### FR-002 — Authentification des clients marketplace

- **Question :** Comment les clients doivent-ils passer commande sur la marketplace ?
- **Choix proposés :** A. commande invitée ; B. compte obligatoire ; C. les deux.
- **Réponse :** B — création de compte ou connexion obligatoire avant de commander.
- **Statut :** VALIDÉ
- **Conséquence :**
  - Un client doit être authentifié pour finaliser une commande.
  - Le parcours client doit prévoir un espace personnel.
  - L'historique des commandes et le suivi doivent être accessibles au client authentifié.
  - Les coordonnées et adresses de livraison doivent être associées au compte client.
- **À préciser ultérieurement :** quelles informations sont obligatoires à l'inscription, vérification d'e-mail/téléphone, gestion de plusieurs adresses et parcours de récupération de compte.

### FR-003 — Panier multi-coopératives et découpage des commandes

- **Question :** Comment organiser les commandes contenant des produits de plusieurs coopératives ?
- **Choix proposés :** A. commande unique ; B. commandes séparées ; C. panier multi-coopératives avec paiement unique et commandes séparées par coopérative.
- **Réponse :** C — le client paie une seule fois ; MAHLINE crée une commande distincte par coopérative, avec suivi et facturation séparés.
- **Statut :** VALIDÉ
- **Conséquence :**
  - Le panier public peut contenir des produits de plusieurs coopératives.
  - Le parcours de paiement est unique pour le client.
  - Après validation, MAHLINE doit créer une commande par coopérative concernée.
  - Chaque coopérative traite sa propre commande et dispose de son propre suivi.
  - La facturation est séparée par coopérative.
  - Le client doit pouvoir consulter l'ensemble de son achat et suivre les sous-commandes par coopérative.
- **Points à cadrer dans les questions suivantes :** paiement et répartition des montants, frais de livraison par coopérative, échec partiel, annulation partielle, remboursement partiel, commissions MAHLINE, numérotation et documents remis au client.

### FR-004 — Moyens de paiement au premier release et évolution prévue

- **Question :** Quels moyens de paiement prévoir au premier release ?
- **Choix proposés :** A. paiement à la livraison uniquement ; B. paiement en ligne uniquement ; C. paiement à la livraison + paiement en ligne ; D. virement bancaire + paiement à la livraison + paiement en ligne.
- **Réponse :** A au premier release — paiement à la livraison uniquement. Après le premier release, intégrer l'option C — paiement à la livraison + paiement en ligne.
- **Statut :** VALIDÉ pour le périmètre initial et planifié pour l'évolution post-release.
- **Conséquence pour le premier release :**
  - Le client règle sa commande à la livraison.
  - Le paiement en ligne n'est pas nécessaire au lancement initial.
  - Le parcours de commande doit permettre de distinguer le montant dû à la livraison et le statut de règlement.
- **Évolution post-release :**
  - Ajouter le paiement en ligne tout en conservant le paiement à la livraison.
  - Le choix du prestataire de paiement, les remboursements, les paiements partiels/échoués et la répartition des fonds entre coopératives restent à spécifier avant cette évolution.

### FR-005 — Tarification des frais de livraison selon le trajet

- **Question :** Comment déterminer les frais de livraison pour un panier multi-coopératives ?
- **Choix proposés initialement :** A. tarif unique pour tout le panier ; B. tarif propre à chaque coopérative ; C. calcul selon destination, poids et volume ; D. tarif propre à chaque coopérative avec seuil éventuel de gratuité.
- **Réponse reçue :** Les sociétés de livraison appliquent des tarifs selon le trajet entre la ville de départ et la ville de destination. Exemples communiqués : Casablanca ↔ Casablanca : 30 DH ; Casablanca ↔ Rabat : 40 DH ; Casablanca ↔ Tanger : 70 DH. L'utilisateur recherchera et fournira ultérieurement la grille tarifaire réelle de la société de livraison.
- **Statut :** PRINCIPE DE TARIFICATION VALIDÉ ; grille tarifaire et règle d'application au panier multi-coopératives À COMPLÉTER.
- **Conséquences / contraintes :**
  - MAHLINE devra pouvoir enregistrer une grille de tarifs fournie par une société de livraison, avec au minimum une ville de départ, une ville de destination et un prix en DH.
  - Les montants cités sont des exemples communiqués, pas une grille exhaustive ni des tarifs définitifs à intégrer en dur.
  - La grille réelle sera fournie ultérieurement ; aucune valeur manquante ne doit être inventée.
  - Il reste à décider comment calculer le total lorsque plusieurs coopératives expédient depuis des villes différentes dans le cadre d'un panier unique.

### FR-006 — Calcul des frais pour un panier multi-coopératives

- **Question :** Comment calculer les frais de livraison lorsque les produits proviennent de plusieurs coopératives expédiant depuis des villes différentes ?
- **Réponse :** A — pour le premier release, calculer séparément les frais de chaque expédition (ville de départ de chaque coopérative vers la ville du client), puis additionner les frais pour obtenir le total de livraison du panier.
- **Évolution prévue :** Cette règle pourra changer ultérieurement ; elle ne doit pas être considérée comme une règle métier immuable.
- **Statut :** VALIDÉ pour le premier release.
- **Conséquences / contraintes :**
  - Chaque sous-commande coopérative porte les informations de son expédition et les frais correspondants.
  - Le total des frais de livraison du panier est la somme des frais de ses expéditions.
  - Le calcul doit rester configurable/évolutif afin de permettre une autre règle ou un autre mode de tarification sans reconstruire le parcours de commande.
  - La grille tarifaire réelle des transporteurs reste attendue ; les exemples de FR-005 ne constituent pas une grille définitive.

### FR-007 — Moment de réservation du stock selon le moyen de paiement

- **Question :** À quel moment réserver ou déduire le stock d'un produit commandé ?
- **Choix proposés :** A. à l'ajout au panier ; B. à la validation de la commande ; C. à la livraison ; D. à la confirmation de la commande par la coopérative.
- **Réponse :** règle différenciée selon le moyen de paiement :
  - **Paiement à la livraison (premier release) :** D — réserver le stock lorsque la coopérative confirme la commande.
  - **Paiement en ligne (évolution post-release) :** B — réserver le stock à la validation de la commande.
- **Statut :** VALIDÉ pour les deux parcours, avec paiement en ligne planifié après le premier release.
- **Conséquences / contraintes :**
  - Pour le paiement à la livraison, une commande non encore confirmée par la coopérative ne réserve pas immédiatement le stock.
  - À la confirmation de la commande par la coopérative, le stock correspondant doit être réservé de manière cohérente et protégée contre les réservations concurrentes.
  - Pour le futur paiement en ligne, la réservation intervient à la validation de la commande ; les règles liées à l'autorisation, à l'échec ou à l'expiration du paiement devront être cadrées avant l'intégration du paiement en ligne.
  - Le moment de réservation est une règle métier configurable/évolutive, pas une hypothèse à coder en dur.
- **Décision complémentaire — délai de confirmation COD :** D — aucune annulation automatique après un délai prédéfini ; la coopérative décide de confirmer ou non la commande.
- **À préciser dans le cadrage du traitement des commandes :** conséquences exactes d'un refus de la coopérative, gestion d'une commande laissée en attente sans limite automatique, durée de réservation après confirmation et libération du stock en cas d'annulation.

### FR-008 — Orchestration des étapes de commande

- **Question :** Qui pilote les étapes de traitement d'une commande après sa création ?
- **Choix proposés :** A. toutes les étapes par la coopérative ; B. préparation/expédition par la coopérative et suivi de livraison par MAHLINE ou le transporteur ; C. orchestration automatique par MAHLINE à partir des actions de la coopérative et des informations du transporteur ; D. autre fonctionnement.
- **Réponse :** C — MAHLINE orchestre automatiquement les étapes selon les actions de la coopérative et les informations de livraison transmises par le transporteur.
- **Statut :** PRINCIPES D'ORCHESTRATION ET DE REFUS PARTIEL VALIDÉS ; détail des statuts, transitions et acteurs À PRÉCISER. Transmission transporteur : accès dédié au premier release, API après le release.
- **Conséquences / contraintes :**
  - Distinguer les actions métier effectuées par la coopérative (par exemple confirmation, préparation et remise au transporteur) des événements logistiques (prise en charge, transit, livraison ou incident).
  - MAHLINE applique les transitions autorisées et conserve l'historique des changements de statut.
  - Les statuts ne doivent pas être modifiables librement sans contrôle : chaque transition doit être liée à un acteur ou à un événement autorisé.
  - Le mode de transmission des informations du transporteur (intégration technique ou saisie/mise à jour manuelle) reste à décider.
  - Les statuts détaillés, les transitions, les refus, les incidents, les retours et les règles de clôture seront cadrés avant le développement du traitement des commandes.
- **Décision complémentaire — transmission des informations du transporteur :** B au premier release — le transporteur dispose d'un accès dédié à MAHLINE pour mettre à jour les informations de ses livraisons ; C après le release — intégration automatique avec le système du transporteur via API lorsque cette possibilité est disponible.
- **Conséquences / contraintes complémentaires :**
  - Prévoir un espace ou un accès transporteur dédié, avec authentification et autorisations strictement limitées aux expéditions qui lui sont attribuées.
  - Un transporteur ne doit pas pouvoir consulter les commandes, clients ou données d'autres coopératives au-delà des informations nécessaires à ses propres livraisons.
  - Les mises à jour doivent être historisées et contrôlées avant de déclencher les transitions correspondantes.
  - L'intégration API est une évolution post-release ; sa disponibilité dépendra des capacités techniques de chaque transporteur.
- **Décision complémentaire — refus d'une sous-commande par une coopérative :** A — seule la sous-commande de la coopérative qui refuse est annulée ; les commandes des autres coopératives continuent leur traitement.
- **Conséquences / contraintes complémentaires :**
  - Le refus d'une coopérative ne doit pas annuler les sous-commandes des autres coopératives du même panier.
  - Le statut et l'historique de la sous-commande refusée doivent être distincts de ceux des autres sous-commandes.
  - Le traitement du panier multi-coopératives doit donc permettre une évolution indépendante de chaque sous-commande.
  - Les conséquences financières détaillées, les éventuels ajustements de frais, la notification du client et les modalités de remboursement (pour les futurs paiements en ligne) restent à cadrer dans FR-009 et les questions financières associées.


## 3. Questions ouvertes

Les questions seront ajoutées au fur et à mesure, dans l'ordre du parcours métier. Une question n'est considérée validée que lorsque la réponse de l'utilisateur est explicite.

| ID | Sujet | Statut |
|---|---|---|
| FR-004 | Moyens de paiement du premier release | VALIDÉ — paiement à la livraison ; paiement en ligne après le release |
| FR-005 | Frais de livraison selon la grille trajet-ville des transporteurs | PRINCIPE VALIDÉ — grille réelle attendue |
| FR-006 | Calcul des frais pour un panier multi-coopératives | VALIDÉ — somme des frais par expédition au premier release |
| FR-007 | Gestion des stocks et réservation selon le moyen de paiement | VALIDÉ — COD à la confirmation coopérative, sans annulation automatique par délai ; paiement en ligne à la validation (post-release) |
| FR-008 | Traitement et transitions de statut des commandes coopératives | PRINCIPES VALIDÉS — orchestration MAHLINE ; accès transporteur au premier release, API ensuite ; refus limité à la sous-commande concernée ; statuts à préciser |
| FR-009 | Annulation, retour et remboursement | PARTIELLEMENT VALIDÉ — fenêtres selon paiement ; annulation indépendante ; après remise au transporteur, pas d'annulation mais refus à la livraison possible ; frais de retour variables selon le motif ; responsabilité des frais définie pour erreur coopérative, dommage transport et changement d'avis ; photos obligatoires, minimum 1 par étape concernée ; au départ, photos coopérative + transporteur ; au retour, photos transporteur au départ du retour + coopérative à réception ; contrôle complet à réception ; produits conformes acceptés, anomalie par produit non conforme ; coopérative et transporteur fournissent les preuves, MAHLINE centralise l'enquête et applique la décision ; procédure de décision à définir |
| FR-010 | Factures, reçus et documents client/coopérative | À poser |
| FR-011 | Commissions et reversements aux coopératives | À poser |
| FR-012 | Inscription et espace personnel client | À poser |
| FR-013 | Publication et visibilité des coopératives/produits | À poser |
| FR-014 | Livraison nationale et internationale au premier release | À poser |
| FR-015 | Abonnements SaaS, plans, quotas et renouvellement | À poser |
| FR-016 | Notifications et communications indispensables au lancement | À poser |

### FR-009 — Annulation, retour et remboursement

- **Décision complémentaire — photos pendant le retour (Question 25) :** C — le transporteur prend des photos au moment de la prise en charge du colis retourné, puis la coopérative prend ses propres photos à la réception du colis retourné.
- **Conséquences / contraintes :**
  - Les photos du transporteur documentent l'état du colis au départ du trajet retour.
  - Les photos de la coopérative documentent l'état du colis à son arrivée et permettent le contrôle du produit.
  - Ces deux séries constituent des preuves distinctes ; le minimum d'une photo par étape concernée s'applique, avec un nombre supérieur si l'étape ou l'état du colis le nécessite.
  - MAHLINE bloque la validation de l'étape suivante tant que les photos requises ne sont pas déposées.
  - Les angles de prise de vue, la quantité exacte selon les situations, l'horodatage et la conservation restent à définir.

- **Décision complémentaire — contrôle à réception du colis retourné (Question 26) :** D — la coopérative effectue un contrôle complet avec photos, vérification de la quantité, de l'état et de la conformité du produit.
- **Conséquences / contraintes :**
  - Le contrôle à réception ne se limite pas à l'apparence extérieure du colis : il porte aussi sur le contenu et le produit.
  - La coopérative documente le contrôle par des photos.
  - La quantité reçue, l'état du produit et sa conformité à la commande doivent être vérifiés et consignés.
  - Le résultat du contrôle doit permettre de décider si le produit peut être remis en stock, sans remise en stock automatique.
  - Les critères détaillés de conformité, les anomalies possibles, les preuves attendues par type d'anomalie et les suites opérationnelles restent à définir.

- **Décision complémentaire — traitement des produits conformes et non conformes à réception (Question 27) :** la coopérative accepte les produits conformes. Pour chaque produit non conforme, elle ouvre/enregistre une anomalie afin de déterminer à quel moment le colis ou le produit a été endommagé : avant l'expédition ou après l'expédition.
- **Conséquences / contraintes :**
  - Le contrôle et la décision sont effectués produit par produit ; un produit conforme ne doit pas être bloqué uniquement parce qu'un autre produit du même colis présente une anomalie.
  - Chaque produit non conforme doit faire l'objet d'une anomalie traçable, liée à la commande, au colis et au produit concerné.
  - L'analyse doit chercher à établir si le dommage existait avant l'expédition ou s'il est survenu après l'expédition, en s'appuyant notamment sur les preuves photographiques prises aux différentes étapes.
  - La procédure d'enquête, les acteurs autorisés à contribuer, les délais, les critères de décision, la responsabilité finale et les conséquences sur le stock et le remboursement restent à préciser.

- **Décision complémentaire — acteurs de l'enquête sur une anomalie (Question 28) :** D — la coopérative, le transporteur et MAHLINE participent au traitement de l'anomalie. La coopérative et le transporteur fournissent leurs preuves ; MAHLINE centralise l'enquête et applique la décision selon les règles établies.
- **Conséquences / contraintes :**
  - La coopérative et le transporteur doivent pouvoir transmettre les éléments de preuve relevant de leur intervention et des étapes qu'ils ont réalisées.
  - MAHLINE centralise les preuves, les événements et l'historique de l'anomalie afin de permettre une analyse traçable.
  - La décision doit s'appuyer sur les règles de responsabilité déjà cadrées : défaut antérieur à l'expédition, dommage pendant le transport ou dommage imputable au client après réception.
  - Les règles de décision, les rôles exacts de validation, la gestion des désaccords, les délais d'enquête et les voies de contestation restent à définir.

- **Décision complémentaire — photos au départ du colis (Question 24) :** C — la coopérative et le transporteur prennent chacun des photos au départ :
  - la coopérative photographie le colis avant sa remise au transporteur ;
  - le transporteur photographie le colis au moment de sa prise en charge.
- **Conséquences / contraintes :**
  - Les deux séries de photos constituent des preuves distinctes de l'état du colis au départ.
  - Le nombre de photos de chaque acteur reste variable selon l'étape et l'état du colis, avec un minimum d'une photo par étape concernée.
  - Les règles détaillées de cadrage, d'horodatage, de conservation et de contestation restent à définir.


- **Question :** Jusqu'à quel moment le client peut-il demander l'annulation d'une sous-commande ?
- **Réponse :** règle différenciée selon le moyen de paiement :
  - **Paiement à la livraison (COD) :** A — le client peut annuler tant que la coopérative n'a pas confirmé la commande.
  - **Paiement en ligne (évolution post-release) :** le client peut demander l'annulation pendant la phase de préparation de la commande.
- **Statut :** PARTIELLEMENT VALIDÉ — fenêtres d'annulation, indépendance des sous-commandes, principe de refus du colis après remise au transporteur et principe de responsabilité variable des frais de retour définis ; répartition par motif et conséquences financières à préciser.
- **Conséquences / contraintes :**
  - Pour le COD, après confirmation par la coopérative, la fenêtre d'annulation client définie ici est terminée.
  - Pour le paiement en ligne, l'annulation client reste possible pendant la préparation ; il faudra définir précisément le point de fin de cette phase et le traitement d'une demande déjà en cours lorsque la commande change d'étape.
  - Les règles de remboursement, de remboursement partiel pour un panier multi-coopératives, de frais de livraison, de commissions, de libération du stock et de notification restent à cadrer.
  - Après remise du colis au transporteur, l'annulation de la commande n'est plus possible.
  - Le client peut toutefois refuser le colis au moment de la livraison ; ce refus déclenche une procédure de retour.
  - **Décision complémentaire — responsabilité des frais de transport retour :** D — elle dépend du motif du refus du colis (par exemple erreur de la coopérative, produit endommagé ou changement d'avis du client). Le principe de responsabilité variable est validé, mais la pour une erreur imputable à la coopérative, celle-ci prend en charge les frais de transport retour. Lorsque le produit est endommagé pendant le transport, le transporteur prend en charge les frais de retour. En cas de simple changement d'avis du client, lorsque le produit est conforme et intact, le client prend en charge les frais de retour.
  - À réception du colis retourné, la coopérative contrôle le produit et décide si celui-ci peut être remis en stock. La procédure détaillée de contrôle, les critères d'acceptation/refus et la traçabilité du résultat restent à définir. La preuve de l'état du produit repose sur des photos prises par la coopérative et par le transporteur aux étapes du départ, du retour et de la réception ; les modalités détaillées restent à préciser. **Décision complémentaire — nombre de photos :** D — le nombre de photos dépend de l'étape et de l'état du colis, avec un minimum obligatoire d'une photo pour chaque étape concernée. Certaines étapes ou situations pourront exiger plusieurs photos. MAHLINE bloque le passage à l'étape suivante tant que le nombre de photos requis pour l'étape n'est pas atteint. La matrice précise étape × acteur × nombre de photos reste à définir. Si le produit retourné est endommagé ou invendable, la responsabilité de la perte dépend de la cause du dommage : dommage causé pendant le transport retour → transporteur ; défaut préexistant avant expédition → coopérative ; détérioration causée par le client après réception → client. Les modalités de preuve et les autres cas éventuels restent à préciser.
- **Décision complémentaire — effet de l'annulation dans un panier multi-coopératives :** A — seule la sous-commande visée par l'annulation du client est annulée ; les sous-commandes des autres coopératives continuent normalement.
- **Conséquences / contraintes complémentaires :**
  - L'annulation est indépendante pour chaque sous-commande, même si elles proviennent d'un panier initial unique.
  - Le total global du panier/achat et les frais associés devront refléter les sous-commandes effectivement maintenues ; la règle précise de recalcul des frais de livraison reste à définir.
  - Les remboursements éventuels (paiement en ligne post-release), les commissions et les notifications liées à une annulation partielle restent à cadrer.

Cette liste est indicative et sera complétée selon les réponses et les parcours identifiés.

## 4. Méthode de cadrage et de développement

Pour chaque domaine nécessaire au premier release :

1. Poser les questions fonctionnelles une par une.
2. Enregistrer chaque question, les choix proposés, la réponse exacte et ses conséquences dans ce document.
3. Distinguer les décisions validées, les questions ouvertes et les hypothèses interdites.
4. Présenter une synthèse du périmètre du domaine et obtenir la validation avant le développement.
5. Auditer les fichiers réels et leurs dépendances sur la branche de travail.
6. Définir les contrats techniques et les tests nécessaires.
7. Développer progressivement, puis exécuter les tests ciblés et de non-régression.
8. Mettre à jour ce registre et la feuille de route globale après validation.

## 5. Phasage convenu

- **Phase 1 — Premier release :** cadrage puis développement des fonctionnalités et domaines nécessaires au SaaS coopératif et à la marketplace publique.
- **Phase 2 — Consolidation architecturale :** audit et harmonisation approfondis des frontières entre domaines, responsabilités, contrats et dépendances.
- **Phase 3 — Renforcement transversal :** cartographie et renforcement des événements métier, de leur fiabilité, de leur traçabilité et de leur intégration entre domaines.
- **Phase 4 — Préparation lancement :** intégration bout en bout, validation globale, sécurité, staging et hébergement.

Le phasage ne permet jamais de reporter les contrôles essentiels de sécurité, d'isolation multi-coopératives, d'autorisation, de cohérence transactionnelle ou de protection des données nécessaires au fonctionnement correct d'une fonctionnalité.

## 6. Historique des mises à jour

### Version 0.27 — 12 novembre 2026
- Complément de FR-009 — Question 28 : la coopérative et le transporteur fournissent les preuves relatives à leurs étapes ; MAHLINE centralise les preuves et l'historique de l'anomalie, mène l'analyse et applique la décision selon les règles établies. Rôles de validation, désaccords, délais et contestation restent à définir.

### Version 0.26 — 12 novembre 2026
- Complément de FR-009 — Question 27 : la coopérative accepte les produits conformes et ouvre une anomalie pour chaque produit non conforme afin de rechercher si le dommage est survenu avant ou après l'expédition. Traitement produit par produit ; procédure d'enquête, acteurs, délais, critères de décision et conséquences restent à cadrer.

### Version 0.25 — 12 novembre 2026
- Complément de FR-009 — Question 26 : à la réception d'un colis retourné, la coopérative effectue un contrôle complet avec photos, vérification de la quantité, de l'état et de la conformité du produit. Le contrôle documente la décision de remise en stock, qui reste non automatique. Les critères détaillés et les suites en cas d'anomalie restent à cadrer.

### Version 0.24 — 12 novembre 2026
- Complément de FR-009 — Question 25 : pendant le retour, le transporteur prend des photos au moment de la prise en charge du colis retourné, puis la coopérative prend ses propres photos à la réception. Les séries documentent respectivement l'état au départ du trajet retour et à l'arrivée ; minimum d'une photo par étape concernée, davantage selon la situation. Détails de prise de vue et conservation à définir.

### Version 0.23 — 12 novembre 2026
- Complément de FR-009 — Question 24 : au départ du colis, la coopérative prend des photos avant la remise et le transporteur en prend au moment de la prise en charge. Les deux séries constituent des preuves distinctes de l'état du colis. Les détails techniques et les nombres précis restent à cadrer.

### Version 0.22 — 12 novembre 2026
- Complément de FR-009 — Question 23 : le nombre de photos obligatoires varie selon l'étape et l'état du colis, avec un minimum d'une photo pour chaque étape concernée. Certaines situations pourront exiger plusieurs photos. Le blocage de transition reste appliqué jusqu'à ce que le nombre requis soit atteint ; la matrice détaillée par étape et par acteur reste à définir.

### Version 0.21 — 12 novembre 2026
- Précision de FR-009 : les photos obligatoires conditionnent la validation de chaque étape concernée ; MAHLINE bloque le passage à l'étape suivante tant que les preuves photographiques requises ne sont pas déposées.

### Version 0.20 — 12 novembre 2026
- Précision de FR-009 : l'état du produit doit être documenté par des photos prises par la coopérative et par le transporteur aux étapes du départ, du retour et de la réception. Les photos sont obligatoires pour valider chaque étape concernée : MAHLINE bloque le passage à l'étape suivante tant que les photos requises ne sont pas déposées. La répartition précise des prises de vue, leur nombre minimal, les formats, l'horodatage et la conservation restent à cadrer.

### Version 0.19 — 12 novembre 2026
- Précision de FR-009 : attribution de la perte selon la cause constatée : dommage pendant le transport retour à la charge du transporteur ; défaut déjà présent avant l'expédition à la charge de la coopérative ; détérioration causée par le client après réception à la charge du client. La preuve de l'état du produit repose sur des photos prises par la coopérative et par le transporteur aux étapes définies : départ, retour et réception. La répartition exacte des prises de vue entre les acteurs à chaque étape, le nombre minimal de photos, leur horodatage et leur conservation restent à préciser.

### Version 0.18 — 12 novembre 2026
- Précision de FR-009 : si le contrôle de la coopérative révèle qu'un produit retourné est endommagé ou invendable, la responsabilité de la perte dépend de la cause du dommage. Les cas concrets et leur attribution restent à définir.

### Version 0.17 — 12 novembre 2026
- Précision de FR-009 : à réception d'un colis retourné, la coopérative contrôle le produit et décide de sa remise en stock ; aucune remise en stock automatique n'est prévue.

### Version 0.16 — 12 novembre 2026
- Précision de FR-009 : lorsque le produit est conforme et intact et que le client refuse le colis par simple changement d'avis, le client prend en charge les frais de transport retour.

### Version 0.15 — 12 novembre 2026
- Précision de FR-009 : lorsque le produit est endommagé pendant le transport, le transporteur prend en charge les frais de transport retour.

### Version 0.14 — 12 novembre 2026
- Précision de FR-009 : lorsque le refus du colis est causé par une erreur de la coopérative (mauvais produit, quantité incorrecte ou produit manquant), la coopérative prend en charge les frais de transport retour. La responsabilité pour les autres motifs reste à préciser.

### Version 0.13 — 12 novembre 2026
- Complément de FR-009 : les frais de transport retour dépendent du motif du refus (ex. erreur de la coopérative, produit endommagé, changement d'avis du client). Le principe est validé ; la responsabilité exacte pour chaque motif reste à préciser.

### Version 0.12 — 12 novembre 2026
- Complément de FR-009 : après remise du colis au transporteur, l'annulation n'est plus possible ; le client peut refuser le colis à la livraison, ce qui déclenche une procédure de retour. Les étapes opérationnelles, frais et conséquences financières du retour restent à cadrer.

### Version 0.11 — 12 novembre 2026
- Complément de FR-009 : dans un panier multi-coopératives, l'annulation demandée par le client ne concerne que la sous-commande sélectionnée ; les autres sous-commandes continuent normalement. Le recalcul des frais, les remboursements et les notifications restent à cadrer.

### Version 0.10 — 12 novembre 2026
- Enregistrement partiel de FR-009 : en COD, le client peut annuler jusqu'à la confirmation par la coopérative ; en paiement en ligne (post-release), il peut demander l'annulation pendant la préparation. Retours, remboursements et limites exactes de la phase de préparation restent à préciser.

### Version 0.9 — 12 novembre 2026
- Complément de FR-008 : en cas de refus d'une coopérative, seule sa sous-commande est annulée ; les sous-commandes des autres coopératives continuent indépendamment. Les conséquences financières et notifications restent à cadrer.

### Version 0.8 — 12 novembre 2026
- Complément de FR-008 : accès dédié du transporteur à MAHLINE pour mettre à jour ses livraisons au premier release ; intégration API après le release. Les accès sont limités aux expéditions attribuées au transporteur.

### Version 0.7 — 12 novembre 2026
- Enregistrement de FR-008 : MAHLINE orchestre les étapes de commande à partir des actions de la coopérative et des informations du transporteur. Les statuts, transitions, acteurs et modes de transmission restent à détailler.

### Version 0.6 — 12 novembre 2026
- Complément de FR-007 : pour les commandes avec paiement à la livraison, aucune annulation automatique n'est déclenchée par l'expiration d'un délai de confirmation ; la coopérative décide de confirmer ou non. Les conséquences précises d'un refus et la gestion des commandes durablement en attente restent à cadrer.

### Version 0.5 — 12 novembre 2026
- Enregistrement de FR-007 : réservation du stock à la confirmation de la coopérative pour le paiement à la livraison du premier release ; réservation à la validation de commande pour le futur paiement en ligne. Les délais, refus et libérations de réservation restent à préciser.

### Version 0.4 — 12 novembre 2026
- Enregistrement de FR-006 : au premier release, additionner les frais calculés pour chaque expédition de coopérative vers la ville du client ; règle explicitement évolutive.

### Version 0.3 — 12 novembre 2026
- Enregistrement de FR-005 : principe de tarification par trajet entre ville de départ et ville de destination ; exemples indicatifs Casablanca–Casablanca (30 DH), Casablanca–Rabat (40 DH), Casablanca–Tanger (70 DH). Grille réelle à fournir ultérieurement ; application aux expéditions multi-coopératives encore à préciser.

### Version 0.2 — 12 novembre 2026
- Enregistrement de FR-004 : paiement à la livraison uniquement au premier release ; ajout du paiement en ligne ensuite, en conservant le paiement à la livraison.

### Version 0.1 — 12 novembre 2026
- Création du registre de cadrage fonctionnel du premier release.
- Enregistrement de FR-001 : SaaS de gestion + marketplace publique.
- Enregistrement de FR-002 : compte client obligatoire.
- Enregistrement de FR-003 : panier multi-coopératives, paiement unique, commande et facturation séparées par coopérative.
- Aucune modification du code applicatif.
