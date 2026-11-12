# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 0.77
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
