# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 1.98
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

### Question 84 — Informations minimales avant mise en vente d'un produit (VALIDÉ)
- **Question :** Puisqu'un produit devient automatiquement visible et achetable dès son enregistrement, quelles informations doivent obligatoirement être renseignées pour autoriser cet enregistrement ?
- **Réponse :** A — nom, description, catégorie, prix, stock disponible et au moins une photo sont obligatoires.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** domaine Products ; formulaire de création, validation des données et publication automatique sur la marketplace.
- **Conséquences :**
  - La création/mise en vente exige au minimum ces six éléments.
  - L'enregistrement ne doit pas publier un produit si l'un de ces éléments obligatoires manque.
  - Les caractéristiques complémentaires, variantes, poids et dimensions ne sont pas rendus obligatoires par cette décision.
  - Le mécanisme de brouillon pour les fiches incomplètes et les règles de validation détaillées restent à préciser.

### Question 85 — Comportement d'un produit dont le stock atteint zéro (VALIDÉ)
- **Question :** Lorsqu'un produit publié n'a plus de stock disponible, que doit voir le client sur la marketplace ?
- **Réponse :** A — le produit reste visible avec la mention « Rupture de stock », mais ne peut plus être commandé.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** domaines Products, Inventory et Marketplace.
- **Conséquences :**
  - Le produit reste consultable sur la marketplace même lorsque son stock disponible atteint zéro.
  - L'ajout au panier et la validation d'une commande pour ce produit sont bloqués tant qu'aucun stock n'est disponible.
  - Après réapprovisionnement, le produit redevient commandable selon le stock disponible.
  - Le comportement d'un panier déjà constitué lorsque le stock tombe à zéro reste à préciser.

### Question 86 — Affichage de la quantité en stock sur la marketplace (VALIDÉ)
- **Question :** Tant qu'un produit est disponible, quelle information de stock le client doit-il voir sur sa fiche produit ?
- **Réponse :** C — afficher une indication par seuil : « En stock », « Stock limité » ou « Rupture de stock ».
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** domaines Products, Inventory et Marketplace.
- **Conséquences :**
  - La marketplace n'affiche pas nécessairement la quantité exacte disponible.
  - Trois états visuels sont prévus : « En stock », « Stock limité » et « Rupture de stock ».
  - La règle de calcul du seuil « Stock limité » et son paramétrage restent à définir.
  - La rupture de stock conserve la règle validée à la question 85 : produit visible, commande bloquée.

### Question 87 — Qui définit le seuil « Stock limité » ? (VALIDÉ)
- **Question :** Qui doit pouvoir définir la quantité à partir de laquelle un produit passe de « En stock » à « Stock limité » ?
- **Réponse :** D — la coopérative définit un seuil individuellement pour chaque produit.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** domaines Inventory, Products et Marketplace.
- **Conséquences :**
  - Chaque produit peut avoir son propre seuil de stock limité.
  - Le seuil est paramétré par la coopérative propriétaire du produit.
  - La valeur du seuil, son caractère obligatoire ou facultatif et sa valeur par défaut restent à définir.

### Question 88 — Le seuil « Stock limité » est-il obligatoire ? (VALIDÉE)
- **Question :** Lors de la création d'un produit, comment gérer le seuil individuel « Stock limité » ?
- **Choix proposés :**
  - A. Le seuil est obligatoire : la coopérative doit saisir une quantité pour chaque produit.
  - B. Le seuil est facultatif ; si la coopérative ne le renseigne pas, MAHLINE applique une valeur par défaut de 5 unités.
  - C. Le seuil est facultatif ; sans seuil défini, le produit affiche « En stock » jusqu'à atteindre zéro.
  - D. Le seuil est calculé automatiquement par MAHLINE selon les ventes du produit.
- **Réponse :** B.
- **Décision :** le seuil individuel reste facultatif. Lorsqu'une coopérative ne renseigne pas de seuil pour un produit, MAHLINE applique automatiquement une valeur par défaut de 5 unités pour déterminer le statut « Stock limité ».
- **Conséquences :** chaque produit peut conserver un seuil personnalisé ; en l'absence de valeur personnalisée, le seuil effectif est de 5 unités. La valeur par défaut et le seuil personnalisé devront être distingués dans les règles métier et l'interface.
- **Périmètre concerné :** domaines Products et Inventory ; formulaire produit, validation et calcul/affichage de disponibilité.
- **Points restant à préciser :** possibilité de modifier la valeur par défaut globalement, effet d'une modification de seuil sur les produits existants, et règle exacte de comparaison (stock inférieur ou égal au seuil).

### Question 89 — Comment comparer le stock disponible au seuil « Stock limité » ? (VALIDÉE)
- **Question :** Avec un seuil de 5 unités, à partir de quel niveau le produit affiche-t-il « Stock limité » ?
- **Choix proposés :**
  - A. « Stock limité » dès que le stock disponible est inférieur ou égal au seuil.
  - B. « Stock limité » uniquement lorsque le stock disponible est strictement inférieur au seuil.
  - C. La coopérative choisit individuellement si la comparaison est stricte ou inclut le seuil.
  - D. MAHLINE applique une règle automatique selon le type de produit.
- **Réponse :** A.
- **Décision :** le statut « Stock limité » s'applique dès que le stock disponible est inférieur ou égal au seuil effectif. Ainsi, avec un seuil de 5 unités, un stock de 5 unités affiche déjà « Stock limité ».
- **Conséquences :** la règle s'applique au seuil personnalisé du produit ou, en l'absence de seuil personnalisé, à la valeur par défaut de 5 unités. Le stock nul conserve le statut « Rupture de stock » défini à la question 85, prioritaire sur « Stock limité ».
- **Périmètre concerné :** domaines Products et Inventory ; calcul du statut de disponibilité et affichage marketplace.
- **Points restant à préciser :** définition exacte du stock disponible (notamment réservations, commandes en cours et retours en inspection) et éventuelles règles de seuil par variante.
 
### Question 90 — Quel stock utiliser pour calculer la disponibilité ? (VALIDÉE)
- **Question :** Quelle quantité doit servir au calcul du statut de disponibilité d'un produit ?
- **Choix proposés :**
  - A. Le stock physique total enregistré par la coopérative.
  - B. Le stock disponible = stock physique moins les quantités réservées pour les commandes.
  - C. Le stock disponible = stock physique moins les quantités réservées et les quantités en préparation.
  - D. La coopérative choisit la méthode pour chaque produit.
- **Réponse :** C.
- **Décision :** le stock utilisé pour calculer la disponibilité est le stock physique diminué des quantités réservées pour les commandes et des quantités en préparation. Le statut « En stock », « Stock limité » ou « Rupture de stock » est calculé à partir de ce stock disponible, et non du stock physique brut.
- **Conséquences :** les quantités affectées à des commandes ou à leur préparation ne doivent pas être présentées comme disponibles à l'achat sur la marketplace. La règle devra être appliquée de façon cohérente lors de l'ajout au panier et de la validation de commande afin d'éviter la survente.
- **Périmètre concerné :** domaines Products et Inventory, intégration avec Orders/commandes ; calcul du stock disponible, affichage marketplace et contrôle de disponibilité.
- **Points restant à préciser :** distinguer précisément « réservé » et « en préparation » pour éviter de déduire deux fois une même quantité ; définir à quel événement chaque quantité entre dans ces catégories, quand elle en sort, et comment les annulations, refus, retours et incidents ajustent les quantités.
 
### Question 91 — À quel moment réserver le stock pour une commande COD ? (VALIDÉE)
- **Question :** À quel événement la quantité commandée doit-elle être réservée pour une commande avec paiement à la livraison (COD) ?
- **Choix proposés :**
  - A. Dès que le client valide sa commande.
  - B. Lorsque la coopérative confirme la commande.
  - C. Lorsque la coopérative commence la préparation.
  - D. Lorsque le transporteur récupère le colis.
- **Réponse :** B.
- **Décision :** pour une commande COD, la quantité est réservée au moment où la coopérative confirme la commande. Cette réponse confirme la règle déjà définie à FR-007.
- **Conséquences :** avant confirmation par la coopérative, la commande client ne déclenche pas de réservation ferme selon cette règle. À partir de la confirmation, la quantité doit être exclue du stock disponible à l'achat et suivie dans le cycle de préparation.
- **Périmètre concerné :** domaines Orders et Inventory ; confirmation de commande, réservation de stock et calcul du stock disponible.
- **Points restant à préciser :** traitement des commandes en attente avant confirmation, durée de réservation après confirmation, libération en cas d'annulation/refus et distinction technique entre réservation et quantité en préparation.
 
### Question 92 — Que devient la réservation si la coopérative refuse la commande ? (VALIDÉE)
- **Question :** Quel traitement appliquer au stock réservé lorsqu'une coopérative refuse une commande ?
- **Choix proposés :**
  - A. La réservation est immédiatement annulée et le stock redevient disponible.
  - B. La réservation reste bloquée jusqu'à validation par un `super_admin`.
  - C. La réservation est annulée uniquement après notification du client.
  - D. La coopérative choisit entre libérer le stock ou le maintenir temporairement.
- **Réponse :** A.
- **Décision :** dès que la coopérative refuse la commande, la réservation correspondante est immédiatement libérée et la quantité revient dans le stock disponible.
- **Conséquences :** le refus doit déclencher une opération cohérente et traçable sur la commande et la réservation afin d'éviter qu'une quantité reste bloquée ou soit libérée deux fois. Pour une commande multi-coopératives, cette libération concerne uniquement les lignes de la sous-commande refusée ; les autres sous-commandes continuent indépendamment.
- **Périmètre concerné :** domaines Orders et Inventory ; refus de commande, libération de réservation, recalcul du stock disponible et historique des mouvements.
- **Points restant à préciser :** traitement d'une annulation après confirmation, libération partielle, gestion des erreurs techniques et règle de libération des quantités déjà engagées dans la préparation.
 
### Question 93 — Annulation COD après confirmation de la coopérative (VALIDÉE)
- **Question :** Si le client souhaite annuler une commande COD après confirmation par la coopérative, mais avant la remise du colis au transporteur, que devient la commande ?
- **Choix proposés :**
  - A. La réservation est immédiatement libérée et le stock redevient disponible.
  - B. La coopérative doit d'abord accepter l'annulation.
  - C. Le stock reste réservé jusqu'à confirmation par un `super_admin`.
  - D. L'annulation est interdite après confirmation de la coopérative.
- **Réponse :** D — l'annulation est interdite après confirmation de la coopérative.
- **Décision :** pour une commande COD, le client ne peut plus annuler la commande après sa confirmation par la coopérative, même si le colis n'a pas encore été remis au transporteur. La possibilité de refuser le colis à la livraison et la procédure de retour restent régies par FR-009.
- **Conséquences :** la réservation de stock n'est pas libérée à la suite d'une demande d'annulation client postérieure à la confirmation. Le parcours doit distinguer une annulation avant confirmation d'un refus à la livraison, qui déclenche une procédure de retour.
- **Périmètre concerné :** domaines Orders et Inventory ; règles d'annulation COD, réservation/libération du stock et interface client.
- **Points restant à préciser :** traitement des demandes exceptionnelles d'annulation par le support ou la coopérative, erreurs de confirmation et cas où la commande est techniquement impossible à exécuter.

### Question 94 — Annulation d'une commande confirmée par la coopérative (VALIDÉE)
- **Question :** La coopérative peut-elle annuler une commande COD après l'avoir confirmée ?
- **Choix proposés :**
  - A. Non, aucune annulation n'est possible après confirmation.
  - B. Oui, uniquement avant le début de la préparation.
  - C. Oui, jusqu'à la remise du colis au transporteur, avec un motif obligatoire.
  - D. Oui, uniquement après autorisation d'un `super_admin`.
- **Réponse :** B, avec motif obligatoire.
- **Décision :** la coopérative peut annuler une commande qu'elle a confirmée uniquement tant que la préparation n'a pas commencé. Un motif d'annulation est obligatoire et doit être conservé dans l'historique de la commande.
- **Conséquences :** l'annulation par la coopérative libère la réservation de stock correspondante ; cette libération doit être cohérente, traçable et limitée à la sous-commande concernée dans un panier multi-coopératives.
- **Périmètre concerné :** domaines Orders et Inventory ; action d'annulation coopérative, contrôle de l'étape de préparation, motif, historique et libération du stock.
- **Points restant à préciser :** liste ou saisie libre des motifs, possibilité d'annulation exceptionnelle après le début de préparation, notifications client et traitement des frais éventuels.

### Question 95 — Début officiel de la préparation (VALIDÉE)
- **Question :** Quel événement définit officiellement le début de la préparation d'une commande ?
- **Choix proposés :**
  - A. Lorsque la coopérative clique sur « Commencer la préparation ».
  - B. Dès qu'un employé commence physiquement à préparer les produits.
  - C. Lorsque tous les produits sont rassemblés et prêts à emballer.
  - D. Lorsque la coopérative imprime le bon de préparation.
- **Réponse :** A.
- **Décision :** le début de préparation est matérialisé dans MAHLINE par l'action explicite de la coopérative « Commencer la préparation ». Cet événement constitue la limite après laquelle la coopérative ne peut plus annuler la commande selon la règle validée à la question 94.
- **Conséquences :** l'action doit être horodatée et attribuée à l'utilisateur qui l'effectue ; l'interface et les règles métier doivent s'appuyer sur cet événement enregistré, et non sur une estimation de l'activité physique.
- **Périmètre concerné :** domaines Orders et Inventory ; workflow de commande, action de préparation, contrôle d'annulation et historique.
- **Points restant à préciser :** permissions des membres de la coopérative, possibilité de revenir en arrière en cas d'erreur de clic, et traitement d'une préparation commencée mais interrompue.

### Question 96 — Annulation accidentelle du début de préparation (VALIDÉE)
- **Question :** Si un membre clique accidentellement sur « Commencer la préparation », peut-on annuler cet événement ?
- **Choix proposés :**
  - A. Non, l'action est définitive.
  - B. Un responsable de la coopérative peut annuler cette action avec un motif obligatoire.
  - C. Tout membre autorisé peut annuler cette action avec un motif obligatoire.
  - D. Seul le `super_admin` responsable de la coopérative peut annuler cette action.
- **Réponse :** B.
- **Décision :** un responsable de la coopérative peut annuler l'événement « Commencer la préparation » lorsqu'il a été déclenché par erreur. Un motif obligatoire doit être enregistré.
- **Conséquences :** l'annulation de cet événement doit être tracée sans effacer l'événement initial ni son horodatage. Le workflow doit revenir à un état cohérent et recalculer les possibilités d'action selon les règles de commande et de stock.
- **Périmètre concerné :** domaines Orders et Inventory ; permissions coopérative, workflow de préparation, audit/historique et règles d'annulation.
- **Points restant à préciser :** définition du rôle « responsable de la coopérative », nombre de corrections autorisées, état exact après annulation et conséquences si une opération physique a déjà commencé.

### Question 97 — Stock après annulation du début de préparation (VALIDÉE)
- **Question :** Que devient la réservation de stock lorsque le début de préparation est annulé par un responsable de la coopérative ?
- **Choix proposés :**
  - A. Le stock reste réservé ; seule l'étape de préparation est réinitialisée.
  - B. Le stock est libéré et redevient disponible.
  - C. Le responsable choisit entre conserver ou libérer la réservation.
  - D. La réservation est maintenue jusqu'à une nouvelle confirmation de la coopérative.
- **Réponse :** A.
- **Décision :** l'annulation de l'événement de début de préparation ne libère pas le stock. La quantité reste réservée pour la commande ; seule l'étape de préparation est réinitialisée.
- **Conséquences :** MAHLINE doit distinguer la réservation de stock de l'état de préparation. Le retour à l'étape antérieure ne doit ni libérer la quantité ni créer une seconde réservation lors du prochain démarrage de préparation.
- **Périmètre concerné :** domaines Orders et Inventory ; transitions de préparation, réservation de stock et prévention des doubles mouvements.
- **Points restant à préciser :** statut exact après réinitialisation, règles de reprise de préparation et traitement d'une annulation si des opérations physiques ont déjà été réalisées.

### Question 98 — Autorisation de démarrer la préparation (VALIDÉE)
- **Question :** Qui peut déclencher l'action « Commencer la préparation » ?
- **Choix proposés :**
  - A. Le responsable de la coopérative uniquement.
  - B. Tous les membres de la coopérative ayant la permission de gérer les commandes.
  - C. Le responsable et les membres qu'il autorise individuellement.
  - D. Le `super_admin` responsable de la coopérative uniquement.
- **Réponse :** B.
- **Décision :** tout membre de la coopérative disposant de la permission de gérer les commandes peut déclencher « Commencer la préparation ».
- **Conséquences :** l'autorisation repose sur la permission effective du membre, et non uniquement sur son appartenance à la coopérative ou son intitulé de rôle. L'action doit rester attribuée et horodatée.
- **Périmètre concerné :** domaines Identity, Cooperatives, Orders et Inventory ; permissions, interface de gestion des commandes et transition de préparation.
- **Points restant à préciser :** matrice détaillée des permissions de gestion des commandes, possibilité de délégation et permission nécessaire pour annuler un début de préparation erroné.

### Question 99 — Autorisation d'annuler un début de préparation erroné (VALIDÉE)
- **Question :** Qui peut annuler un début de préparation déclenché par erreur ?
- **Choix proposés :**
  - A. Tout membre disposant de la permission « Gérer les commandes ».
  - B. Uniquement le responsable de la coopérative.
  - C. Le responsable et les membres disposant d'une permission spécifique « Annuler la préparation ».
  - D. Le `super_admin` responsable de la coopérative.
- **Réponse :** B et D.
- **Décision :** l'annulation d'un début de préparation erroné est réservée au responsable de la coopérative et au `super_admin` chargé de cette coopérative. Les autres membres, même s'ils disposent de la permission « Gérer les commandes » pour démarrer la préparation, ne peuvent pas annuler cet événement.
- **Conséquences :** l'action d'annulation exige un motif obligatoire et doit être attribuée et horodatée. L'événement initial reste conservé dans l'historique ; la réservation de stock est maintenue conformément à la question 97.
- **Périmètre concerné :** domaines Identity, Cooperatives, Orders et Inventory ; autorisations, correction de workflow, historique et réservation de stock.
- **Points restant à préciser :** définition exacte du « responsable de la coopérative », traitement d'un désaccord entre ce responsable et le `super_admin`, et permissions en cas de remplacement temporaire du responsable.

### Question 100 — Arbitrage en cas de désaccord sur l'annulation de préparation (VALIDÉE)
- **Question :** Que faire si le responsable de la coopérative et le `super_admin` chargé de cette coopérative ne sont pas d'accord sur l'annulation d'un début de préparation erroné ?
- **Choix proposés :**
  - A. Le responsable de la coopérative a le dernier mot.
  - B. Le `super_admin` chargé de la coopérative a le dernier mot.
  - C. Le `super_admin_root` tranche.
  - D. L'annulation est bloquée jusqu'à une décision commune.
- **Réponse :** A.
- **Décision :** en cas de désaccord entre le responsable de la coopérative et le `super_admin` chargé de celle-ci, le responsable de la coopérative a le dernier mot sur l'annulation du début de préparation erroné.
- **Conséquences :** l'application doit respecter cette priorité dans le contrôle d'autorisation et conserver une trace de la décision et de son auteur. Le motif obligatoire reste exigé.
- **Périmètre concerné :** domaines Cooperatives, Identity, Orders et Inventory ; arbitrage des autorisations et correction du workflow de préparation.
- **Points restant à préciser :** identification du responsable habilité en cas de plusieurs responsables, délégation ou absence temporaire, et procédure de contestation exceptionnelle.

### Question 101 — Identification du responsable de la coopérative (VALIDÉE)
- **Question :** Comment identifier le responsable de la coopérative dans MAHLINE ?
- **Choix proposés :**
  - A. Le `cooperative_admin` principal de la coopérative.
  - B. Un membre désigné explicitement comme responsable par le `super_admin`.
  - C. Le membre ayant le plus de permissions.
  - D. Le responsable est désigné par le `super_admin_root`.
- **Réponse :** A.
- **Décision :** le responsable de la coopérative est le `cooperative_admin` principal associé à cette coopérative.
- **Conséquences :** ce rôle constitue la référence pour l'arbitrage défini à la question 100 et pour l'annulation d'un début de préparation erroné définie à la question 99. L'application doit pouvoir identifier sans ambiguïté le `cooperative_admin` principal de chaque coopérative.
- **Périmètre concerné :** domaines Identity et Cooperatives, intégration avec Orders ; identification du responsable et contrôle des autorisations.
- **Points restant à préciser :** règles de désignation/remplacement du `cooperative_admin` principal, cas de plusieurs administrateurs, absence ou désactivation du responsable et transfert de responsabilité.

### Question 102 — Nombre de responsables administrateurs par coopérative (VALIDÉE)
- **Question :** Une coopérative peut-elle avoir plusieurs `cooperative_admin` ?
- **Choix proposés :**
  - A. Non, un seul `cooperative_admin` par coopérative.
  - B. Oui, plusieurs, mais un seul est désigné comme administrateur principal.
  - C. Oui, plusieurs avec les mêmes pouvoirs, sans administrateur principal.
  - D. Oui, le nombre est limité par le forfait SaaS.
- **Réponse :** A.
- **Décision :** chaque coopérative dispose d'un seul compte `cooperative_admin`.
- **Conséquences :** ce compte est automatiquement le responsable principal de la coopérative pour les décisions et arbitrages définis précédemment. Aucun mécanisme de désignation entre plusieurs administrateurs n'est nécessaire dans le périmètre retenu.
- **Périmètre concerné :** Identity, Cooperatives, gestion des utilisateurs et autorisations.
- **Points restant à préciser :** procédure de remplacement du `cooperative_admin`, notamment en cas de départ, désactivation, suspension ou indisponibilité ; transfert de ses responsabilités et continuité de gestion.

### Question 103 — Remplacement du responsable de la coopérative (VALIDÉE)

- **Question :** Que se passe-t-il si le `cooperative_admin` quitte la coopérative ou devient indisponible ?
- **Choix proposés :**
  - A. Le `super_admin` responsable peut désigner un autre membre comme nouveau `cooperative_admin`.
  - B. Seul le `super_admin_root` peut désigner le remplaçant.
  - C. Le `super_admin` propose un remplaçant, mais le `super_admin_root` doit valider.
  - D. La coopérative est bloquée jusqu'à l'intervention du `super_admin_root`.
- **Réponse :** A.
- **Décision :** le `super_admin` responsable de la coopérative peut désigner un autre membre comme nouveau `cooperative_admin` lorsque le responsable actuel quitte la coopérative ou devient indisponible.
- **Conséquences :** la coopérative conserve un seul `cooperative_admin`, qui devient son responsable principal. Les modalités de transfert des accès, de révocation de l'ancien compte et de traçabilité de la désignation restent à préciser.
- **Périmètre concerné :** Identity, Cooperatives, gestion des utilisateurs et autorisations.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 104 — Types de fichiers acceptés par Media au premier release (VALIDÉE)

**Question :** Quels types de fichiers le domaine Media doit-il gérer au premier release ?

**Options proposées :**
- A. Images uniquement : JPG, PNG, WebP.
- B. Images et documents : PDF, DOC, DOCX, XLS, XLSX.
- C. Images, documents et vidéos.
- D. Tous les types de fichiers, avec restrictions de sécurité selon leur usage.

**Réponse retenue (corrigée par l'utilisateur) :** Images JPG, JPEG et PNG ; documents PDF ; vidéos autorisées.

**Décision fonctionnelle :**
- Images autorisées : JPG, JPEG et PNG.
- Documents autorisés : PDF.
- Vidéos autorisées au premier release.
- Formats exclus : WebP, Word (DOC/DOCX) et Excel (XLS/XLSX).

**Conséquences / règles à prévoir :**
- La validation des extensions doit être complétée par une vérification réelle du type MIME et du contenu du fichier.
- Définir des limites de taille et de durée pour les vidéos, des règles de stockage et des contrôles de sécurité lors de l'audit technique.
- Les usages autorisés (produits, coopératives, catégories, etc.) et les règles d'accès restent à préciser dans les questions suivantes.

**Périmètre concerné :** Domaine transversal Media et tous les domaines consommateurs de fichiers.

**Statut :** Décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 105 — Limites de taille des fichiers Media (VALIDÉE)

**Question :** Quelle taille maximale autoriser pour les fichiers ?

**Options proposées :**
- A. Images : 2 Mo / PDF : 5 Mo / Vidéos : 20 Mo.
- B. Images : 5 Mo / PDF : 10 Mo / Vidéos : 50 Mo.
- C. Images : 10 Mo / PDF : 20 Mo / Vidéos : 100 Mo.
- D. Autres limites (à préciser).

**Réponse retenue :** A.

**Décision fonctionnelle :**
- Images : maximum 2 Mo par fichier.
- PDF : maximum 5 Mo par fichier.
- Vidéos : maximum 20 Mo par fichier.

**Conséquences / règles à prévoir :**
- Appliquer les limites côté serveur, indépendamment des contrôles côté interface.
- Définir et tester les contrôles de taille avant stockage, ainsi que les messages d'erreur associés.
- Les limites de durée, formats vidéo précis, règles de stockage et éventuelles contraintes de traitement restent à préciser.

**Périmètre concerné :** Domaine transversal Media et tous les domaines consommateurs de fichiers.

**Statut :** Décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 106 — Formats vidéo acceptés et conversion (VALIDÉE)

**Question :** Quels formats vidéo accepter ?

**Options proposées :**
- A. MP4 uniquement.
- B. MP4 et MOV.
- C. MP4, MOV et AVI.
- D. Tous les formats vidéo courants, avec conversion automatique vers un format standard.

**Réponse retenue :** D.

**Décision fonctionnelle :**
- Accepter les formats vidéo courants au téléversement.
- Prévoir une conversion automatique vers un format vidéo standard pour la lecture et la diffusion.

**Conséquences / règles à prévoir :**
- Définir pendant l'audit technique la liste exacte des conteneurs et codecs acceptés en entrée.
- Choisir le format standard de sortie et le mécanisme de conversion.
- Encadrer la conversion (ressources, durée, stockage, échecs, conservation éventuelle de l'original) et sécuriser le traitement des fichiers.
- La limite de taille de 20 Mo par vidéo reste applicable, conformément à la Q105.

**Périmètre concerné :** Domaine transversal Media et tous les domaines consommateurs de vidéos.

**Statut :** Décision fonctionnelle enregistrée ; détails techniques à auditer avant implémentation.

### Question 107 — Choix du format standard de sortie vidéo (VALIDÉE)

**Question :** Quel format standard utiliser après conversion ?

**Options proposées :**
- A. MP4 (H.264), pour une compatibilité maximale.
- B. WebM (VP9), pour optimiser la diffusion web.
- C. MP4 (H.264) et WebM (VP9), avec les deux versions générées.
- D. Laisser le système choisir automatiquement le format optimal.

**Réponse retenue :** D.

**Décision fonctionnelle :**
- Le système choisit automatiquement le format de sortie adapté à la lecture et à la diffusion.
- Le choix du format et des paramètres de conversion est une décision technique à établir lors de l'audit.

**Conséquences / règles à prévoir :**
- Garantir une lecture compatible avec les navigateurs et appareils ciblés.
- Définir les critères de sélection du format, la qualité, la résolution et les paramètres de compression.
- Prévoir un comportement de repli si la conversion échoue ou si le format choisi n'est pas pris en charge.
- La limite de 20 Mo par vidéo à l'entrée reste applicable (Q105).

**Périmètre concerné :** Domaine transversal Media et tous les domaines consommateurs de vidéos.

**Statut :** Décision fonctionnelle enregistrée ; détails techniques à auditer avant implémentation.

### Question 108 — Conservation du fichier vidéo original après conversion (VALIDÉE)

**Question :** Faut-il conserver le fichier vidéo original après conversion ?

**Options proposées :**
- A. Oui, conserver l'original et la version convertie.
- B. Non, remplacer l'original par la version convertie.
- C. Conserver l'original temporairement, puis le supprimer après validation de la conversion.
- D. Conserver l'original uniquement si la conversion échoue.

**Réponse retenue :** C.

**Décision fonctionnelle :**
- Conserver temporairement le fichier original pendant la conversion.
- Supprimer l'original après validation réussie de la version convertie.
- En cas d'échec de conversion, le traitement ne doit pas supprimer l'unique fichier exploitable ; la gestion de l'échec sera précisée techniquement.

**Conséquences / règles à prévoir :**
- Définir les critères de validation de la conversion avant suppression de l'original.
- Prévoir une durée maximale de conservation temporaire et un nettoyage des fichiers temporaires orphelins.
- Garantir que la vidéo convertie est lisible avant de supprimer l'original.

**Périmètre concerné :** Domaine transversal Media et pipeline de traitement vidéo.

**Statut :** Décision fonctionnelle enregistrée ; détails techniques à auditer avant implémentation.

### Question 109 — Autorisations de téléversement dans Media (VALIDÉE)

**Question :** Qui peut téléverser des fichiers dans Media ?

**Options proposées :**
- A. Tous les rôles : `super_admin_root`, `super_admin`, `cooperative_admin` et `user`, selon leurs droits.
- B. `super_admin_root` et `super_admin` uniquement.
- C. `super_admin_root`, `super_admin` et `cooperative_admin`.
- D. Chaque rôle peut téléverser uniquement dans son propre espace et selon les permissions de son domaine.

**Réponse retenue :** D.

**Décision fonctionnelle :**
- Le téléversement est autorisé uniquement dans le périmètre auquel l'utilisateur a accès.
- Les droits de téléversement dépendent du rôle, de l'espace concerné et des permissions du domaine consommateur.
- Media ne doit pas créer de privilège transversal qui contourne les autorisations du domaine métier.

**Conséquences / règles à prévoir :**
- Vérifier côté serveur l'identité, le rôle, la permission métier et le périmètre coopératif avant tout téléversement.
- Appliquer les mêmes règles lors de la consultation, du téléchargement, du remplacement et de la suppression des médias.
- Définir les permissions précises par domaine et par type d'utilisateur dans les questions suivantes et l'audit d'autorisation.

**Périmètre concerné :** Domaine transversal Media, Identity et tous les domaines consommateurs de fichiers.

**Statut :** Décision fonctionnelle enregistrée ; matrice détaillée des permissions à préciser.

### Question 110 — Gestion des médias par le cooperative_admin (VALIDÉE)

**Question :** Un `cooperative_admin` peut-il gérer les médias de sa coopérative ?

**Options proposées :**
- A. Oui : ajouter, consulter, remplacer et supprimer les médias de sa coopérative.
- B. Oui : ajouter et consulter uniquement, sans remplacement ni suppression.
- C. Oui : ajouter, consulter et remplacer, mais suppression réservée au `super_admin`.
- D. Non : seul le `super_admin` responsable gère les médias de la coopérative.

**Réponse retenue :** A.

**Décision fonctionnelle :**
- Le `cooperative_admin` peut ajouter, consulter, remplacer et supprimer les médias rattachés à sa propre coopérative.
- Cette gestion reste strictement limitée au périmètre de sa coopérative.

**Conséquences / règles à prévoir :**
- Vérifier systématiquement l'appartenance du média à la coopérative de l'utilisateur.
- Interdire l'accès, le remplacement ou la suppression des médias d'une autre coopérative.
- Conserver les contrôles d'autorisation métier et la traçabilité des opérations de gestion.

**Périmètre concerné :** Media, Cooperatives et Identity.

**Statut :** Décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 111 — Périmètre de gestion des médias par le super_admin et le cooperative_admin (VALIDÉE)
- **Question :** Lorsqu'un `super_admin` est affecté à une coopérative, quels droits de gestion des médias possède-t-il par rapport au `cooperative_admin` ?
- **Choix proposés :**
  - A. Le `super_admin` et le `cooperative_admin` ont les mêmes droits de gestion.
  - B. Le `super_admin` peut tout gérer ; le `cooperative_admin` conserve ses droits.
  - C. Le `super_admin` supervise et consulte ; le `cooperative_admin` effectue les opérations.
  - D. Le `super_admin` peut gérer les médias uniquement en cas de délégation ou d'intervention nécessaire.
- **Réponse :** Précision fonctionnelle — le `super_admin` peut gérer les médias de toutes les coopératives qui lui sont assignées ; le `cooperative_admin` gère uniquement les médias de sa propre coopérative.
- **Décision :** les droits de gestion du `super_admin` sont limités aux coopératives qui lui sont explicitement affectées. Le `cooperative_admin` conserve les opérations de gestion des médias dans le seul périmètre de sa coopérative. Il n'existe aucun accès transversal du `cooperative_admin` aux médias d'autres coopératives.
- **Conséquences :**
  - Le `super_admin` peut ajouter, consulter, remplacer et supprimer les médias dans chacune de ses coopératives assignées, sous réserve des permissions métier.
  - Le `cooperative_admin` peut ajouter, consulter, remplacer et supprimer les médias de sa coopérative uniquement.
  - L'isolation multi-coopératives doit être vérifiée côté serveur pour chaque opération et chaque accès au fichier ; masquer une action dans l'interface ne suffit pas.
  - Le `super_admin_root` reste soumis à ses droits globaux définis par l'architecture et les politiques applicables.
- **Périmètre concerné :** domaines Media, Cooperatives et Identity ; autorisations, rattachement des médias et isolation multi-coopératives.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.
- **Points restant à préciser :** règles exactes d'intervention du `super_admin_root`, traitement des médias partagés entre domaines, et éventuelles permissions granulaires de téléversement/suppression.

### Question 112 — Droits globaux du super_admin_root sur les médias (VALIDÉE)
- **Question :** Le `super_admin_root` doit-il avoir accès aux médias de toutes les coopératives ?
- **Choix proposés :**
  - A. Oui, accès complet : ajouter, consulter, remplacer et supprimer.
  - B. Consultation uniquement.
  - C. Accès complet, mais suppression soumise à une confirmation avec motif obligatoire.
  - D. Accès complet uniquement en cas d'intervention nécessaire.
- **Réponse :** A.
- **Décision :** le `super_admin_root` dispose d'un accès complet aux médias de toutes les coopératives : ajout, consultation, remplacement et suppression.
- **Conséquences :**
  - Les opérations du `super_admin_root` doivent rester authentifiées, autorisées et journalisées selon les règles de sécurité et d'audit de MAHLINE.
  - Cet accès global ne modifie pas les limites de périmètre du `super_admin` et du `cooperative_admin` définies à la question 111.
  - Les opérations doivent préserver l'intégrité des fichiers et les références utilisées par les domaines métier.
- **Périmètre concerné :** domaines Media, Cooperatives, Identity et Audit ; autorisations globales, gestion des fichiers et traçabilité.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.
- **Points restant à préciser :** éventuelles confirmations ou motifs obligatoires pour certaines suppressions sensibles, ainsi que les règles de conservation et de suppression physique.

### Question 113 — Suppression d'un média utilisé par plusieurs éléments (VALIDÉE)
- **Question :** Lorsqu'un média est utilisé par plusieurs éléments (produits, catégories, coopératives), que doit-il se passer si un utilisateur demande sa suppression ?
- **Choix proposés :**
  - A. Suppression interdite tant que le média est utilisé.
  - B. Suppression autorisée uniquement après détachement de toutes ses utilisations.
  - C. Suppression logique immédiate ; le fichier physique est conservé tant qu'il est encore référencé.
  - D. Suppression automatique de toutes les références et du fichier.
- **Réponse :** D, avec affichage préalable d'un message de confirmation.
- **Décision :** après confirmation explicite de l'utilisateur, MAHLINE supprime toutes les références au média dans les éléments qui l'utilisent, puis supprime le média et son fichier associé.
- **Conséquences :**
  - Avant l'exécution, l'interface affiche un message de confirmation indiquant clairement que la suppression retirera le média de tous les éléments qui l'utilisent et supprimera le fichier.
  - Aucune suppression ne doit être exécutée avant confirmation explicite.
  - Le traitement doit identifier et supprimer proprement toutes les associations concernées, sans supprimer les éléments métier eux-mêmes (produits, catégories, coopératives, etc.).
  - L'opération doit être autorisée selon le périmètre de l'acteur et tracée ; les références ne doivent pas rester orphelines.
  - Les modalités techniques de suppression physique, notamment stockage distant, échec partiel et reprise, restent à définir lors de l'audit technique.
- **Périmètre concerné :** domaines Media, Products, Categories, Cooperatives et Audit ; associations, suppression, confirmation utilisateur et intégrité référentielle.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.
- **Points restant à préciser :** contenu exact du message de confirmation, possibilité d'annuler, traitement des échecs de suppression du fichier et conservation éventuelle dans les sauvegardes.

### Question 114 — Récupération d’un média supprimé accidentellement

- **Question :** Peut-on récupérer un média supprimé accidentellement ?
- **Options proposées :**
  - A. Non, la suppression est définitive.
  - B. Oui, via une corbeille pendant 30 jours.
  - C. Oui, via une corbeille pendant 90 jours.
  - D. Oui, récupération réservée au `super_admin_root` depuis les sauvegardes.
- **Réponse utilisateur :** A.
- **Décision fonctionnelle :** La suppression d’un média est définitive ; aucune corbeille ni procédure de restauration utilisateur n’est prévue.
- **Conséquences :** Après confirmation explicite de suppression (Q113), le média et son fichier associé sont supprimés sans période de rétention applicative ni restauration depuis une corbeille. La suppression doit rester autorisée selon le rôle, le périmètre coopératif et les permissions métier ; les références doivent être traitées conformément à Q113 et l’opération doit être auditée. Les sauvegardes d’infrastructure restent distinctes d’une fonctionnalité de restauration métier.
- **Domaines concernés :** Media, Products, Categories, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points à vérifier à l’audit technique :** comportement des stockages et sauvegardes, suppression physique/remote, échec partiel et reprise, cohérence des références et journaux d’audit.

### Question 115 — Remplacement et pluralité des photos d’un produit

- **Question :** Lorsqu’un utilisateur remplace un média déjà utilisé, que doit-il advenir de l’ancien fichier ?
- **Options proposées :**
  - A. L’ancien fichier est supprimé immédiatement après le remplacement.
  - B. L’ancien fichier est conservé pendant 30 jours.
  - C. L’ancien fichier est conservé dans l’historique du média, consultable par les personnes autorisées.
  - D. Le remplacement crée un nouveau média ; l’ancien reste inchangé jusqu’à sa suppression explicite.
- **Réponse utilisateur :** D, avec la précision qu’un produit peut avoir plusieurs photos.
- **Décision fonctionnelle :** Un remplacement crée un nouveau média sans modifier ni supprimer automatiquement l’ancien. Un produit peut être associé à plusieurs photos ; le modèle fonctionnel ne doit donc pas imposer une photo unique par produit.
- **Conséquences :** Le remplacement ne doit pas entraîner la suppression implicite du média précédent. L’utilisateur peut gérer plusieurs photos pour un même produit, sous réserve de ses permissions et de son périmètre coopératif. La suppression d’un ancien média reste une action distincte, soumise à la confirmation et aux règles de suppression définies en Q113 et Q114. Les règles de nombre maximal de photos, d’ordre d’affichage et de photo principale restent à préciser.
- **Domaines concernés :** Media, Products, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points ouverts :** nombre maximal de photos par produit, ordre d’affichage, désignation d’une photo principale, gestion d’un média réutilisé par plusieurs éléments.

### Question 116 — Nombre maximal de photos par produit

- **Question :** Combien de photos un produit peut-il avoir au maximum ?
- **Options proposées :**
  - A. 3 photos.
  - B. 5 photos.
  - C. 10 photos.
  - D. Nombre illimité, dans la limite du stockage autorisé.
- **Réponse utilisateur :** C.
- **Décision fonctionnelle :** Un produit peut être associé à un maximum de 10 photos.
- **Conséquences :** Le contrôle de cette limite doit être appliqué côté serveur, y compris lors des ajouts multiples et des associations concurrentes. Les remplacements qui créent un nouveau média doivent respecter la limite de 10 photos associées au produit. Les règles de suppression et de réassociation restent celles définies dans les questions précédentes.
- **Domaines concernés :** Media, Products, Cooperatives.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points ouverts :** ordre d’affichage et désignation de la photo principale.

### Question 117 — Choix de la photo principale du produit

- **Question :** Comment choisir la photo principale affichée dans la boutique ?
- **Options proposées :**
  - A. La première photo ajoutée devient automatiquement la photo principale.
  - B. La dernière photo ajoutée devient automatiquement la photo principale.
  - C. Le responsable choisit manuellement une photo principale parmi les 10 photos.
  - D. La photo principale est choisie automatiquement, mais peut être modifiée manuellement.
- **Réponse utilisateur :** C.
- **Décision fonctionnelle :** La personne autorisée choisit manuellement une photo principale parmi les photos associées au produit.
- **Conséquences :** La photo principale doit obligatoirement être l’une des photos actuellement associées au produit. Le choix et le changement de photo principale doivent respecter les permissions et le périmètre coopératif, et être contrôlés côté serveur. Un produit peut conserver jusqu’à 10 photos au total.
- **Domaines concernés :** Media, Products, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points ouverts :** comportement si la photo principale est supprimée ou dissociée ; possibilité de réordonner les photos.

### Question 118 — Remplacement automatique de la photo principale supprimée

- **Question :** Si la photo principale est supprimée, que doit-il se passer ?
- **Options proposées :**
  - A. La première photo restante devient automatiquement la photo principale.
  - B. La deuxième photo la plus ancienne devient automatiquement la photo principale.
  - C. Aucune photo principale n’est définie ; le responsable doit en choisir une nouvelle.
  - D. La suppression est bloquée tant qu’une autre photo n’a pas été choisie comme principale.
- **Réponse utilisateur :** A, avec la précision que le `cooperative_admin` peut également choisir manuellement une photo comme principale.
- **Décision fonctionnelle :** Lorsqu’une photo principale est supprimée, la première photo restante devient automatiquement la photo principale. En dehors de ce cas automatique, le `cooperative_admin` peut choisir manuellement une photo principale parmi les photos du produit.
- **Conséquences :** Le système doit garantir qu’une photo principale désignée appartient aux photos associées au produit. La sélection manuelle doit être réservée au `cooperative_admin` dans le périmètre de sa propre coopérative, conformément aux permissions métier. La règle d’attribution automatique après suppression doit être déterministe ; l’ordre de référence reste à définir.
- **Domaines concernés :** Media, Products, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points ouverts :** critère exact définissant la « première » photo restante (ordre d’ajout ou ordre d’affichage) ; droits éventuels des autres rôles pour changer manuellement la photo principale.

### Question 119 — Réorganisation de l’ordre des photos

- **Question :** Le `cooperative_admin` doit-il pouvoir modifier manuellement l’ordre d’affichage des photos du produit ?
- **Options proposées :**
  - A. Oui, par glisser-déposer.
  - B. Non, les photos restent dans leur ordre d’ajout.
  - C. Oui, avec des boutons « Monter » et « Descendre ».
- **Réponse utilisateur :** A ou C ; les deux modes d’interaction sont acceptables.
- **Décision fonctionnelle :** Le `cooperative_admin` peut réorganiser manuellement les photos d’un produit. L’interface peut utiliser le glisser-déposer ou des boutons « Monter » et « Descendre » ; le choix du mécanisme d’interface sera fait lors de la conception UX/technique.
- **Conséquences :** L’ordre choisi doit être enregistré et conservé dans l’affichage du produit. La réorganisation est limitée aux produits de la coopérative de l’acteur et soumise aux permissions métier. Elle ne doit pas modifier la désignation de la photo principale, qui reste une propriété distincte.
- **Domaines concernés :** Media, Products, Cooperatives.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Point ouvert :** choix UX entre glisser-déposer et boutons, selon l’ergonomie et les contraintes des interfaces prises en charge.

### Question 120 — Visibilité des photos des produits dans la boutique

- **Question :** Qui peut consulter les photos d’un produit publié dans la boutique ?
- **Options proposées :**
  - A. Tout visiteur, même sans compte.
  - B. Uniquement les clients connectés.
  - C. Uniquement les utilisateurs Mahline autorisés.
  - D. Tout visiteur peut voir les photos du produit publié, mais les photos des produits non publiés restent privées.
- **Réponse utilisateur :** A, B et D.
- **Décision fonctionnelle :** Les photos d’un produit publié dans la boutique sont consultables par les visiteurs anonymes comme par les clients connectés. Les photos des produits non publiés restent privées. L’option D exprime la règle de visibilité générale, tandis que A et B confirment l’accès des deux catégories de visiteurs.
- **Conséquences :** L’accès public aux médias doit être conditionné à la publication effective du produit et à son état de visibilité. Les médias de produits brouillons, non publiés, désactivés ou autrement non visibles ne doivent pas être exposés par une URL directe ou un endpoint public. La visibilité des médias ne doit pas étendre celle du produit.
- **Domaines concernés :** Media, Products, Catalog, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points à vérifier :** définition exacte des états de publication et de visibilité du produit ; protection des fichiers et variantes par les routes/stockages publics.

### Question 121 — Visibilité des photos des coopératives

- **Question :** Les photos de profil et de présentation d’une coopérative doivent-elles être visibles publiquement ?
- **Options proposées :**
  - A. Oui, pour toutes les coopératives.
  - B. Oui, uniquement pour les coopératives actives et visibles dans la boutique.
  - C. Non, elles sont réservées aux utilisateurs connectés.
  - D. Chaque coopérative choisit la visibilité de ses propres photos.
- **Réponse utilisateur :** D.
- **Décision fonctionnelle :** Chaque coopérative peut définir la visibilité de ses propres photos de profil et de présentation.
- **Conséquences :** Le paramètre de visibilité doit être géré dans le périmètre de la coopérative et appliqué côté serveur à chaque accès au média. Les rôles autorisés à modifier ce paramètre doivent respecter les règles de gestion de la coopérative : le `cooperative_admin` pour sa propre coopérative, le `super_admin` pour les coopératives qui lui sont assignées et le `super_admin_root` pour toutes les coopératives. Une photo définie comme privée ne doit pas être accessible par URL directe ou endpoint public.
- **Domaines concernés :** Media, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points à vérifier :** portée exacte du réglage (par photo ou par ensemble de photos) ; visibilité par défaut à la création ; comportement pour une coopérative inactive, suspendue ou non publiée.

### Question 122 — Visibilité par défaut des photos d’une coopérative

- **Question :** Lorsqu’une coopérative ajoute une nouvelle photo, quelle visibilité doit être appliquée par défaut ?
- **Options proposées :**
  - A. Publique par défaut.
  - B. Privée par défaut.
  - C. Reprendre automatiquement la visibilité choisie pour les autres photos de la coopérative.
  - D. Demander de choisir la visibilité à chaque ajout.
- **Réponse utilisateur :** A.
- **Décision fonctionnelle :** Toute nouvelle photo de profil ou de présentation ajoutée par une coopérative est publique par défaut. Cette valeur par défaut n'empêche pas la coopérative de modifier ensuite sa visibilité conformément à la question 121.
- **Conséquences :** L'ajout initialise la visibilité à publique. Le contrôle d'accès doit néanmoins vérifier l'état de visibilité enregistré à chaque consultation ; les photos passées explicitement en privé ne doivent pas être exposées publiquement. Le paramètre et ses changements doivent être protégés par les autorisations du périmètre coopératif et tracés.
- **Domaines concernés :** Media, Cooperatives, Audit.
- **Statut :** Décision fonctionnelle enregistrée ; non implémentée.
- **Points à vérifier :** confirmation que cette règle par défaut s'applique aux deux catégories (profil et présentation) ; comportement lors du remplacement d'une photo existante et des imports multiples.

### Question 123 — Conservation de l’ancienne photo lors du remplacement (VALIDÉE)
- **Question :** Lorsqu'une coopérative remplace une photo de profil ou de présentation, que doit-il se passer avec l'ancienne photo ?
- **Choix proposés :**
  - A. Elle est supprimée immédiatement après le remplacement.
  - B. Elle est conservée pendant 30 jours.
  - C. Elle est conservée dans l'historique et reste consultable par les personnes autorisées.
  - D. Le remplacement crée un nouveau média ; l'ancienne photo reste inchangée jusqu'à sa suppression explicite.
- **Réponse :** D.
- **Décision :** le remplacement d'une photo de profil ou de présentation crée un nouveau média. L'ancienne photo reste inchangée et conservée tant qu'une suppression explicite n'a pas été demandée.
- **Conséquences :** le remplacement ne supprime pas automatiquement l'ancien fichier ni son enregistrement média. Les deux médias doivent rester distincts ; la photo actuellement utilisée par la coopérative est identifiée séparément de l'ancienne. Toute suppression ultérieure doit suivre les règles de suppression et d'autorisation déjà validées.
- **Périmètre concerné :** domaine Media ; médias de profil et de présentation des coopératives, remplacement, association au profil, conservation et suppression explicite.
- **Points restant à préciser :** éventuelle consultation d'un historique des anciennes photos, durée de conservation, et modalités de suppression explicite selon les références encore actives.

### Question 124 — Rôles autorisés à supprimer une ancienne photo de coopérative (VALIDÉE)
- **Question :** Lorsqu'une ancienne photo de profil ou de présentation n'est plus utilisée, qui peut demander sa suppression ?
- **Choix proposés :**
  - A. Seul le `cooperative_admin` de la coopérative.
  - B. Le `cooperative_admin` et le `super_admin` responsable de cette coopérative.
  - C. Le `cooperative_admin`, le `super_admin` responsable et le `super_admin_root`.
  - D. Seul le `super_admin_root`.
- **Réponse :** C — les trois rôles peuvent supprimer directement l'ancienne photo.
- **Décision :** le `cooperative_admin` de la coopérative, le `super_admin` qui lui est affecté et le `super_admin_root` sont tous autorisés à supprimer directement une ancienne photo de profil ou de présentation qui n'est plus utilisée. Aucune validation préalable d'un autre rôle n'est requise.
- **Conséquences :**
  - Le `cooperative_admin` agit uniquement sur les médias de sa propre coopérative.
  - Le `super_admin` agit uniquement sur les médias des coopératives qui lui sont affectées.
  - Le `super_admin_root` peut agir sur les médias de toutes les coopératives.
  - Chaque suppression doit respecter les contrôles d'autorisation et d'isolation multi-coopératives, être confirmée selon la règle de confirmation déjà validée et être tracée.
  - Cette autorisation concerne les anciennes photos qui ne sont plus utilisées ; elle ne modifie pas les règles de suppression des médias encore référencés.
- **Périmètre concerné :** domaine Media ; médias de profil et de présentation des coopératives, suppression explicite, autorisations par rôle, isolation multi-coopératives et audit.
- **Points restant à préciser :** gestion technique des références partagées ou encore actives et traitement d'une éventuelle erreur de suppression.

### Question 125 — Suppression d'une photo encore utilisée (VALIDÉE)
- **Question :** Si une photo est encore utilisée comme photo principale ou photo de présentation, que doit faire MAHLINE lorsqu'un utilisateur demande sa suppression ?
- **Choix proposés :**
  - A. Bloquer la suppression jusqu'à ce qu'une autre photo soit sélectionnée.
  - B. Demander de choisir une photo de remplacement avant de confirmer la suppression.
  - C. Autoriser la suppression et retirer automatiquement la photo de son emplacement.
  - D. Autoriser la suppression uniquement au `super_admin_root`.
- **Réponse :** C.
- **Décision :** la suppression d'une photo encore utilisée est autorisée. MAHLINE supprime le média demandé et le retire automatiquement de l'emplacement où il était utilisé, sans imposer au préalable la sélection d'une photo de remplacement.
- **Conséquences :**
  - La suppression et le retrait de toutes les associations concernées doivent être cohérents et traçables.
  - Pour une photo principale de produit, la règle déjà validée à la question 118 s'applique : la première photo restante devient automatiquement la photo principale ; si aucune photo ne reste, le produit ne dispose plus de photo principale.
  - Pour une photo de profil ou de présentation de coopérative, le média supprimé n'est plus utilisé dans l'emplacement concerné. Le comportement d'affichage en l'absence de photo reste à préciser.
  - Les contrôles d'autorisation, l'isolation multi-coopératives et la confirmation de suppression restent obligatoires.
- **Périmètre concerné :** domaine Media ; suppression de médias utilisés, retrait des associations, photos principales de produits, photos de profil et de présentation des coopératives.
- **Points restant à préciser :** affichage de remplacement lorsqu'une coopérative ne dispose plus de photo de profil ou de présentation ; gestion des références partagées et des échecs partiels de suppression.

### Question 126 — Affichage après suppression de la photo de coopérative (VALIDÉE)
- **Question :** Lorsqu'une coopérative supprime sa photo de profil ou de présentation sans en choisir une autre, que doit afficher MAHLINE ?
- **Choix proposés :**
  - A. Une image générique par défaut de MAHLINE.
  - B. Aucun emplacement photo : la zone reste vide.
  - C. La dernière ancienne photo conservée.
  - D. Un message invitant la coopérative à ajouter une nouvelle photo.
- **Réponse :** D.
- **Décision :** lorsqu'une coopérative ne dispose plus de photo de profil ou de présentation après une suppression, MAHLINE affiche un message invitant la coopérative à ajouter une nouvelle photo. Aucune ancienne photo supprimée n'est réaffichée automatiquement.
- **Conséquences :**
  - L'interface doit distinguer l'absence de photo de profil de l'absence de photo de présentation et proposer l'action d'ajout correspondante.
  - Le message d'invitation ne rend pas la photo obligatoire et ne bloque pas les autres fonctions de la coopérative, sauf règle métier distincte à définir.
  - Les règles de visibilité publique/privée déjà validées à la question 121 et la visibilité publique par défaut des nouvelles photos à la question 122 restent applicables.
- **Périmètre concerné :** domaine Media ; interface de gestion des photos de profil et de présentation des coopératives, état sans photo et ajout d'un nouveau média.
- **Points restant à préciser :** texte exact du message, affichage côté marketplace et éventuelle image de secours purement visuelle qui ne serait pas un média de la coopérative.

### Question 127 — Moment de suppression physique du fichier (VALIDÉE)
- **Question :** Après confirmation de suppression d'un média, à quel moment le fichier physique doit-il être supprimé du stockage ?
- **Choix proposés :**
  - A. Immédiatement après la confirmation.
  - B. Après vérification qu'aucune autre entité ne référence ce fichier.
  - C. Après 30 jours de conservation.
  - D. Le fichier est conservé jusqu'à une intervention manuelle du `super_admin_root`.
- **Réponse :** A.
- **Décision :** après confirmation de la suppression, MAHLINE supprime immédiatement le fichier physique du stockage ainsi que le média et ses associations conformément aux règles de suppression validées. Aucune période de conservation différée n'est prévue.
- **Conséquences :**
  - La suppression physique intervient immédiatement après la confirmation de l'utilisateur, sans mise en corbeille ni délai de rétention applicatif.
  - Les références et associations du média doivent être retirées de façon cohérente afin d'éviter les références orphelines.
  - Pour un média partagé, la règle déjà validée à la question 113 s'applique : la suppression confirmée retire toutes les références au média avant de supprimer le fichier ; les entités métier elles-mêmes ne sont pas supprimées.
  - L'opération doit rester autorisée selon le rôle et le périmètre de coopérative, confirmée et tracée.
- **Périmètre concerné :** domaine Media ; suppression logique/métadonnées, associations, fichier physique, stockage et médias éventuellement référencés par plusieurs entités.
- **Points restant à préciser :** comportement de reprise et de signalement si la suppression physique échoue après la confirmation ou si le stockage est temporairement indisponible.

### Question 128 — Échec de suppression physique du fichier (VALIDÉE)
- **Question :** Si l'utilisateur confirme la suppression, mais que le stockage rencontre une erreur et ne parvient pas à supprimer le fichier immédiatement, que doit faire MAHLINE ?
- **Choix proposés :**
  - A. Annuler toute l'opération et conserver le média comme avant.
  - B. Retirer immédiatement le média de l'application, enregistrer l'échec et réessayer automatiquement la suppression physique.
  - C. Laisser le média visible et demander à l'utilisateur de réessayer.
  - D. Signaler l'erreur au `super_admin_root`, qui effectuera la suppression manuellement.
- **Réponse :** C.
- **Décision :** en cas d'échec de suppression physique, MAHLINE conserve le média visible et signale l'échec à l'utilisateur afin qu'il puisse relancer lui-même la suppression.
- **Conséquences :**
  - Le média et ses références restent dans leur état antérieur tant que la suppression physique n'a pas abouti.
  - MAHLINE ne masque pas le média et ne retire pas ses associations à la suite d'une suppression physique échouée.
  - Aucune relance automatique ni intervention obligatoire du `super_admin_root` n'est prévue par cette décision.
  - Une nouvelle tentative doit être déclenchée par l'utilisateur, avec les contrôles d'autorisation et de confirmation applicables.
  - L'échec doit être communiqué clairement ; les détails techniques du stockage ne doivent pas être exposés à l'utilisateur.
- **Statut :** VALIDÉ — décision fonctionnelle enregistrée.
- **Périmètre concerné :** domaine Media ; suppression des métadonnées et associations, stockage physique, gestion des erreurs et interface utilisateur.
- **Points restant à préciser :** modalités techniques de conservation cohérente de l'état et de signalement de l'erreur pendant l'opération de suppression.

### Question 129 — Traçabilité des échecs de suppression (VALIDÉE)
- **Question :** Lorsqu'une suppression physique échoue, quelle trace MAHLINE doit-il conserver et quelle information présenter à l'utilisateur ?
- **Choix proposés :**
  - A. Enregistrer l'échec dans le journal d'audit, consultable uniquement par `super_admin_root`.
  - B. Enregistrer l'erreur uniquement dans les journaux techniques internes.
  - C. Enregistrer l'échec dans les deux : journal d'audit et journaux techniques.
  - D. Ne conserver aucune trace ; afficher simplement un message à l'utilisateur.
- **Réponse :** A, avec affichage d'un simple message à l'utilisateur.
- **Décision :** chaque échec de suppression physique est enregistré dans le journal d'audit, dont la consultation est réservée au `super_admin_root`. L'utilisateur reçoit uniquement un message simple l'informant que la suppression a échoué et qu'il peut réessayer.
- **Conséquences :**
  - L'événement d'échec est traçable dans le journal d'audit et accessible uniquement au `super_admin_root`.
  - L'interface ne présente pas à l'utilisateur les détails techniques de l'erreur ; elle affiche un message simple et compréhensible.
  - Cette décision ne prévoit pas l'enregistrement supplémentaire dans les journaux techniques internes.
  - Le média reste visible et ses références sont conservées conformément à la décision de la question 128, jusqu'à une nouvelle tentative réussie.
- **Statut :** VALIDÉ — décision fonctionnelle enregistrée.
- **Périmètre concerné :** domaine Media ; journal d'audit, autorisations de consultation, gestion des erreurs de stockage et messages utilisateur.
- **Points restant à préciser :** contenu exact de l'événement d'audit et formulation du message utilisateur lors de la conception technique et UX.

### Question 130 — Autorisation d'une nouvelle tentative de suppression (VALIDÉE)
- **Question :** Après un échec de suppression physique, qui peut relancer la suppression du média ?
- **Choix proposés :**
  - A. Uniquement l'utilisateur qui avait demandé la suppression initiale.
  - B. Tout utilisateur disposant du droit de supprimer ce média.
  - C. Uniquement le `super_admin_root`.
  - D. Le système relance automatiquement la suppression en arrière-plan.
- **Réponse :** A et B.
- **Décision :** une nouvelle tentative peut être déclenchée soit par l'utilisateur ayant demandé la suppression initiale, soit par tout autre utilisateur disposant du droit de supprimer le média concerné.
- **Conséquences :**
  - Le demandeur initial peut relancer l'opération.
  - Un autre utilisateur peut également la relancer s'il dispose du droit de suppression sur ce média et dans le périmètre de coopérative applicable.
  - Les autorisations doivent être vérifiées à nouveau au moment de chaque tentative ; la demande initiale ne confère aucun droit supplémentaire.
  - La nouvelle tentative reste soumise aux règles de confirmation, de traçabilité et de gestion d'échec définies aux questions 127 à 129.
  - Aucune relance automatique en arrière-plan n'est prévue par cette décision.
- **Statut :** VALIDÉ — décision fonctionnelle enregistrée.
- **Périmètre concerné :** domaine Media ; autorisations de suppression, périmètre coopératif, nouvelle tentative et traçabilité.
- **Points restant à préciser :** aucun point fonctionnel supplémentaire identifié pour cette règle.

### Question 131 — Confirmation lors d'une nouvelle tentative de suppression (VALIDÉE)
- **Question :** Lorsqu'une suppression physique a déjà échoué, faut-il demander une nouvelle confirmation avant chaque tentative ?
- **Choix proposés :**
  - A. Oui, une confirmation est obligatoire à chaque nouvelle tentative.
  - B. Non, la confirmation initiale reste valable pour les tentatives suivantes.
  - C. Seul le nouvel utilisateur qui reprend la suppression doit confirmer.
  - D. La confirmation dépend du type de média.
- **Réponse :** A.
- **Décision :** chaque nouvelle tentative de suppression physique doit être précédée d'une nouvelle confirmation explicite de l'utilisateur qui déclenche cette tentative.
- **Conséquences :**
  - La confirmation initiale ne vaut que pour la tentative initiale.
  - Toute personne autorisée qui relance la suppression, y compris le demandeur initial, doit confirmer à nouveau.
  - Les droits de suppression et le périmètre coopératif sont revérifiés à chaque tentative, conformément à la question 130.
  - L'échec reste tracé dans le journal d'audit accessible uniquement au `super_admin_root`, tandis que l'utilisateur reçoit un message simple, conformément à la question 129.
- **Statut :** VALIDÉ — décision fonctionnelle enregistrée.
- **Périmètre concerné :** domaine Media ; confirmation, autorisations, nouvelle tentative et traçabilité de suppression.
- **Points restant à préciser :** aucun point fonctionnel supplémentaire identifié pour cette règle.

### Question 132 — Blocage temporaire après des échecs répétés de suppression (VALIDÉE)
- **Question :** Si plusieurs tentatives de suppression physique échouent successivement, quelle règle appliquer ?
- **Choix proposés :**
  - A. Autoriser autant de nouvelles tentatives que nécessaire.
  - B. Bloquer après 3 échecs et demander l'intervention du `super_admin_root`.
  - C. Bloquer après 5 échecs et demander l'intervention du `super_admin_root`.
  - D. Bloquer temporairement, puis autoriser une nouvelle tentative après un délai.
- **Réponse :** D.
- **Décision :** après plusieurs échecs successifs, MAHLINE bloque temporairement les nouvelles tentatives de suppression physique ; une nouvelle tentative redevient possible après expiration du délai de blocage.
- **Conséquences :**
  - Le blocage est temporaire et ne constitue pas une suppression définitive du média.
  - À l'expiration du délai, une nouvelle tentative peut être déclenchée par un utilisateur autorisé, sous réserve d'une nouvelle confirmation (question 131) et d'une nouvelle vérification des permissions (question 130).
  - Les échecs restent inscrits dans le journal d'audit accessible uniquement au `super_admin_root` (question 129) ; l'utilisateur reçoit un message simple.
- **Statut :** VALIDÉ — principe fonctionnel enregistré.
- **Périmètre concerné :** domaine Media ; gestion des échecs répétés, blocage temporaire, autorisations et confirmation.
- **Points restant à préciser :** seuil exact d'échecs successifs déclenchant le blocage, durée du blocage et éventuelle progression du délai à définir avant ou pendant l'audit technique, sans inventer de valeurs.

### Question 133 — Seuil de blocage après échecs successifs de suppression (VALIDÉE)
- **Question :** À partir de combien d'échecs successifs faut-il déclencher le blocage temporaire ?
- **Choix proposés :**
  - A. Après 2 échecs.
  - B. Après 3 échecs.
  - C. Après 5 échecs.
  - D. Après 10 échecs.
- **Réponse :** A.
- **Décision :** MAHLINE déclenche le blocage temporaire des nouvelles tentatives de suppression physique après 2 échecs successifs.
- **Conséquences :**
  - Le seuil de déclenchement est fixé à 2 échecs successifs.
  - Après le blocage, une nouvelle tentative ne peut être effectuée qu'après expiration du délai qui reste à définir (question 132).
  - Chaque nouvelle tentative reste soumise à une confirmation explicite (question 131) et à une vérification des autorisations (question 130).
  - Les échecs sont consignés dans le journal d'audit accessible uniquement au `super_admin_root` ; l'utilisateur reçoit un message simple (question 129).
- **Statut :** VALIDÉ — seuil fonctionnel enregistré.
- **Périmètre concerné :** domaine Media ; suppression physique, gestion des échecs et blocage temporaire.
- **Points restant à préciser :** durée du blocage et éventuelle progression du délai.

### Question 134 — Durée du blocage temporaire après échecs de suppression (VALIDÉE)
- **Question :** Après les 2 échecs successifs, combien de temps MAHLINE doit-il bloquer les nouvelles tentatives ?
- **Choix proposés :**
  - A. 5 minutes.
  - B. 15 minutes.
  - C. 30 minutes.
  - D. 1 heure.
  - E. 24 heures.
- **Réponse :** A.
- **Décision :** après 2 échecs successifs de suppression physique, MAHLINE bloque les nouvelles tentatives pendant 5 minutes.
- **Conséquences :**
  - Le seuil de déclenchement est de 2 échecs successifs (question 133).
  - La durée du blocage est fixée à 5 minutes.
  - À l'expiration du délai, une nouvelle tentative peut être déclenchée par un utilisateur autorisé, avec une nouvelle confirmation et une nouvelle vérification des permissions (questions 130 et 131).
  - Les échecs restent consignés dans le journal d'audit accessible uniquement au `super_admin_root`, et l'utilisateur reçoit un message simple (question 129).
- **Statut :** VALIDÉ — seuil et durée du blocage définis.
- **Périmètre concerné :** domaine Media ; suppression physique, gestion des échecs et blocage temporaire.
- **Points restant à préciser :** déterminer si le délai reste fixe ou augmente après de nouveaux cycles d'échecs.

### Question 135 — Progression du délai de blocage après des échecs répétés (VALIDÉE)
- **Question :** Si la suppression échoue encore après la période de blocage de 5 minutes, quelle règle appliquer ?
- **Choix proposés :**
  - A. Garder toujours un blocage fixe de 5 minutes après chaque série de 2 échecs.
  - B. Augmenter progressivement le délai : 5, 15, 30 puis 60 minutes.
  - C. Après le deuxième blocage, demander l'intervention du `super_admin_root`.
  - D. Bloquer définitivement la suppression du média.
- **Réponse :** B.
- **Décision :** le délai de blocage augmente progressivement selon la séquence de 5, 15, 30 puis 60 minutes lorsque les échecs de suppression physique se répètent.
- **Conséquences :**
  - Le premier blocage dure 5 minutes après 2 échecs successifs (questions 133 et 134).
  - Lors des cycles d'échecs ultérieurs, la durée progresse selon la séquence définie : 15, puis 30, puis 60 minutes.
  - Le blocage reste temporaire ; aucune suppression définitive ni intervention obligatoire du `super_admin_root` n'est déclenchée par cette seule règle.
  - Toute nouvelle tentative reste soumise à une confirmation explicite et à une vérification des autorisations (questions 130 et 131).
  - Les échecs restent consignés dans le journal d'audit accessible uniquement au `super_admin_root` ; l'utilisateur reçoit un message simple (question 129).
- **Statut :** VALIDÉ — progression du délai définie.
- **Périmètre concerné :** domaine Media ; suppression physique, gestion des échecs répétés et blocage temporaire progressif.
- **Points restant à préciser :** aucun point fonctionnel supplémentaire identifié pour la séquence des délais ; les modalités de remise à zéro du compteur restent à cadrer si nécessaire.

### Question 136 — Réinitialisation du compteur d'échecs de suppression (VALIDÉE)
- **Question :** Quand faut-il remettre à zéro le compteur des échecs et des blocages ?
- **Choix proposés :**
  - A. Dès qu'une suppression physique réussit.
  - B. Après 24 heures sans nouvel échec.
  - C. Après 7 jours sans nouvel échec.
  - D. Uniquement sur intervention du `super_admin_root`.
- **Réponse :** A.
- **Décision :** le compteur des échecs et des blocages est remis à zéro dès qu'une suppression physique réussit.
- **Conséquences :**
  - Une suppression physique réussie réinitialise le compteur et la progression des délais de blocage.
  - Si une nouvelle série d'échecs survient ensuite, la progression des blocages repart du premier délai de 5 minutes, après 2 échecs successifs.
  - Les entrées historiques du journal d'audit ne sont pas supprimées par cette réinitialisation.
- **Statut :** VALIDÉ — règle de réinitialisation enregistrée.
- **Périmètre concerné :** domaine Media ; suppression physique, compteur d'échecs et blocage progressif.
- **Points restant à préciser :** aucun point fonctionnel supplémentaire identifié pour la réinitialisation.

### Question 137 — Notification du super_admin_root après un blocage de 60 minutes (VALIDÉE)
- **Question :** Lorsqu'un média subit plusieurs échecs de suppression et atteint le blocage de 60 minutes, faut-il notifier le `super_admin_root` ?
- **Choix proposés :**
  - A. Oui, notification automatique dès le premier blocage de 60 minutes.
  - B. Oui, mais uniquement après plusieurs blocages de 60 minutes.
  - C. Non, les événements restent uniquement dans le journal d'audit.
  - D. Oui, notification à chaque échec, quel que soit le délai.
- **Réponse :** A.
- **Décision :** une notification automatique est envoyée au `super_admin_root` dès qu'un média atteint pour la première fois un blocage de 60 minutes à la suite d'échecs répétés de suppression physique.
- **Conséquences :**
  - Le déclencheur de notification est le premier passage au palier de blocage de 60 minutes.
  - Les échecs et blocages restent consignés dans le journal d'audit accessible uniquement au `super_admin_root`.
  - Cette notification ne transforme pas le blocage temporaire en blocage définitif et ne modifie pas les autorisations de suppression.
- **Statut :** VALIDÉ — notification automatique enregistrée.
- **Périmètre concerné :** domaine Media ; suppression physique, blocage progressif et notification administrative.
- **Points restant à préciser :** canal de notification et contenu exact du message à définir lors du cadrage des notifications et de l'UX.

### Question 138 — Contenu de la notification de blocage de 60 minutes (VALIDÉE)
- **Question :** Quelles informations doivent apparaître dans la notification adressée au `super_admin_root` ?
- **Choix proposés :**
  - A. Nom du média, coopérative concernée et nombre d'échecs.
  - B. Nom du média, coopérative, utilisateur ayant demandé la suppression, nombre d'échecs et date du dernier échec.
  - C. Toutes les informations de B, avec le motif technique de l'échec.
  - D. Un simple message indiquant qu'une suppression est bloquée pendant 60 minutes.
- **Réponse :** C.
- **Décision :** la notification adressée au `super_admin_root` contient le nom du média, la coopérative concernée, l'utilisateur ayant demandé la suppression, le nombre d'échecs, la date du dernier échec ainsi que le motif technique de l'échec.
- **Conséquences :**
  - La notification fournit les éléments nécessaires pour identifier le média et le contexte de la suppression bloquée.
  - Le motif technique doit être présenté sans exposer de secrets, jetons, identifiants sensibles ou données personnelles non nécessaires.
  - Les détails complets de l'incident restent consultables dans le journal d'audit réservé au `super_admin_root`.
- **Statut :** VALIDÉ — contenu fonctionnel de la notification enregistré.
- **Périmètre concerné :** domaine Media ; notification administrative, échecs de suppression et audit.
- **Points restant à préciser :** format de présentation et canal de notification à définir lors du cadrage UX/notifications.

### Question 139 — Canaux de notification du blocage de suppression (VALIDÉE)
- **Question :** Comment le `super_admin_root` doit-il recevoir la notification de blocage de 60 minutes ?
- **Choix proposés :**
  - A. Notification dans l'interface MAHLINE uniquement.
  - B. Email uniquement.
  - C. Notification dans MAHLINE et email.
  - D. Notification dans MAHLINE, email et SMS.
- **Réponse :** C.
- **Décision :** le `super_admin_root` reçoit la notification à la fois dans l'interface MAHLINE et par email lorsqu'un média atteint pour la première fois le blocage de 60 minutes.
- **Conséquences :**
  - La notification interne MAHLINE et l'email utilisent le contenu défini à la question 138.
  - Aucun SMS n'est prévu par cette décision.
  - Les règles de sécurité et de confidentialité des informations de notification restent applicables.
- **Statut :** VALIDÉ — canaux de notification enregistrés.
- **Périmètre concerné :** domaine Media ; notification administrative, blocage progressif et communication.
- **Points restant à préciser :** modèle de l'email, présentation de la notification interne et comportement en cas d'échec d'envoi à préciser lors du cadrage UX/notifications et de l'audit technique.

### Question 140 — Échec d’envoi de l’email de notification (VALIDÉE)
- **Question :** Si l’email destiné au `super_admin_root` échoue alors que la notification interne MAHLINE a bien été créée, quel comportement appliquer ?
- **Choix proposés :**
  - A. Conserver la notification interne et ne pas réessayer l’email.
  - B. Conserver la notification interne et réessayer automatiquement l’email.
  - C. Conserver la notification interne et permettre au `super_admin_root` de relancer manuellement l’envoi.
  - D. Combiner B et C : réessais automatiques et relance manuelle.
- **Réponse :** A.
- **Décision :** en cas d’échec d’envoi de l’email, conserver la notification interne MAHLINE et ne pas effectuer de nouvel envoi automatique ni proposer de relance manuelle.
- **Conséquences :**
  - La notification interne reste disponible pour le `super_admin_root`.
  - L’échec d’envoi doit être journalisé et rester consultable selon les règles d’audit réservées au `super_admin_root`.
  - Aucun mécanisme de réessai ou de relance manuelle n’est prévu par cette décision.
- **Statut :** VALIDÉ — comportement en cas d’échec email enregistré.
- **Périmètre concerné :** domaine Media ; notification administrative et gestion des erreurs d’envoi.
- **Points restant à préciser :** présentation de l’échec dans l’interface et détail de la journalisation à vérifier lors de l’audit UX/technique.

### Question 141 — Durée de conservation des notifications internes (VALIDÉE)
- **Question :** Combien de temps la notification interne relative au blocage de suppression doit-elle rester consultable par le `super_admin_root` ?
- **Choix proposés :**
  - A. 30 jours.
  - B. 90 jours.
  - C. 1 an.
  - D. Sans limite de durée, jusqu’à suppression manuelle.
- **Réponse :** D.
- **Décision :** la notification interne reste consultable sans limite de durée et n’est supprimée que manuellement.
- **Conséquences :**
  - Aucune expiration automatique de cette notification n’est appliquée.
  - La suppression manuelle doit rester réservée au rôle autorisé et être contrôlée/auditée.
  - La durée de conservation des traces du journal d’audit reste distincte et n’est pas modifiée par cette décision.
- **Statut :** VALIDÉ — durée de conservation des notifications enregistrée.
- **Périmètre concerné :** domaine Media ; notifications administratives et conservation.
- **Points restant à préciser :** rôle exact autorisé à supprimer une notification et modalités de suppression à confirmer lors du cadrage des notifications.

### Question 142 — Droit de suppression des notifications (VALIDÉE)
- **Question :** Quels rôles peuvent supprimer les notifications auxquelles ils ont accès ?
- **Choix proposés :**
  - A. Seul le `super_admin_root`.
  - B. Le `super_admin_root` et le `super_admin`.
  - C. Chaque rôle peut supprimer les notifications auxquelles il a accès.
  - D. Aucun rôle : les notifications ne peuvent pas être supprimées.
- **Réponse :** C — précision explicite de l’utilisateur : chaque rôle a le droit de supprimer ces notifications.
- **Décision :** chaque rôle peut supprimer les notifications qui lui sont accessibles. Ce droit ne donne pas accès aux notifications d’un autre rôle ou utilisateur.
- **Conséquences :**
  - La suppression est limitée aux notifications visibles/autorisées pour le rôle et le compte concernés.
  - La suppression d’une notification ne supprime pas les traces correspondantes du journal d’audit.
  - Les contrôles d’accès doivent être appliqués côté serveur.
- **Statut :** VALIDÉ — droit de suppression par chaque rôle sur ses notifications accessibles.
- **Périmètre concerné :** domaine Media ; notifications et autorisations par rôle.
- **Points restant à préciser :** distinguer, lors de l’implémentation, la suppression individuelle de l’archivage éventuel et confirmer si une action « tout supprimer » est souhaitée.

### Question 143 — Effet de la suppression d’une notification (VALIDÉE)
- **Question :** Lorsqu’un utilisateur supprime une notification, quel effet appliquer ?
- **Choix proposés :**
  - A. Suppression immédiate et définitive.
  - B. Marquer comme supprimée tout en la conservant temporairement en base.
  - C. Déplacer dans une corbeille avec possibilité de restauration.
  - D. Masquer uniquement dans l’interface de l’utilisateur, sans suppression en base.
- **Réponse :** D.
- **Décision :** la suppression demandée par un utilisateur masque la notification uniquement dans son interface ; l’enregistrement reste conservé en base.
- **Conséquences :**
  - L’action est individuelle à l’utilisateur : masquer une notification ne la masque pas pour les autres utilisateurs qui y ont accès.
  - La notification reste conservée en base et aucune suppression physique n’est effectuée par cette action.
  - Les traces d’audit restent conservées.
- **Statut :** VALIDÉ — suppression assimilée à un masquage individuel.
- **Périmètre concerné :** domaine Media ; notifications et préférences d’affichage par utilisateur.
- **Points restant à préciser :** durée de conservation technique et éventuelle fonction de réaffichage à préciser lors de l’audit technique/UX.

### Question 144 — Annulation du masquage d’une notification (VALIDÉE)
- **Question :** Comment permettre à l’utilisateur de réafficher une notification qu’il vient de masquer ?
- **Choix proposés :**
  - A. Une rubrique « Notifications masquées ».
  - B. Un bouton « Annuler » disponible juste après le masquage.
  - C. Les deux possibilités.
  - D. Aucun réaffichage possible.
- **Réponse :** B.
- **Décision :** après le masquage d’une notification, proposer à l’utilisateur un bouton « Annuler » lui permettant de rétablir immédiatement son affichage.
- **Conséquences :**
  - Le rétablissement annule le masquage individuel et rend à nouveau la notification visible pour cet utilisateur.
  - Aucune rubrique permanente « Notifications masquées » n’est prévue par cette décision.
  - La notification et ses traces d’audit restent conservées en base.
- **Statut :** VALIDÉ — annulation immédiate du masquage.
- **Périmètre concerné :** domaine Media ; interface de notifications et préférences d’affichage par utilisateur.
- **Points restant à préciser :** durée pendant laquelle le bouton « Annuler » reste disponible et comportement après navigation/rechargement à préciser lors du cadrage UX/technique.

### Question 145 — Durée du bouton « Annuler » (VALIDÉE)
- **Question :** Combien de temps le bouton « Annuler » doit-il rester disponible après le masquage d’une notification ?
- **Choix proposés :**
  - A. 5 secondes.
  - B. 10 secondes.
  - C. 30 secondes.
  - D. 60 secondes.
- **Réponse :** C.
- **Décision :** le bouton « Annuler » reste disponible pendant 30 secondes après le masquage de la notification.
- **Conséquences :**
  - Pendant cette fenêtre, l’utilisateur peut annuler le masquage et rétablir l’affichage de la notification.
  - À l’expiration des 30 secondes, le bouton disparaît ; la notification reste conservée en base mais masquée pour cet utilisateur.
  - Cette décision ne crée pas de rubrique permanente de notifications masquées.
- **Statut :** VALIDÉ — fenêtre d’annulation de 30 secondes.
- **Périmètre concerné :** domaine Media ; interface de notifications et préférences d’affichage par utilisateur.
- **Points restant à préciser :** comportement en cas de navigation ou de rechargement de page pendant les 30 secondes à vérifier lors du cadrage UX/technique.

### Question 146 — Notifications après reconnexion (VALIDÉE)
- **Question :** Lorsqu’un utilisateur se déconnecte puis se reconnecte, que doit-il se passer avec ses notifications non masquées ?
- **Choix proposés :**
  - A. Toutes les notifications non lues restent affichées.
  - B. Seules les notifications des 30 derniers jours restent affichées.
  - C. Toutes les notifications non masquées restent accessibles, sans limite de durée.
  - D. Les notifications sont réinitialisées à chaque déconnexion.
- **Réponse :** A.
- **Décision :** après reconnexion, toutes les notifications non lues restent affichées à l’utilisateur.
- **Conséquences :**
  - La déconnexion ne réinitialise pas les notifications non lues.
  - Les notifications lues ne sont pas concernées par cette règle d’affichage.
  - Le masquage individuel défini aux questions 143 à 145 reste applicable.
- **Statut :** VALIDÉ — conservation des notifications non lues après reconnexion.
- **Périmètre concerné :** domaine Media ; notifications et cycle de session utilisateur.
- **Points restant à préciser :** durée de conservation et consultation des notifications déjà lues à cadrer séparément si nécessaire.

### Question 147 — Marquage individuel et global des notifications comme lues (VALIDÉE)
- **Question :** Comment marquer les notifications comme lues ?
- **Choix proposés :**
  - A. Automatiquement dès que l’utilisateur ouvre la notification.
  - B. Automatiquement dès qu’elle apparaît à l’écran.
  - C. Manuellement, avec un bouton « Marquer comme lu ».
  - D. Automatiquement à l’ouverture et manuellement depuis la liste.
- **Réponse :** A, avec une précision complémentaire explicite : ajouter un bouton « Marquer comme lu » pour toutes les notifications.
- **Décision :** une notification est automatiquement marquée comme lue dès que l’utilisateur l’ouvre. Ajouter également une action permettant de marquer toutes ses notifications comme lues en une seule opération.
- **Conséquences :**
  - L’ouverture d’une notification déclenche son passage à l’état lu.
  - L’action globale ne concerne que les notifications accessibles à l’utilisateur connecté et encore non lues.
  - Cette action ne modifie pas les notifications des autres utilisateurs.
  - Le masquage individuel reste distinct du statut lu/non lu.
- **Statut :** VALIDÉ — lecture automatique à l’ouverture et action globale « Marquer tout comme lu ».
- **Périmètre concerné :** domaine Media ; centre de notifications et états de lecture par utilisateur.
- **Points restant à préciser :** libellé exact, confirmation éventuelle et comportement si la liste contient un grand nombre de notifications à vérifier lors du cadrage UX/technique.

### Question 148 — Indicateur du nombre de notifications non lues (VALIDÉE)
- **Question :** Comment afficher le nombre de notifications non lues sur l’icône de notification ?
- **Choix proposés :**
  - A. Un compteur numérique sur l’icône.
  - B. Un point rouge sans nombre.
  - C. Un compteur numérique et un point visuel.
  - D. Aucun indicateur.
- **Réponse :** Précision explicite : afficher un rond rouge dans un coin de l’icône de notification, contenant le nombre de notifications non lues.
- **Décision :** afficher sur un coin de l’icône de notification un badge rond rouge avec le nombre de notifications non lues de l’utilisateur.
- **Conséquences :**
  - Le nombre affiché reflète les notifications non lues accessibles à l’utilisateur connecté.
  - Le compteur se met à jour lorsqu’une notification est lue, masquée ou lorsqu’une nouvelle notification est reçue, selon les règles d’affichage validées.
  - Le masquage d’une notification ne doit pas être confondu avec son marquage comme lu ; le calcul du compteur doit respecter séparément ces deux états.
- **Statut :** VALIDÉ — compteur numérique dans un rond rouge positionné sur le coin de l’icône.
- **Périmètre concerné :** domaine Media ; centre de notifications et indicateur global de lecture.
- **Points restant à préciser :** présentation du compteur pour les valeurs élevées (ex. 99+) et mise à jour temps réel ou au rafraîchissement à préciser lors du cadrage UX/technique.

### Question 149 — Mise à jour du compteur de notifications (VALIDÉE)
- **Question :** Quand une nouvelle notification arrive, comment actualiser le compteur rouge ?
- **Choix proposés :**
  - A. Instantanément, sans recharger la page (temps réel).
  - B. Toutes les 30 secondes.
  - C. Toutes les minutes.
  - D. Uniquement lorsque l’utilisateur actualise la page.
- **Réponse :** D.
- **Décision :** le compteur rouge du nombre de notifications non lues est actualisé uniquement lorsque l’utilisateur actualise/recharge la page.
- **Conséquences :**
  - Aucune mise à jour automatique périodique ou en temps réel n’est requise pour le premier release.
  - Après réception d’une nouvelle notification, le compteur peut rester inchangé jusqu’au prochain rechargement de page.
  - Les changements d’état effectués dans l’interface doivent néanmoins être reflétés selon les interactions et le rafraîchissement déjà prévus pour les notifications.
- **Statut :** VALIDÉ — actualisation du compteur au rechargement de la page uniquement.
- **Périmètre concerné :** domaine Media ; centre de notifications et badge global.
- **Points restant à préciser :** aucun pour la fréquence d’actualisation du compteur.
 
### Question 150 — Ordre d’affichage des notifications (VALIDÉE)
- **Question :** Dans le centre de notifications, dans quel ordre afficher les notifications ?
- **Choix proposés :**
  - A. Les plus récentes en premier.
  - B. Les plus anciennes en premier.
  - C. Les notifications non lues d’abord, puis les notifications lues, avec les plus récentes en premier dans chaque groupe.
  - D. Les notifications importantes d’abord, puis les autres.
- **Réponse :** A.
- **Décision :** afficher les notifications par ordre chronologique décroissant, avec les plus récentes en premier.
- **Conséquences :**
  - L’ordre d’affichage repose sur la date de création/réception de la notification.
  - Le statut lu/non lu ne modifie pas la priorité de tri.
  - La règle s’applique au centre de notifications de chaque utilisateur, dans le respect des règles d’accès et de visibilité.
- **Statut :** VALIDÉ — notifications les plus récentes en premier.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** filtres et regroupements éventuels du centre de notifications.
 
### Question 151 — Filtres du centre de notifications (VALIDÉE)
- **Question :** Souhaites-tu permettre à l’utilisateur de filtrer ses notifications ?
- **Choix proposés :**
  - A. Toutes / Non lues / Lues.
  - B. Toutes / Non lues uniquement.
  - C. Par catégorie de notification (produit, commande, média, compte, sécurité, etc.).
  - D. A + C : filtres de lecture et de catégorie.
  - E. Aucun filtre.
- **Réponse :** D.
- **Décision :** proposer les filtres de statut (Toutes, Non lues, Lues) ainsi qu'un filtre par catégorie de notification.
- **Conséquences :**
  - Les filtres de statut et de catégorie peuvent être combinés.
  - Les catégories affichées doivent correspondre aux types de notifications réellement accessibles à l'utilisateur.
  - Les filtres ne modifient ni le statut de lecture ni les droits d'accès aux notifications.
  - Le tri chronologique décroissant validé en Q150 reste appliqué aux résultats filtrés.
- **Statut :** VALIDÉ — filtres de lecture et de catégorie.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** liste exhaustive des catégories et comportement de sélection multiple.
 
### Question 152 — Sélection des catégories de notifications (VALIDÉE)
- **Question :** Comment l’utilisateur doit-il sélectionner les catégories de notifications ?
- **Choix proposés :**
  - A. Une seule catégorie à la fois.
  - B. Plusieurs catégories simultanément.
  - C. Une catégorie à la fois, avec une option « Toutes les catégories ».
  - D. Plusieurs catégories simultanément, avec une option « Toutes les catégories ».
- **Réponse :** D.
- **Décision :** autoriser la sélection simultanée de plusieurs catégories et proposer une option « Toutes les catégories ».
- **Conséquences :**
  - L’utilisateur peut combiner plusieurs catégories dans un même filtre.
  - « Toutes les catégories » réinitialise le filtre de catégorie et inclut toutes les catégories auxquelles l’utilisateur a accès.
  - Le filtre de statut (Toutes / Non lues / Lues) reste combinable avec le filtre de catégorie.
  - Les contrôles d'accès restent appliqués côté serveur, indépendamment des filtres choisis.
- **Statut :** VALIDÉ — sélection multiple et option « Toutes les catégories ».
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** présentation visuelle des filtres et conservation éventuelle des préférences.
 
### Question 153 — Conservation des filtres du centre de notifications (VALIDÉE)
- **Question :** Lorsque l’utilisateur quitte le centre de notifications puis y revient, comment gérer ses derniers filtres ?
- **Choix proposés :**
  - A. Réinitialiser les filtres à chaque ouverture.
  - B. Conserver les derniers filtres uniquement pendant la session.
  - C. Enregistrer les préférences et les conserver lors des prochaines connexions.
  - D. Laisser l’utilisateur choisir de mémoriser ou non ses filtres.
- **Réponse :** B.
- **Décision :** conserver les derniers filtres sélectionnés uniquement pendant la session en cours.
- **Conséquences :**
  - Un retour au centre de notifications pendant la même session conserve les filtres précédemment sélectionnés.
  - À la fin de la session ou lors d'une nouvelle connexion, les filtres sont réinitialisés aux valeurs par défaut.
  - Aucune préférence de filtre persistante n'est enregistrée dans le profil utilisateur.
- **Statut :** VALIDÉ — conservation limitée à la session.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** valeurs exactes des filtres par défaut.
 
### Question 154 — Valeurs des filtres par défaut (VALIDÉE)
- **Question :** À chaque nouvelle connexion, quels filtres doivent être appliqués par défaut ?
- **Choix proposés :**
  - A. Toutes les notifications, toutes les catégories.
  - B. Notifications non lues, toutes les catégories.
  - C. Toutes les notifications, aucune catégorie sélectionnée jusqu'au choix de l'utilisateur.
  - D. Notifications non lues, sans catégorie sélectionnée jusqu'au choix de l'utilisateur.
- **Réponse :** D.
- **Décision :** à chaque nouvelle session, afficher par défaut les notifications non lues et ne présélectionner aucune catégorie spécifique.
- **Conséquences :**
  - Le filtre de statut initial est « Non lues ».
  - Le filtre de catégorie initial est neutre : aucune catégorie n'est sélectionnée, ce qui signifie qu'aucune restriction par catégorie n'est appliquée.
  - Pendant la session, les sélections de filtres restent conservées conformément à Q153.
  - À la session suivante, les filtres reviennent à ces valeurs par défaut.
- **Statut :** VALIDÉ — « Non lues » par défaut et toutes catégories incluses.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** éventuel compteur/pagination des résultats filtrés.
 
### Question 155 — Chargement progressif des notifications (VALIDÉE)
- **Question :** Comment afficher les notifications lorsque leur nombre devient important ?
- **Choix proposés :**
  - A. Tout afficher sur une seule page.
  - B. Afficher 20 notifications par page avec pagination.
  - C. Afficher 50 notifications par page avec pagination.
  - D. Charger progressivement les notifications en faisant défiler la page (défilement infini).
- **Réponse :** D.
- **Décision :** utiliser un défilement infini pour charger progressivement les notifications au fur et à mesure que l'utilisateur descend dans la liste.
- **Conséquences :**
  - Le chargement initial et chaque chargement complémentaire doivent rester limités afin d'éviter de récupérer toutes les notifications en une seule fois.
  - Le tri chronologique décroissant validé en Q150 et les filtres validés en Q151 à Q154 doivent être respectés à chaque chargement.
  - Les notifications déjà affichées ne doivent pas être dupliquées lors du chargement des éléments suivants.
  - Les contrôles d'accès et de visibilité restent appliqués côté serveur.
- **Statut :** VALIDÉ — défilement infini avec chargement progressif.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** taille du lot initial et des lots suivants, ainsi que l'indicateur de chargement et le comportement en cas d'erreur.
 
### Question 156 — Taille des lots de notifications (VALIDÉE)
- **Question :** Combien de notifications charger à chaque étape du défilement infini ?
- **Choix proposés :**
  - A. 10 notifications.
  - B. 20 notifications.
  - C. 30 notifications.
  - D. 50 notifications.
- **Réponse :** A.
- **Décision :** charger 10 notifications par lot, y compris le lot initial.
- **Conséquences :**
  - Le chargement initial récupère au maximum 10 notifications.
  - Chaque chargement complémentaire récupère au maximum 10 notifications supplémentaires.
  - Le défilement infini conserve le tri chronologique décroissant, les filtres actifs et les contrôles d'accès.
  - Les éléments déjà chargés ne doivent pas être dupliqués.
- **Statut :** VALIDÉ — lots de 10 notifications.
- **Périmètre concerné :** domaine Media ; centre de notifications.
- **Points restant à préciser :** présentation de l'indicateur de chargement et comportement en cas d'erreur.
 
### Question 157 — Indicateur de chargement des notifications (VALIDÉE)
- **Question :** Lorsqu’un nouveau lot de 10 notifications est en cours de chargement, que doit voir l’utilisateur ?
- **Choix proposés :**
  - A. Un petit indicateur animé de chargement.
  - B. Le texte « Chargement des notifications… ».
  - C. Un indicateur animé accompagné du texte.
  - D. Aucun indicateur : chargement silencieux.
- **Réponse :** A.
- **Décision :** afficher un petit indicateur animé pendant le chargement de chaque lot complémentaire.
- **Conséquences :**
  - L'indicateur apparaît uniquement pendant une opération de chargement en cours.
  - Il disparaît dès que le chargement se termine.
  - L'indicateur ne remplace pas les notifications déjà affichées et ne bloque pas leur consultation.
- **Statut :** VALIDÉ — petit indicateur animé, sans texte.
- **Périmètre concerné :** domaine Media ; centre de notifications et défilement infini.
- **Points restant à préciser :** comportement en cas d'erreur de chargement et message de fin de liste.
 
### Question 158 — Échec du chargement des notifications (VALIDÉE)
- **Question :** Si le chargement d’un lot de notifications échoue à cause d’un problème réseau ou serveur, que doit-il se passer ?
- **Choix proposés :**
  - A. Afficher un message simple et un bouton « Réessayer ».
  - B. Réessayer automatiquement jusqu’à réussite.
  - C. Afficher un message, sans possibilité de réessayer.
  - D. Ignorer l’erreur et continuer silencieusement.
- **Réponse :** A.
- **Décision :** en cas d'échec du chargement d'un lot, afficher un message simple à l'utilisateur et un bouton « Réessayer ».
- **Conséquences :**
  - Aucun nouvel essai automatique n'est lancé.
  - L'utilisateur déclenche explicitement une nouvelle tentative via « Réessayer ».
  - Les notifications déjà affichées restent visibles et consultables.
  - Le nouvel essai porte sur le lot qui a échoué, sans dupliquer les notifications déjà chargées.
  - Ne pas exposer les détails techniques ou sensibles de l'erreur à l'utilisateur.
- **Statut :** VALIDÉ — message simple et bouton « Réessayer ».
- **Périmètre concerné :** domaine Media ; centre de notifications et défilement infini.
- **Points restant à préciser :** formulation du message et comportement lorsque la liste est entièrement chargée.
 
### Question 159 — Fin de la liste des notifications (VALIDÉE)
- **Question :** Lorsque toutes les notifications ont été chargées, que doit afficher le centre de notifications ?
- **Choix proposés :**
  - A. Rien de particulier : la liste s’arrête simplement.
  - B. Un message discret : « Toutes les notifications ont été chargées ».
  - C. Un bouton « Retour en haut ».
  - D. Le message de B et le bouton de C.
- **Réponse :** D.
- **Décision :** à la fin de la liste, afficher un message discret « Toutes les notifications ont été chargées » et un bouton « Retour en haut ».
- **Conséquences :**
  - Le message apparaît uniquement lorsqu'il n'existe plus de notifications supplémentaires à charger pour les filtres actifs.
  - Le bouton permet de revenir en haut du centre de notifications.
  - Si les filtres sont modifiés et que d'autres résultats existent, le message de fin est retiré et le chargement progressif reprend.
- **Statut :** VALIDÉ — message de fin et bouton « Retour en haut ».
- **Périmètre concerné :** domaine Media ; centre de notifications et défilement infini.
- **Points restant à préciser :** aucun point fonctionnel spécifique identifié pour cette règle.
 
### Question 160 — Accès au centre de notifications (VALIDÉE)
- **Question :** Où afficher le centre de notifications dans MAHLINE ?
- **Choix proposés :**
  - A. Dans une page dédiée accessible depuis l’icône de notification.
  - B. Dans un panneau latéral qui s’ouvre depuis l’icône.
  - C. Dans une fenêtre déroulante sous l’icône.
  - D. A + C : page dédiée et aperçu rapide dans une fenêtre déroulante.
- **Réponse :** D.
- **Décision :** proposer une page dédiée au centre de notifications ainsi qu'un aperçu rapide dans une fenêtre déroulante accessible depuis l'icône.
- **Conséquences :**
  - L'aperçu déroulant permet une consultation rapide sans quitter la page en cours.
  - Un accès depuis l'aperçu ouvre la page dédiée pour consulter et gérer l'ensemble des notifications.
  - Les deux interfaces respectent les mêmes règles de visibilité, de lecture, de masquage, de filtres et de tri.
  - Le compteur rouge de l'icône conserve le comportement défini en Q148 et Q149.
- **Statut :** VALIDÉ — page dédiée et aperçu déroulant.
- **Périmètre concerné :** domaine Media ; navigation et centre de notifications.
- **Points restant à préciser :** nombre de notifications affichées dans l'aperçu rapide et actions disponibles directement depuis celui-ci.
 
### Question 161 — Nombre de notifications dans l’aperçu rapide (VALIDÉE)
- **Question :** Combien de notifications afficher dans la fenêtre déroulante ?
- **Choix proposés :**
  - A. 5 notifications.
  - B. 10 notifications.
  - C. 15 notifications.
  - D. 20 notifications.
- **Réponse :** C.
- **Décision :** afficher les 15 notifications les plus récentes dans l'aperçu rapide de la fenêtre déroulante.
- **Conséquences :**
  - L'aperçu présente au maximum 15 notifications, classées de la plus récente à la plus ancienne.
  - L'aperçu reste distinct du centre complet, qui conserve le défilement infini par lots de 10.
  - Un accès à la page dédiée permet de consulter toutes les notifications selon les filtres et règles définis.
- **Statut :** VALIDÉ — aperçu limité aux 15 notifications les plus récentes.
- **Périmètre concerné :** domaine Media ; aperçu rapide du centre de notifications.
- **Points restant à préciser :** actions disponibles directement dans l'aperçu rapide.
 
### Question 162 — Actions dans l’aperçu rapide (VALIDÉE)
- **Question :** Quelles actions l’utilisateur doit-il pouvoir effectuer directement depuis la fenêtre déroulante ?
- **Choix proposés :**
  - A. Consulter uniquement les notifications ; cliquer ouvre la page dédiée.
  - B. Consulter et marquer une notification comme lue.
  - C. Consulter, marquer comme lue et masquer une notification.
  - D. Toutes les actions disponibles dans le centre complet, directement depuis l’aperçu.
- **Réponse :** A + B.
- **Décision :** depuis l'aperçu rapide, l'utilisateur peut consulter les notifications, ouvrir une notification pour accéder à la page dédiée et marquer une notification comme lue directement dans l'aperçu.
- **Conséquences :**
  - L'aperçu rapide ne permet pas de masquer ou supprimer une notification.
  - L'ouverture d'une notification depuis l'aperçu conduit à sa page dédiée ; conformément à Q147, son ouverture entraîne automatiquement son marquage comme lue.
  - Une action « Marquer comme lue » est également disponible directement depuis l'aperçu, sans devoir ouvrir la page dédiée.
  - Le compteur de notifications non lues est cohérent avec le statut de lecture, mais son actualisation visible suit la règle de rechargement validée en Q149.
- **Statut :** VALIDÉ — consultation, ouverture de la page dédiée et marquage comme lu depuis l'aperçu.
- **Périmètre concerné :** domaine Media ; aperçu rapide du centre de notifications.
- **Points restant à préciser :** présentation des actions dans chaque élément de l'aperçu.
 
### Question 163 — Bouton « Tout marquer comme lu » dans l’aperçu rapide

- **Réponse :** A.
- **Décision validée :** afficher le bouton « Tout marquer comme lu » directement dans la fenêtre déroulante d’aperçu rapide.
- **Portée :** l’action marque comme lues toutes les notifications non lues accessibles à l’utilisateur connecté, sans modifier les notifications d’autres utilisateurs.
- **Cohérence :** cette action complète la possibilité de marquer une notification individuellement comme lue depuis l’aperçu (Q162). Le masquage reste réservé à la page dédiée.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 164 — Mise à jour du compteur rouge après lecture

- **Réponse :** D.
- **Décision validée :** après qu’une notification est marquée comme lue depuis l’aperçu rapide, le compteur rouge est mis à jour après un court délai automatique.
- **Portée :** le compteur représente toujours le nombre de notifications non lues accessibles à l’utilisateur connecté. Le délai exact et le mécanisme technique seront définis pendant l’audit technique.
- **Cohérence :** cette décision complète Q149, qui prévoit une actualisation du compteur uniquement au rechargement pour les nouvelles notifications. Le comportement après une action explicite de lecture est désormais distinct.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 165 — Fermeture de l’aperçu rapide

- **Réponse :** C (confirmée après clarification).
- **Décision validée :** l’aperçu rapide se ferme automatiquement lorsqu’on clique en dehors de la fenêtre ou lorsqu’on utilise le bouton de fermeture « X ».
- **Portée :** la touche Échap n’est pas incluse dans cette décision.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 166 — Navigation depuis une notification

- **Réponse :** A.
- **Décision validée :** le clic sur une notification ouvre la page concernée dans l’onglet actuel du navigateur.
- **Portée :** la notification sert de lien vers la page ou la ressource correspondante ; aucune ouverture systématique dans un nouvel onglet ni fenêtre modale n’est prévue.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 167 — Notification liée à une ressource supprimée ou inaccessible

- **Réponse :** A.
- **Décision validée :** si la ressource visée par une notification a été supprimée ou n’est plus accessible à l’utilisateur, afficher un message simple indiquant qu’elle n’est plus disponible.
- **Portée :** ne pas exposer de détails techniques ni d’informations sur une ressource à laquelle l’utilisateur n’a plus accès.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 168 — Confirmation lors du masquage d’une notification

- **Réponse :** B.
- **Décision validée :** masquer immédiatement la notification, sans demander de confirmation, et proposer une possibilité d’annulation pendant 30 secondes.
- **Portée :** le masquage reste individuel à l’utilisateur et ne supprime pas physiquement la notification ni son historique (décisions Q143–Q145).
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 169 — Distinction visuelle des notifications importantes

- **Réponse :** D.
- **Décision validée :** toutes les notifications utilisent le même style visuel ; aucun badge « Important » ni code couleur selon la gravité.
- **Portée :** cette uniformité concerne la présentation dans la liste et l’aperçu ; les catégories et filtres fonctionnels définis précédemment restent disponibles.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 170 — Notifications sans lien direct

- **Réponse :** C.
- **Décision validée :** lorsqu’une notification ne renvoie pas vers une ressource ou une page précise, son clic ouvre une page dédiée qui affiche son contenu détaillé.
- **Portée :** les notifications liées à une ressource continuent d’ouvrir cette ressource dans l’onglet actuel (Q166). Les notifications générales disposent d’une page de détail dédiée.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 171 — Bibliothèque centrale des médias

- **Réponse :** A (validation de l’option D proposée).
- **Décision validée :** MAHLINE disposera d’une bibliothèque centrale Media, avec classement par module et possibilité de retrouver un même fichier utilisé dans plusieurs modules.
- **Portée :** un fichier physique est enregistré une seule fois dans Media ; les modules (Produits, Coopératives, Catalogue, Marketplace et futurs modules) peuvent créer leurs propres références vers ce fichier. La suppression d’une référence ne supprime pas les autres références ni le fichier physique tant qu’il reste utilisé.
- **Règles transversales :** les droits d’accès, la visibilité et les règles de suppression restent contrôlés côté serveur et ne sont pas contournés par la bibliothèque centrale.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 172 — Organisation de la bibliothèque Media

- **Réponse :** A (validation de l’organisation recommandée).
- **Décision validée :** classement automatique des médias par coopérative, puis par module et type de fichier.
- **Portée :** aucun dossier personnalisé créé manuellement par les utilisateurs pour le premier release. La bibliothèque centrale permet de retrouver les médias et leurs références selon cette organisation.
- **Règles d’accès :** l’organisation et les filtres ne donnent aucun droit supplémentaire ; l’isolation entre coopératives et les autorisations par rôle sont vérifiées côté serveur.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 173 — Recherche dans la bibliothèque Media

- **Réponse :** D.
- **Décision validée :** proposer une recherche et des filtres combinables sur le nom du fichier, le type, la date d’ajout, le module et le produit associé.
- **Portée :** les résultats restent limités aux médias que l’utilisateur est autorisé à consulter. Les filtres n’élargissent jamais les droits d’accès ni le périmètre coopératif.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 174 — Prévisualisation des fichiers Media

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** permettre la prévisualisation intégrée de tous les fichiers acceptés lorsque le format et le navigateur le permettent ; proposer le téléchargement lorsque la prévisualisation n’est pas prise en charge.
- **Portée :** images, PDF et vidéos acceptés par Media ; les comportements précis dépendent des capacités techniques du format et du navigateur.
- **Orientation du premier release :** définir les fonctionnalités principales et indispensables de chaque domaine par questions-réponses, puis auditer les fonctionnalités déjà présentes dans le dépôt afin d’identifier ce qui est prêt, incomplet, absent ou à corriger pour le premier release. L’objectif est de coder les fonctionnalités validées et de limiter le périmètre au nécessaire pour lancer MAHLINE.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.
 
### Question 175 — Téléchargement des fichiers Media

- **Réponse :** C (validation de la recommandation).
- **Décision validée :** le droit de télécharger un média dépend du rôle de l’utilisateur, du module concerné, de ses autorisations et de la visibilité du fichier.
- **Règles d’accès :** la consultation ne donne pas automatiquement le droit de téléchargement. Les contrôles sont effectués côté serveur et respectent l’isolation des coopératives ainsi que les Policies/Gates ; connaître ou deviner l’URL du fichier ne permet pas de contourner ces contrôles.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 176 — Informations affichées sur la fiche d’un média

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** la fiche d’un média affiche le nom du fichier, son type, sa taille, sa date d’ajout, l’utilisateur qui l’a ajouté, la coopérative concernée, le module d’origine, les produits ou autres éléments auxquels il est associé, son statut de visibilité et son historique de modifications.
- **Règles d’accès :** les informations et associations affichées sont limitées au périmètre autorisé de l’utilisateur ; l’historique et les métadonnées ne doivent pas révéler des ressources d’autres coopératives ou des informations auxquelles il n’a pas accès.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 177 — Modification des métadonnées d’un média

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** un utilisateur autorisé peut modifier les métadonnées d’un média sans remplacer le fichier : nom, description, classement et visibilité, selon les permissions applicables.
- **Règles d’accès et de traçabilité :** toute modification est contrôlée côté serveur, respecte l’isolation des coopératives et les autorisations du module ; les changements sont historisés. Modifier le classement ou la visibilité ne donne aucun droit supplémentaire sur le fichier ni sur les ressources associées.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 178 — Détection des fichiers en double

- **Réponse :** C (validation de la recommandation).
- **Décision validée :** détecter les fichiers strictement identiques à partir de leur contenu (empreinte/hash), puis proposer à l’utilisateur autorisé de réutiliser le média existant ou de conserver un nouveau fichier.
- **Règles d’accès :** la détection et la proposition de réutilisation ne doivent pas révéler l’existence ni les métadonnées d’un média auquel l’utilisateur n’a pas accès ; la réutilisation respecte les autorisations, la visibilité et l’isolation des coopératives.
- **Statut :** décision fonctionnelle enregistrée ; implémentation non commencée.

### Question 179 — Matrice des autorisations Media

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** les permissions Media sont granulaires et indépendantes par action : ajout, consultation, modification des métadonnées, téléchargement, suppression et association/dissociation d’un média. Elles sont attribuées selon le rôle et le module concernés.
- **Règles d’accès :** chaque action vérifie côté serveur la permission effective, le périmètre de coopérative et les règles métier du module ; `super_admin_root` agit dans toutes les coopératives, `super_admin` dans celles qui lui sont assignées, `cooperative_admin` dans sa propre coopérative et `user` uniquement selon les permissions qui lui sont accordées. Aucune permission Media ne contourne les Policies/Gates ni l’isolation des coopératives.
- **Précision à établir :** la matrice détaillée rôle × module × action sera définie à partir des permissions existantes de MAHLINE lors du cadrage fonctionnel et vérifiée à l’audit technique ; aucune permission non validée n’est présumée accordée.
- **Statut :** principe fonctionnel enregistré ; matrice détaillée à établir ; implémentation non commencée.

### Question 180 — Gestion du stockage Media

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** chaque coopérative dispose d’un quota de stockage associé à sa formule d’abonnement (START, PRO ou BUSINESS). Le `super_admin_root` peut configurer les limites applicables.
- **Portée :** les limites chiffrées en Go ne sont pas encore fixées ; elles seront déterminées après vérification des coûts, de l’infrastructure d’hébergement et des besoins du premier release.
- **Règles de contrôle :** le quota est calculé et appliqué côté serveur par coopérative. Le stockage consommé par les médias partagés doit être comptabilisé de manière cohérente sans permettre de contourner les limites ni l’isolation entre coopératives.
- **Statut :** principe fonctionnel enregistré ; valeurs des quotas à définir ; implémentation non commencée.

### Question 181 — Comportement lorsque le quota Media est atteint

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** avertir la coopérative lorsque son stockage atteint 80 %, puis 95 %. À 100 %, bloquer les nouveaux téléversements pour cette coopérative.
- **Exception :** `super_admin_root` peut accorder exceptionnellement une capacité supplémentaire à une coopérative, avec justification et traçabilité de l'opération.
- **Continuité d'accès :** l'atteinte du quota ne supprime aucun média existant et ne bloque pas, à elle seule, la consultation ou le téléchargement des médias déjà autorisés.
- **Statut :** règle fonctionnelle enregistrée ; modalités de l'extension exceptionnelle et notifications à préciser ; implémentation non commencée.

### Question 182 — Téléversement de plusieurs fichiers

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** l'interface propose le téléversement d'un seul fichier ou de plusieurs fichiers en une seule opération.
- **Suivi :** afficher une progression individuelle et un état distinct pour chaque fichier afin que l'utilisateur identifie les fichiers réussis, en cours ou en échec.
- **Règles :** chaque fichier est contrôlé individuellement selon son type, sa taille, les permissions du demandeur et le quota de sa coopérative. Un échec sur un fichier ne doit pas faire perdre les fichiers déjà téléversés avec succès.
- **Statut :** principe fonctionnel enregistré ; détails de reprise et de gestion des échecs à préciser ; implémentation non commencée.

### Question 183 — Gestion des échecs de téléversement

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** pour chaque fichier en échec, Media affiche clairement la cause compréhensible de l'échec et propose une action « Réessayer » propre à ce fichier. Les fichiers déjà téléversés avec succès ne sont ni renvoyés ni annulés.
- **Portée :** chaque tentative de reprise concerne uniquement le fichier concerné ; les contrôles de format, taille, quota, autorisations et sécurité sont réexécutés côté serveur. Les détails techniques sensibles ne sont pas exposés à l'utilisateur.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 184 — Annulation des téléversements en cours

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** l'utilisateur peut annuler individuellement un fichier pendant son téléversement ou annuler en une seule action tous les fichiers encore en cours.
- **Portée :** l'annulation ne supprime ni ne renvoie les fichiers déjà téléversés avec succès. L'état de chaque fichier doit être clairement distingué (en attente, en cours, réussi, échoué ou annulé). Les éventuels fichiers partiels temporaires doivent être nettoyés de manière sûre côté serveur.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 185 — Reprise après interruption du téléversement

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** après une fermeture accidentelle de page ou une perte de connexion, Media tente de reprendre automatiquement le téléversement lorsque cela est techniquement possible ; si la reprise automatique ne peut pas aboutir, l'utilisateur retrouve le fichier interrompu et peut demander une reprise manuelle.
- **Portée :** la reprise doit être sécurisée et vérifier à nouveau les autorisations, le quota disponible et l'intégrité du fichier. Les fichiers déjà téléversés avec succès ne sont pas recommencés. La durée de conservation des données temporaires de reprise et les mécanismes techniques seront définis pendant l'audit et la conception.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 186 — Durée de conservation des téléversements interrompus

- **Réponse :** B (24 heures).
- **Décision validée :** les données temporaires nécessaires à la reprise d'un téléversement interrompu sont conservées pendant 24 heures au maximum.
- **Portée :** à l'expiration du délai, les données temporaires et fragments incomplets sont supprimés automatiquement et de manière sûre. Cette purge ne touche pas les fichiers déjà téléversés avec succès et enregistrés dans Media. La reprise reste soumise aux contrôles d'autorisation, de quota et d'intégrité.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 187 — Récapitulatif après un téléversement multiple

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** à la fin d'une opération de téléversement multiple, Media affiche un récapitulatif global indiquant le nombre de fichiers réussis, échoués et annulés, ainsi qu'un résultat détaillé pour chaque fichier avec son nom et son état.
- **Portée :** les erreurs affichées doivent être compréhensibles et ne pas divulguer de détails techniques sensibles. Les fichiers échoués restent identifiables pour une reprise individuelle ; les fichiers réussis ne sont pas renvoyés.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 188 — Nombre maximal de fichiers par opération

- **Réponse :** C (20 fichiers maximum).
- **Décision validée :** une opération de téléversement multiple accepte au maximum 20 fichiers sélectionnés.
- **Portée :** cette limite s'applique à chaque opération, indépendamment des limites de taille par fichier et du quota global de stockage de la coopérative. Les contrôles restent effectués côté serveur ; une évolution du plafond pourra être décidée après les tests de charge et de performance.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 189 — Ordre de traitement des fichiers

- **Réponse :** C (validation de la recommandation).
- **Décision validée :** Media téléverse plusieurs fichiers simultanément, avec un nombre de téléversements parallèles limité et configurable.
- **Portée :** la limite de parallélisme est déterminée selon les capacités réelles de l'hébergement et pourra être ajustée après les tests de charge. Elle ne modifie pas le plafond de 20 fichiers par opération. La progression, l'annulation et les erreurs restent suivies individuellement pour chaque fichier.
- **Statut :** règle fonctionnelle enregistrée ; valeur technique du parallélisme à définir pendant l'audit ; implémentation non commencée.

### Question 190 — Optimisation automatique des images

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** à l'import d'une image JPG, JPEG ou PNG, Media conserve le fichier original et génère automatiquement plusieurs versions optimisées adaptées aux usages (miniature, affichage normal et grand format).
- **Portée :** les versions dérivées sont générées à partir de l'original, sans le remplacer. Les règles précises de dimensions, qualité, formats de sortie, génération et stockage seront fixées pendant l'audit technique, en tenant compte des capacités de l'hébergement et du quota de la coopérative. L'accès à l'original et aux variantes reste soumis aux mêmes autorisations et règles de visibilité.
- **Statut :** règle fonctionnelle enregistrée ; paramètres techniques à définir pendant l'audit ; implémentation non commencée.

### Question 191 — Format des images optimisées

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** les variantes d'images générées par Media utilisent un format moderne optimisé, WebP lorsque le navigateur le prend en charge, avec un format de repli compatible si nécessaire.
- **Portée :** le fichier original JPG, JPEG ou PNG reste conservé. Le choix du format servi au navigateur doit être compatible avec ses capacités et ne doit pas contourner les règles d'autorisation, de visibilité ou de quota. Les formats de sortie exacts et la stratégie de détection/fallback seront confirmés pendant l'audit technique.
- **Statut :** règle fonctionnelle enregistrée ; stratégie technique à préciser pendant l'audit ; implémentation non commencée.

### Question 192 — Orientation et métadonnées EXIF des images

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** Media corrige automatiquement l'orientation des images à partir des informations EXIF lorsque cela est nécessaire. Les versions optimisées diffusées suppriment les métadonnées EXIF sensibles, notamment les coordonnées GPS et les informations sur l'appareil.
- **Portée :** le fichier original est conservé intact ; le traitement porte sur les variantes générées. Les contrôles doivent éviter toute exposition involontaire de métadonnées sensibles via les aperçus ou les téléchargements de variantes.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 193 — Dimensions maximales des images

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** le super_admin_root peut configurer les dimensions maximales acceptées par Media. Pour le premier release, la valeur par défaut recommandée est de 6 000 pixels au maximum sur le côté le plus long, avec un plafond complémentaire de 24 mégapixels par image.
- **Portée :** la limite de poids de 2 Mo par image reste applicable indépendamment des dimensions. Cette valeur par défaut vise à accepter les photos courantes de téléphones récents tout en limitant les coûts de décodage et de traitement sur l'hébergement. Les paramètres pourront être ajustés après les essais sur l'infrastructure réelle. L'original accepté reste conservé intact ; les variantes sont générées selon les règles d'optimisation validées.
- **Statut :** règle fonctionnelle enregistrée ; valeur par défaut recommandée à confirmer lors des tests techniques ; implémentation non commencée.

### Question 194 — Qualité des images optimisées

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** la qualité de compression est adaptative selon l'usage de chaque variante : miniature, affichage normal et grand format. Chaque variante est optimisée pour offrir un bon équilibre entre qualité visuelle, poids du fichier et rapidité d'affichage.
- **Portée :** les paramètres de qualité et de dimensions propres à chaque variante seront définis et vérifiés pendant l'audit technique. Les limites validées à la question 193 restent applicables à l'image source : 6 000 pixels maximum sur le côté le plus long, 24 mégapixels maximum et 2 Mo maximum. L'original reste conservé intact.
- **Statut :** règle fonctionnelle enregistrée ; paramètres techniques des variantes à définir ; implémentation non commencée.

### Question 195 — Moment de génération des variantes d'images

- **Réponse :** C (validation de la recommandation).
- **Décision validée :** la miniature est générée immédiatement après l'acceptation de l'image afin de devenir rapidement disponible dans l'interface. Les variantes d'affichage normal et grand format sont générées en arrière-plan, sans bloquer le téléversement.
- **Portée :** le traitement asynchrone doit signaler clairement l'état des variantes encore en cours de génération. Les erreurs de génération doivent être détectables et traitables sans invalider les fichiers déjà correctement téléversés ni exposer de détails techniques sensibles. Les règles de sécurité, de quota et de conservation de l'original restent applicables.
- **Statut :** règle fonctionnelle enregistrée ; mécanisme technique et gestion des erreurs à préciser pendant l'audit ; implémentation non commencée.

### Question 196 — Échec de génération d'une variante d'image

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** lorsqu'une variante normale ou grand format ne peut pas être générée, Media utilise temporairement l'original comme solution de secours lorsque cela est possible, déclenche des tentatives automatiques de régénération et propose une relance manuelle si les tentatives automatiques échouent.
- **Portée :** l'échec de génération d'une variante ne doit pas rendre le fichier original indisponible ni faire disparaître l'image de son emplacement autorisé. Les règles d'accès, de visibilité et de sécurité restent applicables au fichier de secours. Le nombre, l'espacement et les conditions des tentatives automatiques seront définis pendant l'audit technique.
- **Statut :** règle fonctionnelle enregistrée ; paramètres de reprise à définir lors de l'audit technique ; implémentation non commencée.

### Version 0.84 — 12 novembre 2026
- Question 83 — Publication d'un produit sur la marketplace : réponse A. Un produit devient visible et achetable automatiquement dès que la coopérative l'enregistre. Les conditions de complétude/validité des données, les contrôles avant publication, les modifications ultérieures et les règles de suspension/retrait restent à définir.
- Méthode de cadrage confirmée : privilégier des questions fonctionnelles concrètes, directement exploitables pour développer les fonctionnalités du premier release et les domaines concernés ; traiter séparément les évolutions post-release.

### Version 0.81 — 12 novembre 2026
- Complément de FR-009 — Question 82 : le `super_admin_root` décide au cas par cas s'il faut conserver l'historique des modifications apportées à un export. La décision, son motif et les éventuelles traces conservées doivent être documentés ; les règles de journalisation détaillées restent à cadrer.
- Ajustement de méthode demandé : les prochaines questions doivent être directement orientées vers les fonctionnalités concrètes nécessaires au premier release et vers le développement des domaines MAHLINE. Prioriser les parcours utilisateurs, règles métier, écrans/actions, données, statuts, permissions et cas d'erreur réellement nécessaires au lancement ; distinguer explicitement le périmètre premier release des évolutions ultérieures.

### Question 83 — Publication d'un produit sur la marketplace (VALIDÉ)
- **Question :** Lorsqu'une coopérative crée ou modifie un produit, à quel moment ce produit doit-il devenir visible et achetable sur la marketplace ?
- **Réponse :** A — automatiquement dès que la coopérative enregistre le produit.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** domaines Products, Catalog et Cooperatives ; visibilité publique, publication et autorisations.
- **Conséquences :**
  - L'enregistrement d'un produit par la coopérative déclenche automatiquement sa mise en visibilité et sa disponibilité à l'achat sur la marketplace.
  - Aucun workflow de validation manuelle préalable par un `super_admin` ou le `super_admin_root` n'est requis selon cette décision.
  - Les conditions de complétude et de validité des informations, les contrôles bloquants, l'effet des modifications ultérieures, ainsi que la suspension et le retrait de publication restent à définir par des questions spécifiques.

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


### Version 0.80 — 12 novembre 2026
- FR-009, Question 81 : décision A — seul le `super_admin_root` peut modifier un export déjà généré.

### Version 0.79 — 12 novembre 2026
- FR-009, Question 80 : décision A — un export généré reste entièrement modifiable sans création automatique d'une nouvelle version.
- **Conséquences / points à préciser :** la modification directe du fichier exporté ne crée pas de version V supplémentaire. Les modalités de traçabilité des modifications, l'identification de l'auteur et de l'heure, la conservation éventuelle de l'état antérieur et les restrictions techniques selon le format restent à préciser. Cette règle concerne le fichier exporté et ne modifie pas les pièces sources du dossier d'incident.

### Version 0.78 — 12 novembre 2026
- FR-009, Question 79 : décision D — le `super_admin_root` décide au cas par cas de conserver ou non une trace des pièces justificatives exclues d'un export ; sa justification et sa décision sont journalisées.

### Version 0.77 — 12 novembre 2026
- FR-009, Question 78 : décision C — pour chaque version d'un export, le `super_admin_root` sélectionne les pièces justificatives à inclure ; la sélection et l'export sont journalisés.

### Version 0.76 — 12 novembre 2026
- FR-009, Question 77 : décision C — conserver le numéro d'export d'origine et identifier chaque régénération par un numéro de version séquentiel (V1, V2, V3…). Chaque version reste liée à l'export initial et à sa date de référence.

### Version 0.75 — 12 novembre 2026
- FR-009, Question 76 : décision C — lors d'une régénération, le `super_admin_root` peut choisir entre le contenu historique identique à l'export initial et les données actuelles ; la date de référence doit être clairement indiquée.

### Version 0.74 — 12 novembre 2026
- FR-009, Question 75 : décision D — après expiration de la disponibilité d'un export, le `super_admin_root` décide au cas par cas entre sa régénération et sa restauration temporaire ; justification et journalisation obligatoires.

### Version 0.72 — 12 novembre 2026
- **FR-009, Question 74 :** décision D — le `super_admin_root` définit la durée de disponibilité des fichiers exportés selon le type d'export ; leur suppression est contrôlée et journalisée.

- FR-009, Question 73 : décision B — la confirmation du mot de passe actuel reste valable 5 minutes pour effectuer plusieurs exports ; une nouvelle confirmation est exigée après expiration. L'heure de validation et les exports effectués dans cette fenêtre sont journalisés ; le mot de passe n'est jamais conservé.

### Version 0.71 — 12 novembre 2026
- FR-009, Question 72 : décision B — confirmation du mot de passe actuel du `super_admin_root` exigée avant le téléchargement d'un export ; aucun secret n'est journalisé.

### Version 0.70 — 12 novembre 2026
- FR-009, Question 71 : décision C — export réservé au `super_admin_root` en PDF, Excel/CSV et ZIP avec pièces justificatives ; exports journalisés et protégés contre la divulgation de secrets.

### Version 0.69 — 12 novembre 2026
- FR-009, Question 70 : décision A — consultation de l'historique complet des incidents réservée au `super_admin_root` ; consultations journalisées.

### Version 0.68 — 12 novembre 2026
- FR-009, Question 69 : décision A — conservation permanente de l'historique complet des incidents de notification, sous réserve des exigences légales et de protection des données.

### Version 0.67 — 12 novembre 2026
- FR-009, Question 68 : décision D — en cas de récidive immédiate après réactivation par le `super_admin` responsable, le `super_admin_root` décide au cas par cas ; décision et changements d'état journalisés.

### Version 0.66 — 12 novembre 2026
- FR-009, Question 67 : décision B — réactivation par le `super_admin_root` ou le `super_admin` responsable de la coopérative, par action explicite et journalisée ; aucune réactivation automatique.

### Version 0.65 — 12 novembre 2026
- FR-009, Question 66 : décision D — le `super_admin_root` décide au cas par cas du traitement de l'incident, avec justification et journalisation ; le statut de l'incident reste distinct de la suspension des notifications.

### Version 0.64 — 12 novembre 2026
- FR-009, Question 65 : décision D — en l'absence de réponse du destinataire, le `super_admin_root` décide au cas par cas, sans délai automatique, avec justification et traçabilité.

### Version 0.63 — 12 novembre 2026
- FR-009, Question 64 : décision C — si la remise effective ne peut pas être confirmée techniquement, le `super_admin_root` vérifie manuellement auprès du destinataire avant la reprise des notifications ; vérification et décision tracées.

### Version 0.62 — 12 novembre 2026
- FR-009, Question 63 : décision C — un e-mail de test n'est considéré réussi que si le destinataire le reçoit effectivement dans sa boîte de réception. La capacité du fournisseur à fournir une preuve fiable de remise doit être vérifiée.

### Version 0.61 — 12 novembre 2026
- FR-009, Question 62 : décision C — après correction du destinataire, un e-mail de test est envoyé ; les renvois normaux reprennent uniquement si ce test réussit.

### Version 0.60 — 12 novembre 2026
- FR-009, Question 61 : décision D — le `super_admin_root` peut relancer les envois, corriger l'adresse, modifier le destinataire et clôturer manuellement après vérification ; opérations auditées.

### Version 0.59 — 12 novembre 2026
- FR-009, Question 60 : décision A — après 10 échecs, arrêt des renvois automatiques dans l'attente d'une intervention du `super_admin_root`. Incident maintenu ouvert ; actions manuelles et reprise à préciser.

### Version 0.58 — 12 novembre 2026
- FR-009, Question 59 : décision D — temporisation progressive des renvois : 5 min, 15 min, 30 min, 1 h, puis toutes les 2 h. Séquence exacte des 10 tentatives et autres règles de planification à préciser.

### Version 0.57 — 12 novembre 2026
- FR-009, Question 58 : décision C — 10 tentatives automatiques infructueuses avant alerte au `super_admin_root`. Intervalle, compteur, typologie d'erreurs et réinitialisation à préciser.

### Version 0.56 — 12 novembre 2026
- FR-009, Question 57 : décision A — après clôture, un nouvel échec rouvre automatiquement le même incident et relance les tentatives. L'historique précédent est conservé ; regroupement et conservation à préciser.

### Version 0.55 — 12 novembre 2026
- FR-009, Question 56 : décision D — clôture automatique après nouvel envoi réussi ; clôture manuelle possible par le `super_admin_root` après vérification, avec justification et traçabilité. Définition du succès, nouvel échec après clôture et preuve de remise à préciser.

### Version 0.54 — 12 novembre 2026
- FR-009, Question 55 : décision D — alerte au `super_admin_root` dans son espace MAHLINE, par e-mail et indicateur persistant au tableau de bord jusqu'à résolution. Déduplication, prise en charge, escalade et clôture à préciser.

### Version 0.53 — 12 novembre 2026
- FR-009, Question 54 : décision D — renvois automatiques de l'e-mail ; si l'échec persiste, alerte au `super_admin_root` pour intervention. Journaliser les tentatives et résultats ; nombre, intervalles, canal d'alerte et traitement manuel à préciser.

### Version 0.52 — 12 novembre 2026
- FR-009, Question 53 : décision C — chaque destinataire reçoit la notification de décision dans son espace MAHLINE et par e-mail. Contenu adapté au rôle ; gestion des échecs, reprises et preuves de notification à préciser.

### Version 0.51 — 12 novembre 2026
- FR-009, Question 52 : décision B — la notification communique le résultat (confirmée, modifiée ou annulée) et un résumé du motif. Le dossier complet, les preuves et les échanges ne sont pas transmis automatiquement ; adaptation du résumé par rôle, modèles et canaux restent à préciser.

## 3. Questions ouvertes

Les questions seront ajoutées au fur et à mesure, dans l'ordre du parcours métier. Une question n'est considérée validée que lorsque la réponse de l'utilisateur est explicite.

| ID | Sujet | Statut |
|---|---|---|
| FR-004 | Moyens de paiement du premier release | VALIDÉ — paiement à la livraison ; paiement en ligne après le release |
| FR-005 | Frais de livraison selon la grille trajet-ville des transporteurs | PRINCIPE VALIDÉ — grille réelle attendue |
| FR-006 | Calcul des frais pour un panier multi-coopératives | VALIDÉ — somme des frais par expédition au premier release |
| FR-007 | Gestion des stocks et réservation selon le moyen de paiement | VALIDÉ — COD à la confirmation coopérative, sans annulation automatique par délai ; paiement en ligne à la validation (post-release) |
| FR-008 | Traitement et transitions de statut des commandes coopératives | PRINCIPES VALIDÉS — orchestration MAHLINE ; accès transporteur au premier release, API ensuite ; refus limité à la sous-commande concernée ; statuts à préciser |
| FR-009 | Annulation, retour et remboursement | PARTIELLEMENT VALIDÉ — fenêtres selon paiement ; annulation indépendante ; après remise au transporteur, pas d'annulation mais refus à la livraison possible ; frais de retour variables selon le motif ; responsabilité des frais définie pour erreur coopérative, dommage transport et changement d'avis ; photos obligatoires, minimum 1 par étape concernée ; au départ, photos coopérative + transporteur ; au retour, photos transporteur au départ du retour + coopérative à réception ; contrôle complet à réception ; produits conformes acceptés, anomalie par produit non conforme ; coopérative et transporteur fournissent les preuves, MAHLINE centralise l'enquête ; en cas de désaccord, litige et intervention humaine avant décision finale ; décision par le super_admin responsable de la coopérative ; si conflit d'intérêts ou contestation par ce responsable, transfert prioritaire au super_admin_root, puis à défaut à un autre super_admin habilité et non impliqué ; le client peut contester et fournir des preuves ; réexamen par le super_admin_root en priorité, ou à défaut par un autre super_admin habilité et non impliqué ; le super_admin_root décide au cas par cas de suspendre ou non l'application de la décision initiale pendant le réexamen ; contestation dans les 48 heures suivant la notification ; canal/preuve de notification, calcul du délai, exceptions, critères de suspension, motifs, une seule contestation autorisée par litige ; ajout ; au début du réexamen, notification dans l'espace client et par e-mail (Q40) de preuves par le client autorisé jusqu'au commencement du réexamen ; ouverture du dossier par le responsable désigné = début officiel du réexamen ; notification dans l'espace client et e-mail, mais l'échec d'e-mail ne bloque pas ; ouverture accidentelle : annulation par super_admin_root avec justification et traçabilité ; après annulation, ajout de preuves client automatiquement réactivé jusqu'à la prochaine ouverture du dossier ; annulations multiples autorisées sans limite numérique prédéfinie par le `super_admin_root`, justification et traçabilité obligatoires à chaque fois ; aucune notification dédiée au client lors de la réactivation de l'ajout de preuves (Q46) ; après réactivation, le client peut ajouter des preuves sans échéance fixe jusqu'à la prochaine ouverture du dossier par le responsable désigné (Q47) ; après réexamen, le réviseur peut confirmer, modifier ou annuler la décision initiale selon les preuves et circonstances (Q48) ; la décision rendue après réexamen est définitive et ne peut faire l'objet d'une seconde contestation (Q49) ; son application intervient selon la nature de la décision (confirmation, modification ou annulation) (Q50), avec modalités propres à préciser ; la décision définitive après réexamen est notifiée au client, à la coopérative, au transporteur et au `super_admin` responsable (Q51) ; contenu de notification : résultat et résumé du motif (Q52) |
| FR-010 | Factures, reçus et documents client/coopérative | À poser |
| FR-011 | Commissions et reversements aux coopératives | À poser |
| FR-012 | Inscription et espace personnel client | À poser |
| FR-013 | Publication et visibilité des coopératives/produits | À poser |
| FR-014 | Livraison nationale et internationale au premier release | À poser |
| FR-015 | Abonnements SaaS, plans, quotas et renouvellement | À poser |
| FR-016 | Notifications et communications indispensables au lancement | À poser |

### FR-009 — Annulation, retour et remboursement

- **Décision complémentaire — réactivation de l'ajout de preuves après annulation du début du réexamen (Question 44) :** A — après annulation par le `super_admin_root` d'une ouverture accidentelle, le client retrouve automatiquement la possibilité d'ajouter des preuves jusqu'à la prochaine ouverture du dossier par le responsable désigné.
- **Conséquences / contraintes :**
  - La fermeture de l'ajout de preuves liée au début du réexamen est levée lorsque le `super_admin_root` annule ce début.
  - Le client peut alors compléter le dossier jusqu'à la prochaine ouverture effective par le responsable désigné.
  - La prochaine ouverture ferme à nouveau l'ajout de preuves et marque le nouveau début effectif du réexamen.
  - **Question 45 — nombre d'annulations d'une ouverture accidentelle :** B — le `super_admin_root` peut annuler plusieurs fois une ouverture accidentelle du réexamen, sans limite numérique prédéfinie, à condition de fournir une justification à chaque annulation.
  - Chaque annulation doit être historisée séparément avec son auteur, sa date/heure, sa justification, le dossier concerné et l'état avant/après. Après chaque annulation, l'ajout de preuves client est réactivé jusqu'à la prochaine ouverture du dossier par le responsable désigné.
  - Le contrôle des abus éventuels et les autres modalités de notification ou d'échéance restent à préciser.
  - **Question 46 — notification au client de la réactivation de l'ajout de preuves :** D — aucune notification dédiée n'est envoyée ; le client constate lui-même que l'ajout de preuves est de nouveau disponible.
  - L'absence de notification ne modifie pas la réactivation effective du dépôt de preuves. Les autres notifications déjà prévues au début effectif du réexamen restent applicables.
  - **Question 47 — délai de dépôt des preuves après réactivation :** C — aucun délai fixe n'est appliqué après la réactivation. Le client peut ajouter des preuves jusqu'à la prochaine ouverture du dossier par le responsable désigné.
  - La prochaine ouverture effective du dossier clôt le dépôt de preuves et marque le début du réexamen, conformément aux décisions des questions 42 et 44. Il n'y a donc pas de nouvelle échéance de 48 heures à chaque réactivation. Les modalités de prévention des dépôts abusifs ou disproportionnés restent à préciser.
  - **Question 48 — portée de la décision après réexamen :** D — selon les preuves et les circonstances, le réviseur peut confirmer intégralement la décision initiale, la modifier ou l'annuler.
  - La décision de réexamen doit expliciter l'issue retenue et sa motivation, et être historisée avec l'identité du réviseur, la date/heure, les éléments examinés et le lien vers la décision initiale. Les effets opérationnels et financiers de chaque issue restent à préciser.
  - **Question 49 — caractère définitif de la décision après réexamen :** A — la décision rendue après réexamen est définitive ; aucune seconde contestation du client n'est possible.
  - Le système doit empêcher l'ouverture d'une nouvelle contestation contre la décision de réexamen du même litige. La traçabilité de la décision finale et de sa notification reste obligatoire ; les voies de traitement d'une éventuelle erreur matérielle ou d'une irrégularité exceptionnelle ne sont pas définies ici.
  - **Question 50 — moment d'application de la décision définitive :** D — l'application dépend de la nature de la décision rendue après réexamen : confirmation, modification ou annulation.
  - Il faudra définir les modalités et le moment d'effet pour chacune de ces trois issues avant développement ; aucune règle uniforme d'application immédiate ou différée n'est présumée.
  - **Question 51 — destinataires de la décision définitive après réexamen :** D — le client, la coopérative concernée, le transporteur impliqué et le `super_admin` responsable doivent être informés.
  - La communication doit être adaptée aux droits et au rôle de chaque destinataire : chaque acteur ne reçoit que les informations et pièces nécessaires à son intervention. Les canaux, le contenu détaillé et la preuve de notification restent à définir.
  - **Question 52 — contenu de la notification de décision :** B — chaque destinataire reçoit le résultat de la décision (confirmée, modifiée ou annulée) accompagné d'un résumé du motif.
  - **Conséquences / contraintes :** la notification doit distinguer clairement l'issue du réexamen et en expliquer brièvement la raison. Elle ne transmet pas automatiquement le dossier complet, toutes les preuves ou l'ensemble des échanges. Le niveau de détail du résumé selon le rôle du destinataire, les modèles de notification et les canaux restent à préciser.
  - **Question 53 — canaux de notification de la décision définitive :** C — chaque destinataire reçoit la notification dans son espace MAHLINE et par e-mail.
  - **Conséquences / contraintes :** la notification doit être disponible dans l'espace MAHLINE et envoyée par e-mail à chacun des destinataires concernés (client, coopérative, transporteur impliqué et `super_admin` responsable). Le contenu reste adapté au rôle et aux droits du destinataire ; l'échec d'envoi de l'e-mail, les reprises éventuelles, les preuves de remise/consultation et les règles de conservation restent à préciser.
  - **Question 54 — gestion de l'échec d'envoi de l'e-mail :** D — MAHLINE effectue des tentatives automatiques de renvoi ; si l'échec persiste, le `super_admin_root` est alerté pour intervention.
  - **Conséquences / contraintes :** l'échec de l'e-mail ne bloque pas la disponibilité de la notification dans l'espace MAHLINE. Le système doit tracer chaque tentative et son résultat, appliquer une politique de nouvelles tentatives, puis générer une alerte au `super_admin_root` lorsque l'envoi reste en échec. Le nombre de tentatives, leurs intervalles, le canal d'alerte du root, le traitement manuel et la clôture de l'incident restent à définir.
  - **Question 55 — canaux et visibilité de l'alerte au `super_admin_root` :** D — alerte dans son espace MAHLINE, par e-mail et sous forme d'un indicateur visible dans son tableau de bord jusqu'à résolution de l'incident.
  - **Conséquences / contraintes :** l'incident d'échec d'e-mail doit rester visiblement signalé dans le tableau de bord du `super_admin_root` tant qu'il n'est pas résolu ; une notification est également créée dans son espace MAHLINE et envoyée par e-mail. Les règles de déduplication, d'escalade, d'accusé de prise en charge, de résolution et de disparition de l'indicateur restent à préciser.
  - **Question 56 — clôture de l'incident d'envoi d'e-mail :** D — MAHLINE clôture automatiquement l'incident dès qu'un nouvel envoi réussit ; le `super_admin_root` peut également le clôturer manuellement après vérification.
  - **Conséquences / contraintes :** un envoi ultérieur réussi entraîne la résolution automatique de l'incident et la disparition de l'indicateur persistant. Le `super_admin_root` conserve la possibilité de clôturer manuellement après contrôle. La clôture manuelle doit être justifiée et tracée ; le comportement en cas de nouvel échec après clôture, les critères de succès de l'envoi et la distinction entre envoi accepté par le serveur et remise effective restent à préciser.
  - **Question 57 — nouvel échec après clôture :** A — MAHLINE rouvre automatiquement le même incident et reprend les tentatives d'envoi.
  - **Conséquences / contraintes :** un nouvel échec après clôture réactive le même incident, sans créer un dossier distinct, et relance la politique de nouvelles tentatives. L'historique antérieur doit être conservé ; les événements de réouverture, les nouvelles tentatives et les nouvelles clôtures doivent être ajoutés à la chronologie. La règle de regroupement des échecs successifs et la durée de conservation de l'incident restent à préciser.
  - **Question 58 — nombre de tentatives automatiques avant alerte :** C — MAHLINE effectue 10 tentatives automatiques d'envoi avant d'alerter le `super_admin_root`.
  - **Conséquences / contraintes :** le seuil est fixé à 10 tentatives pour une séquence d'échec. Les tentatives et leurs résultats sont journalisés ; après la dixième tentative infructueuse, l'alerte au `super_admin_root` est déclenchée. L'intervalle entre les tentatives, le point de départ du compteur, le traitement des erreurs temporaires/permanentes et la réinitialisation du compteur après succès restent à préciser.
  - **Question 59 — intervalle entre les tentatives automatiques :** D — délai progressif : 5 minutes, 15 minutes, 30 minutes, 1 heure, puis toutes les 2 heures.
  - **Conséquences / contraintes :** les renvois suivent une temporisation progressive et plafonnent ensuite à un intervalle de 2 heures. La séquence exacte des délais pour les 10 tentatives, le calcul à partir de l'échec initial ou de la tentative précédente, les heures silencieuses éventuelles et le comportement après la dixième tentative restent à préciser.
  - **Question 60 — comportement après 10 tentatives infructueuses :** A — MAHLINE arrête les renvois automatiques et attend l'intervention du `super_admin_root`.
  - **Conséquences / contraintes :** après la dixième tentative infructueuse, aucun nouvel envoi automatique n'est lancé tant qu'une intervention du `super_admin_root` n'a pas eu lieu. L'incident reste ouvert et visible dans le tableau de bord jusqu'à résolution ; le root doit pouvoir examiner l'échec et décider de la suite. Les actions manuelles disponibles (relancer, corriger l'adresse, changer le destinataire ou clôturer), leur traçabilité et la reprise éventuelle des tentatives restent à définir.
  - **Question 61 — actions du `super_admin_root` sur l'incident :** D — il peut relancer les tentatives automatiques, corriger l'adresse e-mail, modifier le destinataire et clôturer manuellement l'incident après vérification.
  - **Conséquences / contraintes :** ces opérations sont réservées au `super_admin_root` et doivent être journalisées (acteur, date/heure, action, motif si nécessaire et résultat). Une relance manuelle reprend l'envoi après l'arrêt automatique au seuil de 10 échecs. Une modification d'adresse ou de destinataire doit être enregistrée dans l'historique de l'incident. La clôture manuelle n'est possible qu'après vérification et doit être justifiée. Les règles exactes de reprise du compteur, les contrôles de validité du nouvel e-mail et la distinction entre acceptation par le serveur et livraison effective restent à préciser.
  - **Question 62 — après correction de l'adresse ou du destinataire :** C — MAHLINE envoie un seul e-mail de test ; si l'envoi réussit, les tentatives normales reprennent.
  - **Conséquences / contraintes :** le test est une étape préalable à la reprise des notifications de l'incident. Son résultat (date/heure, adresse ou destinataire ciblé, statut et erreur éventuelle) est journalisé. En cas d'échec du test, les tentatives normales restent suspendues et l'incident demeure ouvert pour intervention du `super_admin_root`. La définition précise d'un test réussi (acceptation par le serveur d'envoi ou confirmation de livraison), le nombre de tests autorisés et la reprise du compteur des 10 tentatives restent à préciser.
  - **Question 63 — définition d'un e-mail de test réussi :** C — le destinataire reçoit effectivement l'e-mail dans sa boîte de réception.
  - **Conséquences / contraintes :** la simple acceptation du message par MAHLINE ou par le fournisseur d'e-mail ne suffit pas à valider le test. La reprise des notifications normales dépend d'une preuve de remise effective dans la boîte du destinataire. Il faudra vérifier que le fournisseur et son mécanisme de suivi permettent d'obtenir un statut de remise suffisamment fiable ; une acceptation SMTP ou un statut « envoyé » ne doit pas être présenté comme une preuve de réception en boîte de réception. Le traitement des messages placés en spam, des boîtes pleines, des rejets différés et l'absence de preuve de remise restent à préciser.
  - **Question 64 — absence de confirmation de réception effective :** C — si le fournisseur ne peut pas confirmer la remise dans la boîte du destinataire, le `super_admin_root` doit vérifier manuellement auprès du destinataire avant toute reprise des notifications.
  - **Conséquences / contraintes :** l'absence de preuve technique de remise ne vaut ni succès ni échec certain. Les notifications normales restent suspendues jusqu'à la vérification manuelle du `super_admin_root` auprès du destinataire. La vérification, son auteur, sa date/heure, le destinataire contacté, le résultat et la décision de reprise doivent être journalisés. Les modalités de preuve et les cas où le destinataire reste injoignable restent à préciser.
  - **Question 65 — absence de réponse du destinataire à la vérification manuelle :** D — le `super_admin_root` décide au cas par cas, sans délai automatique, et doit justifier sa décision.
  - **Conséquences / contraintes :** aucune reprise automatique des notifications n'est déclenchée par le seul écoulement du temps. Les notifications restent suspendues jusqu'à une décision explicite du `super_admin_root`. La décision, sa justification, son auteur, sa date/heure et son éventuelle portée (reprise, maintien de la suspension ou autre mesure) doivent être journalisés. Aucun délai par défaut de 24 ou 48 heures n'est imposé.
  - **Question 66 — incident après décision de maintenir la suspension :** D — le `super_admin_root` décide au cas par cas du traitement de l'incident, avec justification et journalisation.
  - **Conséquences / contraintes :** aucune clôture automatique ni obligation uniforme de maintenir l'incident ouvert n'est imposée. La décision doit distinguer le statut de l'incident de l'état de suspension des notifications : clôture ou maintien de l'incident ne réactive pas implicitement les notifications. Toute décision et tout changement d'état sont justifiés et journalisés.
  - **Question 67 — autorité de réactivation des notifications suspendues :** B — le `super_admin_root` ou le `super_admin` responsable de la coopérative peut réactiver les notifications.
  - **Conséquences / contraintes :** le `super_admin` ne peut agir que pour une coopérative dont il est responsable ; le `super_admin_root` conserve une autorité globale. La réactivation doit être une action explicite, après vérification/correction de la cause de suspension, et être journalisée (acteur, date/heure, incident, destinataire, motif et état avant/après). Aucune réactivation automatique n'est autorisée. Les modalités précises de vérification préalable et les éventuelles restrictions selon le type d'incident restent à définir.
  - **Question 68 — récidive immédiate après réactivation par le `super_admin` :** D — le `super_admin_root` décide au cas par cas de la conduite à tenir.
  - **Conséquences / contraintes :** aucune règle automatique uniforme n'est imposée pour cette récidive. Le cas est signalé au `super_admin_root`, qui décide notamment du maintien ou de la nouvelle suspension des notifications, du traitement de l'incident existant ou de sa réouverture, et des éventuelles mesures correctives. La décision, son motif, son auteur, sa date/heure et les changements d'état doivent être journalisés. La récidive ne doit pas être traitée comme une réactivation réussie sans vérification.
  - **Question 69 — conservation de l'historique des incidents de notification :** A — conserver définitivement l'intégralité de l'historique.
  - **Conséquences / contraintes :** l'historique complet doit être conservé sans suppression liée à une échéance prédéfinie : tentatives d'envoi, erreurs et résultats, changements d'adresse ou de destinataire, e-mails de test, vérifications manuelles, décisions de suspension/réactivation/clôture et réouvertures. Les événements doivent rester consultables et traçables, avec accès strictement autorisé et protection contre l'altération. Les règles techniques d'archivage, de stockage et les obligations légales applicables restent à vérifier ; « définitivement » ne dispense pas de respecter les exigences légales de protection et de conservation des données.
  - **Question 70 — consultation de l'historique complet des incidents :** A — seul le `super_admin_root` peut consulter l'historique complet.
  - **Conséquences / contraintes :** l'accès à l'historique intégral est strictement réservé au `super_admin_root`. Les autres rôles ne disposent pas d'un accès à cet historique complet, même lorsqu'ils sont responsables de la coopérative ou concernés par l'incident. Les éventuelles notifications opérationnelles qui leur sont adressées restent limitées aux informations nécessaires à leur rôle et ne constituent pas un accès au dossier complet. Les consultations de l'historique doivent elles-mêmes être journalisées.
  - **Question 71 — export de l'historique complet d'un incident :** C — le `super_admin_root` peut exporter un PDF, un fichier Excel/CSV et une archive ZIP contenant les pièces justificatives.
  - **Conséquences / contraintes :** les trois formats d'export sont prévus. L'export est réservé au `super_admin_root`, doit être limité au dossier autorisé et inclure les données et pièces justificatives pertinentes sans divulguer de secrets (mots de passe, jetons, codes ou données sensibles non nécessaires). Chaque export doit être journalisé : auteur, date/heure, incident, format, périmètre et résultat. L'archive ZIP doit préserver l'intégrité et la traçabilité des pièces ; modalités de signature, durée de disponibilité des fichiers exportés et protection contre le partage non autorisé restent à préciser.
  - **Question 72 — protection des exports d'incidents :** B — confirmation du mot de passe du `super_admin_root` avant le téléchargement.
  - **Conséquences / contraintes :** une réauthentification par le mot de passe actuel est exigée au moment de la demande d'export ou avant sa mise à disposition. Aucun code supplémentaire ni lien à usage unique n'est imposé par cette décision. Le mot de passe ne doit jamais être stocké dans les journaux, transmis par e-mail ou conservé après vérification. La vérification, son résultat, l'identité de l'acteur et l'export demandé sont journalisés sans enregistrer le secret. La durée de validité de la réauthentification et les protections du fichier après téléchargement restent à définir.


- **Décision complémentaire — ouverture accidentelle du dossier de réexamen (Question 43) :** C — seul le `super_admin_root` peut annuler le début du réexamen si le responsable désigné a ouvert le dossier par erreur. L'annulation exige une justification et doit être entièrement traçable.
- **Conséquences / contraintes :**
  - L'ouverture du dossier par le responsable désigné reste l'événement qui marque le début officiel du réexamen et ferme l'ajout de preuves par le client.
  - Une annulation ne peut être effectuée que par le `super_admin_root`, jamais par le responsable du réexamen lui-même.
  - L'auteur, la date et l'heure, la justification, le dossier concerné et l'état avant/après doivent être historisés.
  - Le comportement après annulation (réouverture de l'ajout de preuves, nouvelle notification au client, nouvelle prise en charge et nouveau début du réexamen) reste à définir.


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

- **Décision complémentaire — désaccord entre la coopérative et le transporteur (Question 29) :** D — lorsqu'un désaccord existe sur la responsabilité d'un dommage, le dossier est placé en litige et nécessite une intervention humaine avant toute décision finale.
- **Conséquences / contraintes :**
  - Un désaccord ne peut pas être tranché automatiquement par MAHLINE sur la seule base des preuves disponibles.
  - Le dossier doit conserver son statut de litige et réunir les preuves, événements, observations et positions des parties.
  - Aucune attribution définitive de responsabilité ni conséquence financière liée au dommage ne doit être appliquée avant la décision humaine.
  - L'identité de l'acteur humain habilité à examiner et trancher le litige, les critères de décision, les délais de traitement et les éventuelles voies de contestation restent à définir.
- **Décision complémentaire — autorité habilitée à trancher un litige (Question 30) :** B — un `super_admin` habilité à gérer la coopérative concernée prend la décision finale.
- **Conséquences / contraintes :**
  - La décision finale relève d'un `super_admin` disposant explicitement de l'habilitation nécessaire sur la coopérative concernée.
  - L'accès au dossier de litige et les actions de décision doivent être limités aux `super_admin` habilités ; un `super_admin` non habilité ne doit pas pouvoir consulter ou trancher ce dossier.
  - La décision, son auteur, sa date, son motif et les preuves examinées doivent être conservés dans l'historique traçable du litige.
  - Les critères de désignation/habilitation, la gestion des conflits d'intérêts, les délais, les contestations et les cas où aucun `super_admin` n'est habilité restent à définir.
- **Décision complémentaire — absence de `super_admin` habilité (Question 31) :** B — le litige reste en attente jusqu'à la désignation d'un `super_admin` habilité.
- **Conséquences / contraintes :**
  - Aucun autre rôle ne peut se substituer automatiquement au décideur habilité.
  - Le dossier conserve son statut de litige et reste sans décision finale tant qu'aucun `super_admin` habilité n'a été désigné.
  - La désignation d'un décideur habilité devient une étape nécessaire pour reprendre le traitement.
  - L'acteur responsable de cette désignation, le délai maximal d'attente et la procédure d'escalade si aucun décideur n'est désigné restent à préciser.
- **Précision complémentaire — responsable du traitement/désignation du litige (Question 32) :** le `super_admin` qui prend en charge la coopérative concernée est le responsable désigné pour intervenir sur le litige de cette coopérative.
- **Conséquences / contraintes :**
  - Le rattachement du `super_admin` à la coopérative détermine son habilitation à traiter le litige correspondant.
  - Le litige doit être orienté vers le `super_admin` responsable de cette coopérative ; il n'est pas attribué automatiquement à un administrateur quelconque.
  - Si aucun `super_admin` ne prend en charge la coopérative, le litige reste en attente conformément à la décision de la Question 31.
  - Le cas d'absence durable de responsable et son éventuelle escalade restent à définir.

- **Décision complémentaire — conflit d'intérêts ou indisponibilité du `super_admin` responsable (Question 33) :** A ou B, selon la règle validée : A en priorité — le `super_admin_root` reprend exceptionnellement le dossier lorsque le `super_admin` responsable est lui-même impliqué dans le litige ou conteste la décision ; B en alternative — si le `super_admin_root` ne peut pas intervenir, un autre `super_admin` habilité sur la coopérative et non impliqué examine le dossier.
- **Conséquences / contraintes :**
  - Le `super_admin` initialement responsable ne peut pas instruire ni trancher un litige dans lequel il est impliqué ou dont il conteste la décision.
  - Le transfert au `super_admin_root` constitue la voie prioritaire de remplacement.
  - Si le `super_admin_root` ne peut pas intervenir, le dossier peut être confié à un autre `super_admin` habilité sur la coopérative, à condition qu'il ne soit pas impliqué dans le litige.
  - Le motif du conflit ou de l'indisponibilité, le transfert du dossier, l'identité du nouveau responsable et toute décision doivent être historisés.
  - La définition opérationnelle de l'impossibilité d'intervention du `super_admin_root` et les garanties de neutralité du remplaçant restent à préciser.

- **Décision complémentaire — contestation d'une décision de litige par le client (Question 34) :** A — le client peut déposer une contestation et fournir des preuves à l'appui.
- **Conséquences / contraintes :**
  - Le client doit disposer d'un moyen de contester une décision rendue sur un litige qui concerne sa commande.
  - La contestation doit permettre de joindre ou transmettre des éléments de preuve complémentaires.
  - La contestation, ses pièces, sa date de dépôt et son traitement doivent être historisés et rattachés au litige initial.
  - L'autorité chargée de réexaminer la décision, les délais de contestation, les motifs recevables, le nombre de recours et les effets de la contestation sur l'exécution de la décision restent à définir.

- **Décision complémentaire — autorité de réexamen de la contestation client (Question 35) :** C — le `super_admin_root` examine la contestation en priorité ; s'il est indisponible, le dossier peut être confié à un autre `super_admin` habilité sur la coopérative et non impliqué dans le litige ni dans la décision initiale.
- **Conséquences / contraintes :**
  - Le réexamen est distinct de la décision initiale et doit préserver l'impartialité.
  - Le `super_admin_root` constitue le niveau prioritaire de réexamen.
  - En cas d'indisponibilité du `super_admin_root`, un autre `super_admin` habilité sur la coopérative peut intervenir uniquement s'il n'a participé ni au litige ni à la décision contestée.
  - L'identité du réviseur, le motif de recours à un remplaçant, les éléments examinés, la décision de réexamen et sa justification doivent être historisés et rattachés au litige initial.
  - Les critères d'indisponibilité, les délais de recours et de traitement, les motifs recevables, le nombre de recours et les effets suspensifs ou non de la contestation restent à définir.

- **Décision complémentaire — effet de la contestation sur l'exécution de la décision initiale (Question 36) :** C — le `super_admin_root` décide au cas par cas si l'application de la décision initiale doit être suspendue pendant le réexamen.
- **Conséquences / contraintes :**
  - Le dépôt d'une contestation ne suspend pas automatiquement la décision initiale.
  - Une décision explicite du `super_admin_root` est nécessaire pour suspendre son application pendant le réexamen.
  - La décision de suspendre ou de maintenir l'exécution, son motif, son auteur, sa date et sa portée doivent être historisés et rattachés au litige et à la contestation.
  - Les critères d'octroi ou de refus de la suspension, les mesures provisoires éventuelles, les délais de réexamen et les conséquences sur les flux financiers, les stocks ou les retours restent à définir.

- **Décision complémentaire — délai de contestation par le client (Question 37) :** A — le client dispose de 48 heures après la notification de la décision pour déposer sa contestation.
- **Conséquences / contraintes :**
  - Le délai de contestation court à partir de la notification de la décision au client.
  - Le dépôt doit être horodaté afin de vérifier le respect du délai de 48 heures.
  - Le canal et la preuve de notification, le calcul précis du délai (fuseau horaire et jours calendaires ou ouvrés), le traitement des notifications non délivrées et les éventuelles exceptions restent à définir.

- **Décision complémentaire — nombre de contestations du client pour un même litige (Question 38) :** A — une seule contestation est autorisée pour un même litige.
- **Conséquences / contraintes :**
  - Le client ne peut déposer qu'une contestation dans le cadre du processus de réexamen d'une décision relative à un litige donné.
  - Le système doit empêcher le dépôt d'une seconde contestation pour ce même litige.
  - La contestation unique doit pouvoir contenir les preuves et arguments du client ; les modalités de complément éventuel du dossier avant clôture de l'instruction et les exceptions restent à définir.
  - Cette limitation s'applique au litige concerné et ne prive pas le client de ses droits concernant un autre litige.

- **Décision complémentaire — ajout de preuves après le dépôt de la contestation (Question 39) :** B — le client peut ajouter des preuves jusqu'au commencement du réexamen par le responsable désigné.
- **Conséquences / contraintes :**
  - Le dossier de contestation reste complétable après son dépôt initial, mais uniquement avant le commencement effectif du réexamen.
  - MAHLINE doit enregistrer chaque ajout avec son auteur et son horodatage, et rattacher les pièces à la contestation unique du litige.
  - Le système doit matérialiser le commencement du réexamen afin de fermer ensuite la possibilité d'ajout par le client.
  - La définition de l'événement qui marque le début du réexamen, l'information du client sur cette clôture, les formats et limites des pièces, ainsi que les éventuelles demandes de complément par le responsable restent à préciser.

- **Décision complémentaire — information du client au début du réexamen (Question 40) :** C — notification dans l'espace client et par e-mail.
- **Décision complémentaire — échec d'envoi de l'e-mail (Question 41) :** A — la notification dans l'espace client suffit ; le réexamen continue même si l'e-mail ne peut pas être envoyé.
- **Conséquences / contraintes :**
  - Au commencement effectif du réexamen, MAHLINE doit créer une notification dans l'espace client et tenter l'envoi d'un e-mail.
  - La notification dans l'espace client constitue le canal de référence : son émission suffit pour informer le client et permettre au réexamen de continuer.
  - Un échec d'envoi de l'e-mail ne bloque pas le réexamen et n'impose pas de nouvelle tentative automatique selon la règle retenue.
  - La notification doit permettre au client de savoir que le réexamen a commencé et que l'ajout de preuves par lui-même est désormais fermé.
  - L'événement de début du réexamen, la création de la notification interne, la tentative d'e-mail, son résultat et leurs horodatages doivent être traçables.
  - Le contenu exact du message, les règles de conservation des échecs d'envoi et les éventuelles notifications complémentaires restent à préciser.

- **Décision complémentaire — événement marquant le début du réexamen (Question 42) :** A — le réexamen commence dès que le responsable ouvre le dossier du litige.
- **Conséquences / contraintes :**
  - L'ouverture du dossier par le responsable désigné constitue l'événement officiel de début du réexamen.
  - À cet instant, MAHLINE doit horodater le début du réexamen et fermer la possibilité pour le client d'ajouter de nouvelles preuves à sa contestation.
  - Cette ouverture déclenche la notification dans l'espace client et la tentative d'envoi de l'e-mail ; l'échec de l'e-mail ne bloque pas le réexamen.
  - Le système doit distinguer l'ouverture du dossier par le responsable de sa simple consultation par d'autres acteurs non responsables.
  - La définition technique de l'action d'ouverture, les ouvertures accidentelles, les réouvertures et les éventuels changements de responsable restent à préciser.

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

- **Question 82 — Historique des modifications d'un export :** réponse D — le `super_admin_root` décide au cas par cas si l'historique des modifications doit être conservé. La décision et son motif doivent être traçables. Les modalités exactes de journalisation restent à définir.

### Version 0.50 — 12 novembre 2026
- Complément de FR-009 — Question 51 : D — la décision définitive après réexamen est communiquée au client, à la coopérative concernée, au transporteur impliqué et au `super_admin` responsable. Les informations transmises doivent respecter les droits et le besoin d'en connaître de chaque acteur ; canaux, contenu détaillé et preuve de notification restent à définir.

### Version 0.49 — 12 novembre 2026
- Complément de FR-009 — Question 50 : D — le moment d'application de la décision définitive dépend de sa nature (confirmation, modification ou annulation). Les modalités et le moment d'effet propres à chacune des trois issues restent à définir ; aucune règle uniforme n'est supposée.

### Version 0.48 — 12 novembre 2026
- Complément de FR-009 — Question 49 : A — la décision rendue après réexamen est définitive et ne peut faire l'objet d'une seconde contestation du client. MAHLINE doit empêcher une nouvelle contestation du même litige ; la traçabilité de la décision finale et de sa notification reste obligatoire. Le traitement d'une éventuelle erreur matérielle ou irrégularité exceptionnelle reste hors décision.

### Version 0.47 — 12 novembre 2026
- Complément de FR-009 — Question 48 : D — après réexamen, le réviseur peut confirmer intégralement, modifier ou annuler la décision initiale selon les preuves et les circonstances. L'issue, sa motivation, le réviseur, l'horodatage et les éléments examinés doivent être historisés ; les effets opérationnels et financiers restent à cadrer.

### Version 0.46 — 12 novembre 2026
- Complément de FR-009 — Question 47 : C — après chaque réactivation, le client peut ajouter des preuves sans délai fixe jusqu'à la prochaine ouverture du dossier par le responsable désigné. Cette ouverture clôt le dépôt et démarre le réexamen. Aucune nouvelle échéance de 48 heures n'est créée ; les mesures contre les dépôts abusifs ou disproportionnés restent à définir.

### Version 0.45 — 12 novembre 2026
- Complément de FR-009 — Question 46 : D — aucune notification dédiée n'est envoyée au client lorsque le `super_admin_root` annule une ouverture accidentelle et réactive l'ajout de preuves. Le client constate lui-même la disponibilité du dépôt ; les notifications déjà prévues au début effectif du réexamen restent inchangées.

### Version 0.44 — 12 novembre 2026
- Complément de FR-009 — Question 45 : B — le `super_admin_root` peut annuler plusieurs fois une ouverture accidentelle du réexamen, sans limite numérique prédéfinie, avec justification obligatoire et traçabilité complète à chaque annulation. Après chaque annulation, l'ajout de preuves client est réactivé jusqu'à la prochaine ouverture du dossier par le responsable désigné. Les mesures de contrôle des abus éventuels, la notification de réactivation et une éventuelle nouvelle échéance restent à préciser.

### Version 0.43 — 12 novembre 2026
- Complément de FR-009 — Question 44 : A — après annulation du début du réexamen par le `super_admin_root`, l'ajout de preuves par le client est automatiquement réactivé jusqu'à la prochaine ouverture du dossier par le responsable désigné. Le nombre d'annulations, la notification au client et l'éventuelle nouvelle échéance restent à préciser.

### Version 0.42 — 12 novembre 2026
- Complément de FR-009 — Question 43 : C — seul le `super_admin_root` peut annuler le début du réexamen en cas d'ouverture accidentelle du dossier par le responsable désigné ; une justification est obligatoire et l'action doit être entièrement tracée. Les conséquences sur la possibilité d'ajouter de nouvelles preuves, la notification du client et la reprise du réexamen restent à définir.

### Version 0.41 — 12 novembre 2026
- Complément de FR-009 — Question 42 : A — l'ouverture du dossier par le responsable désigné marque officiellement le début du réexamen. Cet événement ferme l'ajout de preuves par le client, déclenche la notification dans l'espace client et la tentative d'e-mail, et doit être horodaté. Les ouvertures accidentelles, réouvertures et changements de responsable restent à cadrer.

### Version 0.40 — 12 novembre 2026
- Complément de FR-009 — Question 41 : A — la notification dans l'espace client suffit ; le réexamen continue même si l'envoi de l'e-mail échoue. L'émission de la notification interne, la tentative d'e-mail, son résultat et les horodatages doivent rester traçables.

### Version 0.39 — 12 novembre 2026
- Complément de FR-009 — Question 40 : C — au début effectif du réexamen, le client est informé dans son espace client et par e-mail. L'événement de début et les notifications doivent être traçables ; le contenu du message et le traitement des échecs d'envoi restent à préciser.

### Version 0.38 — 12 novembre 2026
- Complément de FR-009 — Question 39 : B — le client peut ajouter des preuves après le dépôt de sa contestation, jusqu'au commencement effectif du réexamen par le responsable désigné. Chaque ajout est horodaté et rattaché au dossier unique. L'événement marquant le début du réexamen, la notification de clôture et les modalités des pièces restent à définir.

### Version 0.37 — 12 novembre 2026
- Complément de FR-009 — Question 38 : A — une seule contestation du client est autorisée pour un même litige. Le système doit empêcher une seconde contestation du même litige. La contestation unique peut réunir les preuves et arguments du client ; les modalités de complément du dossier et les éventuelles exceptions restent à définir.

### Version 0.36 — 12 novembre 2026
- Complément de FR-009 — Question 37 : le client dispose de 48 heures après notification de la décision pour déposer une contestation. Le dépôt doit être horodaté. Le canal et la preuve de notification, le calcul précis du délai, les notifications non délivrées et les exceptions restent à définir.

### Version 0.35 — 12 novembre 2026
- Complément de FR-009 — Question 36 : la contestation du client ne suspend pas automatiquement la décision initiale. Le `super_admin_root` décide au cas par cas de suspendre ou non son application pendant le réexamen. L'auteur, le motif, la date et la portée de cette décision doivent être historisés ; les critères de suspension et les conséquences opérationnelles restent à définir.

### Version 0.34 — 12 novembre 2026
- Complément de FR-009 — Question 35 : la contestation du client est réexaminée en priorité par le `super_admin_root` ; s'il est indisponible, un autre `super_admin` habilité sur la coopérative et non impliqué dans le litige ni dans la décision initiale peut intervenir. Le réviseur, le motif de remplacement, les éléments examinés et la décision motivée doivent être historisés. Critères d'indisponibilité, délais, motifs recevables, nombre de recours et effets de la contestation restent à définir.

### Version 0.33 — 12 novembre 2026
- Complément de FR-009 — Question 34 : le client peut contester une décision rendue sur un litige et fournir des preuves à l'appui. La contestation et ses pièces doivent être historisées et rattachées au litige initial ; l'autorité de réexamen, les délais, les motifs recevables, le nombre de recours et les effets sur l'exécution de la décision restent à définir.

### Version 0.32 — 12 novembre 2026
- Complément de FR-009 — Question 33 : si le `super_admin` responsable est impliqué dans le litige ou conteste la décision, le `super_admin_root` reprend le dossier en priorité ; s'il ne peut pas intervenir, un autre `super_admin` habilité sur la coopérative et non impliqué peut l'examiner. Le motif du transfert, le nouveau responsable et les décisions doivent être historisés ; les critères d'indisponibilité et garanties de neutralité restent à préciser.

### Version 0.31 — 12 novembre 2026
- Précision de FR-009 — Question 32 : le `super_admin` qui prend en charge la coopérative concernée est le responsable du traitement du litige lié à cette coopérative. Si aucun `super_admin` ne la prend en charge, le litige reste en attente. L'escalade en cas d'absence durable de responsable reste à définir.

### Version 0.30 — 12 novembre 2026
- Complément de FR-009 — Question 31 : si aucun `super_admin` n'est habilité sur la coopérative concernée, le litige reste en attente jusqu'à la désignation d'un `super_admin` habilité. Le responsable de la désignation, le délai maximal et l'escalade restent à préciser.

### Version 0.29 — 12 novembre 2026
- Complément de FR-009 — Question 30 : la décision finale sur un litige relève d'un `super_admin` habilité à gérer la coopérative concernée. L'accès au dossier et le pouvoir de décision doivent être limités à cette habilitation et la décision doit être historisée ; les règles d'habilitation, conflits d'intérêts, délais et contestations restent à préciser.

### Version 0.28 — 12 novembre 2026
- Complément de FR-009 — Question 29 : en cas de désaccord entre la coopérative et le transporteur sur la responsabilité d'un dommage, le dossier est placé en litige et nécessite une intervention humaine avant toute décision finale. MAHLINE ne tranche pas automatiquement ; l'acteur habilité à décider et la procédure restent à définir.

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
