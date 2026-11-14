# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 4.24
- **Date d'ouverture :** 12 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** EN COURS — décisions fonctionnelles recueillies progressivement
- **Périmètre :** premier release de MAHLINE (SaaS de gestion coopérative + marketplace publique)

> Ce document est le registre de référence des questions, réponses et décisions fonctionnelles prises pendant le cadrage. Chaque réponse validée doit être ajoutée ici avant de poursuivre. Les points non encore posés ou non validés restent ouverts ; aucune règle ne doit être supposée.

## 1. Objectif du premier release

Construire une première version exploitable de MAHLINE qui combine :
- un SaaS permettant aux coopératives de gérer leur activité ;
- une marketplace publique permettant aux clients de découvrir les coopératives et leurs produits et de commander ;
- un parcours cohérent entre la marketplace et les espaces de gestion des coopératives.

La cohérence approfondie des frontières entre domaines et le renforcement global des événements transversaux sont planifiés dans une phase ultérieure dédiée, après le développement fonctionnel des domaines du premier release. Les règles indispensables de sécurité, d'autorisation, de propriété des données et d'intégrité restent obligatoires pendant chaque développement.


## Décision de stratégie de livraison — Parcours opérationnel prioritaire MAHLINE V1 (Q252 — VALIDÉE)

- **Question :** Faut-il adopter une stratégie de développement priorisant le parcours complet client → coopérative → transporteur, sans attendre la spécification exhaustive de toutes les fonctions avancées de chaque domaine ?
- **Réponse :** A — oui. Priorité au parcours complet client → coopérative → transporteur ; les autres fonctionnalités seront développées progressivement.
- **Décision :** le critère principal de priorité du premier release est la livraison d'un parcours métier vertical réellement utilisable : un client peut créer/ouvrir son compte, consulter les produits publiés, constituer son panier et passer une commande ; la ou les coopératives concernées reçoivent leurs sous-commandes, les examinent, peuvent proposer une modification de quantité, valident la commande selon les règles de Sales/Inventory, préparent la remise et la confient au transporteur ; le transporteur prend en charge l'expédition et communique les événements de suivi nécessaires.
- **Principe de cadrage :** il n'est pas nécessaire de terminer l'audit exhaustif de toutes les fonctionnalités avancées de tous les domaines avant de commencer le développement du parcours prioritaire. En revanche, chaque étape doit disposer de ses règles métier indispensables, autorisations, états, exceptions, contrats inter-domaines, critères d'acceptation et tests avant son implémentation.
- **Finance V1 minimale :** Finance peut être développée sans finaliser immédiatement toutes ses fonctions avancées. Le périmètre initial doit au minimum enregistrer les montants de commande et leurs ventilations par coopérative, les frais de livraison, le mode COD, les montants à encaisser, les informations d'encaissement/remise communiquées par Delivery, les commissions applicables et les références financières/documents indispensables. Les rapprochements complexes, contestations avancées, crédits et recouvrements complexes, automatisation complète des reversements et rapports avancés peuvent être spécifiés et livrés progressivement, sans perdre la traçabilité des opérations V1.
- **Phasage fonctionnel recommandé :**
  1. Socle d'accès et acteurs : Identity, comptes customer, Cooperatives, rôles, permissions et espaces nécessaires.
  2. Offre marketplace : Catalog, Categories, Products, publication, consultation et disponibilité.
  3. Parcours commercial : Sales, profil commercial, adresses via Localization, panier, checkout, commande multi-coopératives et sous-commandes.
  4. Traitement coopérative : réception, examen, proposition de changement de quantité, décision du client lorsque requise, confirmation, réservation de stock via Inventory et préparation.
  5. Expédition : Delivery, transporteur, prise en charge, statuts, preuve de remise et suivi client.
  6. Finance minimale : enregistrement des montants, frais, COD, commission figée, encaissements et références de reversement nécessaires au suivi du parcours.
  7. Capacités de soutien : Media et Communication pour les documents, preuves, e-mails et notifications strictement nécessaires au parcours.
- **Frontières conservées :** Sales reste propriétaire du panier, du checkout et des commandes commerciales ; Inventory du stock et des réservations ; Delivery des expéditions, transporteurs et événements de livraison/encaissement ; Finance des écritures, commissions, fonds, rapprochements et reversements marketplace ; Subscriptions / SaaS Billing reste séparé de Finance marketplace.
- **Règle de non-régression :** les exigences de sécurité, autorisation serveur, isolation des coopératives, audit, intégrité, versionnement et idempotence restent obligatoires même dans le périmètre minimal.
- **Point métier non tranché par Q252 :** le traitement exact d'une diminution de quantité proposée par la coopérative, notamment la nécessité et le délai d'acceptation du client avant confirmation définitive, doit faire l'objet d'une question dédiée avant codage de cette branche du workflow.
- **Conséquence sur le planning :** l'ordre de développement antérieur est remplacé par le phasage vertical prioritaire décrit dans la feuille de route ; les fonctions non indispensables au parcours sont conservées dans le backlog et ne sont ni supprimées ni réputées validées.
- **Statut :** stratégie V1 validée ; spécifications détaillées, audits techniques et tests restent à réaliser par étape.

## Décision d'architecture — Compte client de la marketplace (VALIDÉE)

- **Question :** Le compte obligatoire du client marketplace doit-il être géré dans Identity, dans un domaine Customer autonome avec authentification séparée, ou via le rôle interne `user` ?
- **Réponse :** A — ajouter un rôle `customer` dans Identity.
- **Décision :** le client marketplace possède un compte géré par le domaine Identity, avec un rôle `customer` distinct des quatre rôles internes existants (`super_admin_root`, `super_admin`, `cooperative_admin`, `user`).
- **Conséquences :**
  - Le socle d'authentification, de sessions, de sécurité et de gestion du cycle de vie des comptes est partagé avec Identity ; aucun système d'authentification parallèle n'est créé.
  - Le rôle `customer` dispose de permissions et d'un espace client propres, sans hériter des droits d'administration des coopératives.
  - Le rôle interne `user` conserve sa signification actuelle et n'est pas réutilisé pour les acheteurs de la marketplace.
  - Les données métier du client et ses parcours d'achat (profil client, adresses, panier, commandes et historique) doivent être répartis selon les responsabilités à spécifier entre Identity, Localization et Sales ; cette décision ne fait pas de Sales un second système de comptes.
  - Les politiques d'accès doivent empêcher un client d'accéder aux données d'un autre client ou aux espaces internes des coopératives.
- **Périmètre concerné :** Identity, Sales et Localization ; rôles, permissions, inscription, authentification, profil client et parcours marketplace.
- **Points restant à préciser :** champs du profil client, vérification de l'adresse e-mail, récupération du compte, consentements, suppression/anonymisation et éventuelle conversion d'un compte client en compte interne (aucune conversion automatique n'est autorisée par cette décision).

## Décision d'architecture — Répartition des informations du profil client (VALIDÉE)

- **Question :** Où gérer les informations du profil d'un client marketplace ?
- **Réponse :** A — Identity gère l'identité et Sales gère le profil commercial.
- **Décision :** séparer les données d'identité et de sécurité du compte des données métier nécessaires aux achats.
- **Répartition :**
  - **Identity :** identité du compte, nom, prénom, adresse e-mail, téléphone et éléments liés à l'authentification et à la sécurité.
  - **Sales :** profil commercial du client, préférences et informations utiles au parcours d'achat, ainsi que le lien métier avec paniers et commandes.
  - **Localization :** référentiel et gestion technique des adresses structurées ; Sales en définit l'usage commercial (notamment les adresses de livraison). L'adresse postale ne doit pas être dupliquée dans Identity comme donnée de sécurité.
- **Conséquences :**
  - Identity reste le propriétaire de l'identité et du cycle de vie du compte customer.
  - Sales ne crée pas de mécanisme d'authentification ni de mot de passe parallèle.
  - Les commandes et leur historique restent dans Sales ; Identity ne devient pas propriétaire des données commerciales.
  - Les accès aux profils, adresses, paniers et commandes doivent être limités au client concerné et aux acteurs internes autorisés selon leur périmètre.
- **Périmètre concerné :** Identity, Sales et Localization.
- **Points restant à préciser :** nombre maximal d'adresses autorisées, validation du téléphone et du nom du destinataire, consentements et politique de conservation/anonymisation. Le principe de plusieurs adresses et d'une adresse par défaut est validé par Q288 ; les coordonnées propres au destinataire par adresse sont validées par Q289.

### Question 4 — Inscription d'un client marketplace (VALIDÉ)
- **Question :** Comment un client marketplace doit-il créer son compte lors du premier release ?
- **Réponse :** A — auto-inscription par le client avec nom, prénom, adresse e-mail, téléphone et mot de passe ; l'adresse e-mail doit être vérifiée avant toute commande.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et espace client ; parcours d'inscription, vérification d'adresse e-mail et autorisation de commander.
- **Conséquences :**
  - Le client peut créer lui-même un compte avec les informations minimales définies.
  - Le compte relève du rôle distinct `customer` ; il utilise le système commun d'authentification et de sécurité d'Identity.
  - La vérification de l'adresse e-mail est obligatoire avant de pouvoir passer une commande.
  - L'inscription ne nécessite pas d'approbation manuelle préalable par un administrateur MAHLINE.
  - Les règles détaillées de validation des champs, de confirmation du téléphone, de limitation anti-abus et de récupération du compte restent à cadrer.

### Question 5 — Capacités du client avant vérification de l'e-mail (VALIDÉ)
- **Question :** Que peut faire un client avant de vérifier son adresse e-mail ?
- **Réponse :** A — il peut naviguer sur la marketplace et ajouter des produits au panier, mais il ne peut pas confirmer une commande avant vérification.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Sales ; état du compte client, navigation marketplace, panier et contrôle du checkout.
- **Conséquences :**
  - Un compte client dont l'adresse e-mail n'est pas encore vérifiée peut accéder à la marketplace.
  - Il peut consulter les produits et constituer son panier.
  - La confirmation et la création d'une commande sont bloquées tant que l'adresse e-mail n'est pas vérifiée.
  - Le contrôle doit être appliqué côté serveur au moment du checkout, et pas uniquement dans l'interface.
  - Les règles d'expiration et de renvoi du lien de vérification restent à préciser.

### Question 6 — Vérification de l'adresse e-mail du client (VALIDÉ)
- **Question :** Par quel mécanisme le client vérifie-t-il son adresse e-mail lors de l'inscription ?
- **Réponse :** A — lien sécurisé envoyé par e-mail ; le client clique sur ce lien pour vérifier son adresse. Le lien expire après 24 heures et un nouvel envoi est possible.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; génération, envoi, validation, expiration et renvoi du lien de vérification.
- **Conséquences :**
  - Le mécanisme de vérification repose sur un lien individuel sécurisé envoyé à l'adresse e-mail déclarée.
  - Le lien est à usage contrôlé et expire 24 heures après son émission.
  - Le client peut demander un nouvel envoi lorsque le lien a expiré ou n'est plus disponible.
  - Un lien expiré ou invalide ne doit pas permettre de vérifier l'adresse.
  - Les règles de limitation des renvois, d'invalidation des anciens liens et de journalisation restent à préciser.

### Question 7 — Gestion des liens de vérification successifs (VALIDÉ)
- **Question :** Que se passe-t-il si le client demande plusieurs liens de vérification de son adresse e-mail ?
- **Réponse :** A — l'émission d'un nouveau lien invalide immédiatement tous les liens précédemment émis ; un seul lien reste utilisable.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; gestion des jetons de vérification et renvoi des e-mails.
- **Conséquences :**
  - Un seul lien de vérification est actif par compte à un instant donné.
  - La création d'un nouveau lien invalide immédiatement le précédent, même si sa période de validité de 24 heures n'est pas écoulée.
  - Seul le lien le plus récemment émis peut vérifier l'adresse e-mail.
  - Un ancien lien ne peut jamais être réutilisé après l'émission d'un nouveau lien.
  - Les limites de fréquence des demandes et les messages affichés au client restent à préciser.

### Question 8 — Limitation des demandes de renvoi du lien (VALIDÉ)
- **Question :** Quelle limite appliquer aux demandes de renvoi du lien de vérification d'e-mail ?
- **Réponse :** B — maximum 5 demandes par heure, puis blocage temporaire pendant 1 heure.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; endpoint de renvoi, protection anti-abus et retour utilisateur.
- **Conséquences :**
  - Le client peut demander au maximum 5 renvois du lien de vérification pendant une fenêtre d'une heure.
  - Lorsque la limite est atteinte, les nouvelles demandes sont temporairement bloquées pendant une heure.
  - La limite s'applique côté serveur et ne peut pas être contournée en répétant les appels depuis l'interface.
  - Les compteurs et blocages doivent être gérés sans journaliser de jetons ou de données secrètes.
  - Le périmètre exact de comptage (compte, adresse IP et combinaison des deux) devra être précisé lors de la conception technique.

### Question 9 — Modification de l'adresse e-mail du client (VALIDÉ)
- **Question :** Le client peut-il modifier l'adresse e-mail de son compte depuis son espace client ?
- **Réponse :** A — oui ; la nouvelle adresse doit être vérifiée avant de remplacer l'ancienne. L'ancienne adresse reste celle du compte tant que la nouvelle n'est pas validée.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; modification de l'adresse e-mail, vérification et maintien de l'accès au compte.
- **Conséquences :**
  - Le client peut initier une demande de changement d'adresse e-mail depuis son espace client.
  - La nouvelle adresse doit être vérifiée à l'aide du mécanisme de vérification sécurisé avant de devenir l'adresse principale du compte.
  - Jusqu'à la vérification, l'ancienne adresse reste l'adresse principale et conserve son statut.
  - Une adresse non vérifiée ne peut pas être utilisée comme nouvelle adresse principale pour l'authentification et les communications du compte.
  - Les règles de révocation des sessions et tokens, de notification de l'ancienne adresse et de prévention des changements répétés restent à préciser dans le cadrage Identity.

### Question 10 — Révocation des accès après changement d'adresse e-mail (VALIDÉ)
- **Question :** Que doit-il se passer avec les sessions ouvertes et les tokens API lorsqu'un client valide une nouvelle adresse e-mail ?
- **Réponse :** A — révoquer toutes les sessions et tous les tokens API ; le client doit se reconnecter.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; changement d'adresse e-mail, sessions web, tokens API et sécurité du compte.
- **Conséquences :**
  - Dès que la nouvelle adresse e-mail est vérifiée et devient l'adresse principale, toutes les sessions web actives du compte sont révoquées.
  - Tous les tokens API actifs du compte sont également révoqués.
  - Le client doit s'authentifier à nouveau avec ses nouveaux identifiants.
  - La révocation doit être exécutée côté serveur et être cohérente même si plusieurs appareils sont connectés.
  - L'opération et les révocations doivent être journalisées sans enregistrer de jetons, secrets ou données sensibles.
  - Les règles de notification de l'ancienne adresse et de gestion d'un échec partiel de révocation restent à détailler dans la conception technique.

### Question 11 — Notification de sécurité à l'ancienne adresse e-mail (VALIDÉ)
- **Question :** Après validation d'une nouvelle adresse e-mail, faut-il notifier l'ancienne adresse ?
- **Réponse :** A — oui, envoyer une notification de sécurité à l'ancienne adresse pour signaler le changement, sans communiquer la nouvelle adresse.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Communication ; événement de changement d'adresse et notification de sécurité.
- **Conséquences :**
  - Une notification est envoyée à l'ancienne adresse après la validation effective de la nouvelle adresse.
  - Le message signale qu'un changement d'adresse e-mail a été effectué sur le compte.
  - La notification ne révèle pas la nouvelle adresse e-mail.
  - La notification est informative et ne doit contenir aucun mot de passe, jeton, lien secret ou autre donnée d'authentification.
  - Le contenu du message, le canal de secours en cas d'échec d'envoi et le mécanisme de signalement d'un changement non autorisé restent à préciser.

### Question 12 — Signalement d'un changement d'e-mail non reconnu (VALIDÉ)
- **Question :** Que doit contenir la notification envoyée à l'ancienne adresse si le client ne reconnaît pas le changement d'adresse e-mail ?
- **Réponse :** A — un lien sécurisé permettant de signaler un changement non autorisé et de démarrer une procédure de sécurisation du compte.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Communication ; notification de sécurité, signalement d'incident et protection du compte.
- **Conséquences :**
  - La notification envoyée à l'ancienne adresse contient un lien sécurisé permettant au destinataire de signaler qu'il n'est pas à l'origine du changement.
  - Ce parcours déclenche une procédure de sécurisation du compte et de traitement de l'incident.
  - Le lien ne doit pas permettre, à lui seul, de remplacer automatiquement l'adresse e-mail ou de restaurer des identifiants sans contrôles supplémentaires.
  - Le mécanisme doit être à durée limitée, à usage contrôlé et protégé contre les abus ; ses modalités techniques, les vérifications d'identité et les actions de sécurisation seront précisées pendant la conception détaillée.
  - La notification ne révèle pas la nouvelle adresse e-mail et ne contient aucun mot de passe, token ou secret d'authentification.

### Question 13 — Actions après signalement d'un changement d'e-mail non autorisé (VALIDÉ)
- **Question :** Quelles actions déclencher lorsqu'un client signale qu'il n'est pas à l'origine du changement d'adresse e-mail ?
- **Réponse :** A — bloquer temporairement le compte, révoquer toutes les sessions et tous les tokens, puis lancer une procédure de récupération sécurisée.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Communication ; traitement des incidents de sécurité, protection du compte et récupération d'accès.
- **Conséquences :**
  - Le signalement déclenche un blocage temporaire du compte afin de limiter toute utilisation non autorisée pendant le traitement.
  - Toutes les sessions web actives et tous les tokens API du compte sont révoqués.
  - Une procédure de récupération sécurisée est engagée pour vérifier le propriétaire légitime et rétablir son accès.
  - Le signalement ne provoque pas de restauration automatique de l'ancienne adresse e-mail ; toute correction d'identité doit passer par la procédure de récupération et ses contrôles.
  - La durée du blocage, les étapes de vérification, les critères de déblocage et les notifications associées restent à définir dans la conception détaillée.
  - L'incident et les actions de sécurité sont journalisés sans enregistrer de mot de passe, token, lien secret ou autre donnée d'authentification.

### Question 14 — Autorisation de déblocage après signalement de sécurité (VALIDÉ)
- **Question :** Qui peut débloquer le compte après un signalement de changement d'e-mail non autorisé ?
- **Réponse :** A — le compte est débloqué uniquement après vérification sécurisée de l'identité du client, selon une procédure automatisée ou assistée par le support.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Communication ; récupération de compte, vérification d'identité, gestion des incidents et déblocage.
- **Conséquences :**
  - Le déblocage dépend de la réussite des contrôles de récupération et de la vérification du propriétaire légitime ; il ne dépend pas uniquement de l'écoulement d'un délai.
  - La récupération peut être automatisée lorsque les preuves et contrôles requis sont suffisants.
  - Le support peut assister le client lorsque la procédure automatisée ne permet pas de conclure, selon un processus contrôlé et traçable.
  - Aucun déblocage automatique fondé uniquement sur une durée fixe n'est autorisé.
  - Les preuves acceptées, étapes de contrôle, habilitations du support, validation finale et modalités de déblocage restent à détailler dans la conception technique.
  - Chaque action de récupération et de déblocage doit être journalisée sans stocker de secrets d'authentification.

### Question 15 — Récupération du compte sans accès aux deux adresses e-mail (VALIDÉ)
- **Question :** Quelle procédure prévoir si le client n'a accès ni à l'ancienne ni à la nouvelle adresse e-mail ?
- **Réponse :** A — procédure de récupération renforcée avec vérification d'identité par le support, sans dépendre de l'accès à ces deux adresses.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity et Communication ; récupération de compte, vérification d'identité et assistance support.
- **Conséquences :**
  - L'absence d'accès aux deux adresses e-mail ne bloque pas définitivement toute récupération du compte.
  - Une procédure renforcée permet au client de demander une récupération avec assistance du support, sans utiliser l'accès à l'ancienne ou à la nouvelle adresse comme preuve suffisante ou obligatoire.
  - Le support doit vérifier l'identité et la légitimité de la demande au moyen de preuves et contrôles renforcés avant toute modification des coordonnées ou tout déblocage.
  - Aucune information personnelle simple ou facilement devinable ne suffit à elle seule pour récupérer le compte.
  - Les preuves acceptées, niveaux de contrôle, habilitations, validations, délais et notifications alternatives restent à définir dans la conception détaillée.
  - Toutes les étapes et décisions doivent être journalisées sans conserver de mots de passe, tokens, codes ou secrets d'authentification.

### Question 16 — Historique des demandes de récupération de compte (VALIDÉ)
- **Question :** Faut-il conserver une trace des demandes de récupération de compte ?
- **Réponse :** A — conserver un historique de sécurité complet : date, motif, statut, étapes de vérification, intervenant support et décision finale, sans enregistrer les documents sensibles ni les secrets.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity, Audit et Communication ; incidents de sécurité, récupération de compte et traçabilité des interventions.
- **Conséquences :**
  - Chaque demande de récupération donne lieu à un historique retraçant sa date, son motif, son statut, les étapes de vérification réalisées, l'intervenant support et la décision finale.
  - L'historique permet de suivre les demandes répétées et d'auditer les actions effectuées pendant la récupération.
  - Les documents d'identité, pièces justificatives, mots de passe, tokens, codes, liens secrets et autres données sensibles ne sont pas copiés dans les journaux d'audit.
  - Les données strictement nécessaires à la traçabilité sont protégées par des accès autorisés et une politique de conservation à définir.
  - Les règles de consultation, de rectification éventuelle, de conservation et de purge seront précisées lors de la conception détaillée, sans altérer l'intégrité de la piste d'audit.

### Question 17 — Accès à l'historique des demandes de récupération (VALIDÉ)
- **Question :** Qui peut consulter l'historique des demandes de récupération de compte ?
- **Réponse :** B — `super_admin_root` et les agents du support autorisés, chacun selon ses permissions.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity, Audit et Communication ; consultation des incidents et traitement des demandes de récupération.
- **Conséquences :**
  - `super_admin_root` conserve l'accès complet à l'historique et aux événements de sécurité associés.
  - Les agents du support explicitement autorisés peuvent consulter les seules informations nécessaires au traitement des demandes qui leur sont accessibles, selon des permissions dédiées et limitées.
  - Les agents du support ne disposent pas, du seul fait de leur rôle, d'un accès global aux journaux d'audit de sécurité ni aux dossiers des autres clients.
  - Les consultations et actions sensibles effectuées par le support sont elles-mêmes journalisées.
  - Les habilitations précises, le périmètre des dossiers accessibles et les règles de masquage des informations sensibles seront détaillés dans la conception technique.

### Question 18 — Notification du client lors des consultations de son dossier de récupération

**Décision validée : C**

- Le client est notifié à l'ouverture de son dossier de récupération et à sa clôture.
- Aucune notification n'est envoyée à chaque consultation du dossier ou à chaque action interne du support.
- La traçabilité complète des consultations et actions reste conservée ; l'accès complet à cette traçabilité demeure réservé à `super_admin_root`, conformément à la Question 17.
- Les agents du support autorisés conservent uniquement leur accès limité aux informations et dossiers nécessaires à leur mission.
- Les événements d'ouverture et de clôture, leurs destinataires et le contenu exact des notifications seront précisés dans la conception technique et le cadrage Communication.

### Question 19 — Contenu des notifications envoyées au client pour son dossier de récupération

**Décision validée : C**

- Les e-mails envoyés à l'ouverture et à la clôture du dossier contiennent uniquement les informations minimales nécessaires, la référence du dossier et un lien sécurisé vers l'espace client.
- Les détails du motif, des vérifications d'identité, des étapes de traitement et du résultat ne sont pas exposés dans l'e-mail.
- Ces informations détaillées sont consultables dans l'espace client après authentification, selon les droits d'accès applicables.
- Le lien contenu dans l'e-mail ne doit pas donner un accès direct au dossier ni contourner l'authentification.
- Le contenu exact des notifications, les règles de durée de validité et la destination du lien seront précisés dans la conception technique et le cadrage Communication.

### Question 20 — Suivi de l'avancement du dossier de récupération par le client

**Décision validée : C**

- Le client peut suivre l'avancement de son dossier depuis son espace après authentification.
- Le suivi présente les différentes étapes du traitement, leur date et leur statut, ainsi que des informations complémentaires non sensibles utiles à la compréhension de l'avancement.
- Les étapes visibles couvrent notamment : demande reçue, vérification d'identité, examen par le support, décision et clôture.
- Les notes internes du support, les éléments de sécurité sensibles, les preuves confidentielles et les informations concernant les agents ne sont pas exposés au client.
- La liste définitive des statuts, les transitions autorisées et les règles de visibilité seront précisées dans la conception technique.

### Question 21 — Ajout d'informations par le client après l'ouverture du dossier de récupération

**Décision validée : C**

- Le client peut compléter son dossier uniquement lorsqu'un agent du support autorisé lui demande explicitement des informations ou justificatifs complémentaires.
- La demande de complément précise les éléments attendus et un délai de réponse.
- Les informations et justificatifs transmis sont rattachés au dossier et leur réception est historisée.
- Le client ne peut pas modifier librement le dossier ni ajouter des éléments en dehors d'une demande de complément active.
- Les règles de délai, de relance, d'expiration de la demande et de traitement en cas d'absence de réponse seront précisées dans la conception technique.
- Les justificatifs éventuels sont soumis aux règles de sécurité, de confidentialité, de conservation et d'accès du domaine concerné.

### Question 22 — Conséquence de l'absence de réponse du client à une demande de complément

**Décision validée : A**

- Si le client ne transmet pas les informations ou justificatifs demandés avant l'expiration du délai indiqué, le dossier de récupération est clôturé automatiquement.
- La clôture automatique est déclenchée à l'échéance de la demande de complément, selon le délai communiqué au client.
- La date d'échéance, l'absence de réponse et la clôture automatique sont historisées dans le dossier.
- Le client reçoit la notification de clôture prévue par la Question 18 ; l'e-mail reste minimal conformément à la Question 19 et les détails restent consultables après authentification.
- La durée applicable, le calcul de l'échéance, les éventuelles exceptions et les modalités de réouverture d'un dossier clôturé seront précisés dans la conception technique.

### Question 23 — Demande de réouverture d'un dossier de récupération clôturé automatiquement

**Décision validée : B**

- Le client peut soumettre depuis son espace une demande motivée de réouverture d'un dossier clôturé automatiquement.
- La demande de réouverture n'entraîne pas la réactivation automatique du dossier ni le déblocage du compte.
- Un agent du support autorisé examine la demande et décide de l'accepter ou de la refuser selon la procédure de vérification applicable.
- La demande, son motif, les vérifications effectuées, l'identité de l'agent et la décision finale sont historisés.
- Le client est informé de la décision par notification minimale ; les détails sont consultables après authentification conformément aux règles des Questions 18 et 19.
- Les critères d'acceptation, les délais de traitement et les conséquences d'une réouverture acceptée seront précisés dans la conception technique.

### Question 24 — Traitement du dossier après acceptation d'une demande de réouverture

**Décision validée : A**

- Lorsque le support accepte la demande de réouverture, le dossier initial est réactivé ; aucun nouveau dossier n'est créé.
- L'intégralité de l'historique antérieur est conservée, y compris la clôture automatique, la demande de réouverture, les vérifications et la décision du support.
- La reprise du traitement est ajoutée à la chronologie du même dossier avec sa date et l'agent responsable.
- La réouverture du dossier ne provoque pas, à elle seule, le déblocage du compte ; la procédure sécurisée de vérification et de déblocage reste applicable.
- Les statuts de réactivation, les transitions autorisées et les notifications associées seront précisés dans la conception technique.

### Question 25 — Information du client après acceptation de la réouverture

**Décision validée : C**

- Lorsque le support accepte la réouverture, le client reçoit automatiquement une notification minimale l'informant que son dossier initial a repris son traitement.
- La notification ne contient pas de détails sensibles ; elle renvoie vers l'espace client sécurisé sans contourner l'authentification.
- Le suivi du dossier initial est actualisé dans l'espace client avec la réouverture et les nouvelles étapes, dates et statuts visibles.
- L'événement de notification et la mise à jour du suivi sont historisés.
- Le contenu exact de la notification et les règles de mise à jour du suivi seront précisés dans la conception technique et le cadrage Communication.

### Question 26 — Contestation d'un refus de réouverture d'un dossier de récupération

**Décision validée : C**

- Le client peut contester un refus de réouverture en soumettant une demande motivée depuis son espace client.
- La contestation est examinée par un responsable du support disposant d'une habilitation dédiée ou par `super_admin_root`.
- Dans la mesure du possible, la personne chargée de l'examen est différente de l'agent ayant pris la décision initiale.
- La contestation ne réactive pas automatiquement le dossier et ne débloque pas le compte.
- Le motif de contestation, les éléments examinés, les intervenants et la décision finale sont historisés.
- Le client est informé de la décision selon les règles de notification minimale et de consultation sécurisée déjà définies.
- Les niveaux d'habilitation, les délais, les éventuels recours supplémentaires et les critères de décision seront précisés dans la conception technique.

### Question 27 — Autorité habilitée à autoriser la réouverture après acceptation d'une contestation

**Décision validée : C**

- Après acceptation d'une contestation, la réouverture peut être autorisée par un responsable du support disposant explicitement de cette permission ou par `super_admin_root`.
- L'autorisation dépend de permissions dédiées et vérifiables ; le rôle de support seul ne confère pas automatiquement ce pouvoir.
- La personne qui autorise la réouverture doit être distincte de l'agent ayant pris la décision initiale contestée, dans la mesure du possible.
- La décision d'autorisation, son auteur, sa date, son motif et les références de la contestation sont historisés.
- La réouverture réactive le dossier initial et conserve son historique, conformément à la Question 24 ; elle ne débloque pas automatiquement le compte.
- La matrice exacte des permissions, les règles de séparation des tâches et les cas d'exception seront précisés dans la conception technique.

### Question 28 — Notification du client de la décision sur sa contestation

**Décision validée : A**

- Le client reçoit automatiquement une notification minimale, que sa contestation soit acceptée ou refusée.
- La notification indique l'issue de la contestation, la référence du dossier et un lien vers l'espace client sécurisé.
- Les motifs détaillés, les vérifications et les éléments internes de décision ne sont pas exposés dans l'e-mail ; ils sont consultables dans l'espace client après authentification, selon les règles de visibilité applicables.
- L'envoi de la notification et la décision communiquée sont historisés.
- Le contenu exact des notifications et les informations affichées dans l'espace client seront précisés dans la conception technique et le cadrage Communication.

### Question 29 — Nombre de niveaux de contestation d'un refus de réouverture

**Décision validée : B**

- La procédure autorise au maximum deux niveaux de contestation pour une décision de refus de réouverture.
- Le premier niveau est examiné par un responsable du support explicitement habilité, selon les règles de séparation des tâches.
- Si le premier recours est refusé, le client peut former un second et dernier recours auprès de `super_admin_root`.
- La décision rendue par `super_admin_root` au second niveau clôt la procédure de contestation de cette décision ; aucun recours successif supplémentaire n'est prévu dans ce mécanisme.
- Chaque recours, ses motifs, les éléments examinés, les intervenants, les dates et la décision sont historisés ; le client est notifié conformément à la Question 28.
- Les délais de recours, les conditions de recevabilité et les modalités de présentation du recours final seront précisés dans la conception technique.

### Question 30 — Délai de dépôt d'une contestation après notification d'un refus

**Décision validée : A**

- Le client dispose de 7 jours calendaires à compter de la notification du refus pour déposer une contestation.
- Le délai est calculé à partir de la notification effectivement envoyée au client, selon une règle de datation et de fuseau horaire à définir techniquement.
- Le délai s'applique au premier niveau de contestation et, lorsque le premier recours est refusé, au second et dernier niveau auprès de `super_admin_root`, à compter de la notification de cette décision.
- La date de notification, la date limite et la date de dépôt de chaque recours sont historisées.
- Les modalités de calcul en cas d'échec de notification, d'indisponibilité du service ou de jour non ouvré seront précisées dans la conception technique.

### Question 31 — Traitement d'une contestation déposée après le délai de 7 jours

**Décision validée : B**

- Une contestation déposée après le délai normal de 7 jours calendaires n'est pas automatiquement recevable.
- Le client peut joindre une demande motivée expliquant les circonstances du retard et, lorsque cela est possible, les éléments qui la justifient.
- Un responsable du support disposant d'une habilitation dédiée examine la demande et décide si le retard peut être exceptionnellement accepté.
- La décision, sa motivation, les dates, les justificatifs éventuels et l'identité du décideur sont historisés dans le dossier.
- En cas d'acceptation, la contestation suit le processus normal du niveau concerné ; cette acceptation ne réactive pas automatiquement le compte et ne débloque pas l'accès.
- En cas de refus, le client est notifié selon les règles de notification déjà définies ; la décision et son motif sont consultables dans son espace authentifié.
- Les critères précis d'acceptation, les éventuelles limites d'exception et la prévention des conflits d'intérêts seront détaillés dans la conception technique.

### Question 32 — Nombre d'exceptions pour une contestation tardive

**Décision validée : B**

- Une seule exception de délai peut être accordée pour chaque niveau de contestation.
- Le client peut donc bénéficier au maximum d'une exception au premier niveau et d'une exception au second niveau, si ce second recours est ouvert.
- Plusieurs demandes tardives successives au même niveau ne peuvent pas donner lieu à plusieurs dérogations.
- Toute demande d'exception, sa motivation, la décision, son motif, les dates et l'identité du responsable habilité sont historisés.
- L'acceptation d'une exception permet uniquement l'examen de la contestation concernée ; elle ne réactive pas automatiquement le compte et ne débloque pas l'accès.
- Les règles de décompte, notamment en cas de demande retirée ou déclarée irrecevable, seront précisées dans la conception technique.

### Question 33 — Indépendance du responsable examinant une demande d'exception tardive

**Décision validée : B**

- La demande d'exception tardive peut être examinée par le même responsable habilité que celui ayant pris la décision initiale, lorsqu'aucun autre responsable habilité n'est disponible.
- Le recours à ce même responsable n'est permis que dans cette situation de disponibilité insuffisante ; il ne doit pas devenir la règle lorsque qu'un autre décideur habilité peut intervenir.
- Le motif de cette impossibilité de séparation des responsabilités doit être consigné dans le dossier.
- L'identité du demandeur, du responsable examinateur, la décision, sa motivation et les dates sont historisées.
- Les habilitations restent contrôlées : un agent non autorisé ne peut pas statuer sur une exception.
- Les modalités de preuve de l'indisponibilité d'un autre décideur seront définies dans la conception technique.

### Question 34 — Accès à l'historique des exceptions de délai

**Décision validée : B**

- `super_admin_root` dispose d'un accès complet à l'historique des demandes d'exception tardive, des décisions et de leurs justifications.
- Les responsables du support disposant d'une habilitation dédiée peuvent consulter uniquement les dossiers et informations nécessaires à leurs missions.
- Un responsable ne peut pas accéder globalement à l'ensemble des historiques du support du seul fait de son rôle opérationnel.
- Les consultations et actions sensibles sur ces historiques sont journalisées.
- Les agents non habilités ne disposent d'aucun accès à ces informations.
- Les permissions précises, les filtres par dossier et les règles d'audit seront définis dans la conception technique.

### Question 35 — Durée de conservation de l'historique des contestations et exceptions

**Décision validée : B**

- L'historique des contestations et des demandes d'exception tardive est conservé pendant 5 ans après la clôture définitive du dossier.
- La durée s'applique aux décisions, motifs, dates, identités des intervenants, événements de notification et traces de consultation nécessaires à l'audit.
- Les justificatifs contenant des données personnelles ou sensibles doivent être limités au strict nécessaire et soumis à des règles de conservation et de suppression adaptées ; leur conservation ne doit pas être prolongée automatiquement au seul motif que l'historique du dossier est conservé 5 ans.
- À l'expiration de la durée, les données sont supprimées ou anonymisées selon les obligations légales applicables et les éventuelles obligations de conservation ou de gel liées à un litige.
- Cette durée de 5 ans constitue la décision fonctionnelle MAHLINE et reste à valider au regard du droit marocain, notamment des exigences applicables en matière de protection des données personnelles et de conservation des pièces.

### Question 36 — Traitement des données à l'expiration de la durée de conservation

**Décision validée : B**

- À l'expiration des 5 ans suivant la clôture définitive, les données identifiantes du dossier et les justificatifs personnels ou sensibles sont supprimés, sous réserve des obligations légales de conservation ou d'un gel justifié lié à un litige.
- Les données strictement nécessaires à des statistiques peuvent être conservées uniquement après anonymisation irréversible, de manière à empêcher raisonnablement la réidentification du client, des agents et des autres personnes concernées.
- Les données pseudonymisées ne sont pas considérées comme anonymisées et restent soumises aux règles de protection et de conservation applicables.
- L'opération de suppression ou d'anonymisation est tracée sans conserver dans la trace des données personnelles qui devraient être supprimées.
- Les modalités techniques, les contrôles de réidentification et les exceptions légales seront précisés lors de la conception technique et de la validation juridique.

### Question 37 — Autorisation d'une prolongation de conservation au-delà de 5 ans

**Décision validée : C**

- Aucune prolongation de conservation ne peut être autorisée par simple décision discrétionnaire de `super_admin_root` ou d'un responsable du support.
- Une conservation au-delà des 5 ans n'est possible que lorsqu'une obligation légale applicable ou une décision judiciaire l'impose, ou lorsqu'un gel légalement justifié est requis dans le cadre d'un litige.
- La prolongation est limitée aux données strictement nécessaires, à la finalité concernée et à la durée imposée ou justifiée par cette obligation.
- Le fondement, le périmètre, la date de début et la date de fin ou de réexamen sont documentés et audités.
- À la fin de l'obligation ou du gel, les données sont supprimées ou anonymisées conformément à la règle de la Question 36.
- Les modalités de mise en œuvre et de preuve de ces obligations seront précisées dans la conception technique et validées juridiquement.

### Question 38 — Information du client sur la conservation de ses données de contestation

**Décision validée : C**

- La politique de confidentialité décrit les finalités, catégories de données, durées de conservation et règles de suppression ou d'anonymisation applicables aux dossiers de contestation.
- Lors de la clôture du dossier, un rappel est présenté dans l'espace client authentifié.
- Ce rappel indique la durée de conservation applicable, son point de départ, le traitement prévu à son expiration et les exceptions légales possibles.
- Le rappel ne divulgue aucune note interne, donnée de sécurité ou information concernant d'autres personnes.
- La présentation et la preuve de mise à disposition de cette information sont historisées de manière proportionnée.
- Le contenu définitif de l'information et la politique de confidentialité seront validés juridiquement avant mise en production.

### Question 39 — Téléchargement d'une copie du dossier de contestation par le client

**Décision validée : A**

- Le client peut télécharger depuis son espace authentifié une copie de son dossier de contestation.
- L'export comprend les décisions, leurs motifs communicables, les dates, les notifications et l'historique des recours et événements communicables au client.
- L'export exclut les notes internes du support, les informations de sécurité non communicables, les données personnelles de tiers et les justificatifs ou éléments confidentiels qui ne doivent pas être divulgués.
- Le contenu exporté respecte les droits des tiers et les règles de protection des données ; les éléments exclus ne doivent pas être exposés indirectement dans les métadonnées ou pièces jointes.
- La génération et le téléchargement sont réservés au client authentifié concerné, protégés contre l'accès non autorisé et journalisés.
- Le format, la durée de disponibilité du fichier, les limites de téléchargement et les contrôles techniques seront définis dans la conception technique.

### Question 40 — Formats de téléchargement du dossier de contestation

**Décision validée : C**

- Le client peut choisir entre deux formats de téléchargement depuis son espace authentifié : un PDF récapitulatif structuré et un fichier ZIP.
- Le PDF présente de manière lisible les éléments communicables du dossier : décisions et motifs, dates, notifications et historique des recours.
- Le ZIP contient le PDF récapitulatif ainsi que les pièces communicables auxquelles le client a légitimement accès.
- Les deux formats appliquent les mêmes exclusions : notes internes, données de sécurité non communicables, données personnelles de tiers et pièces confidentielles non divulguables.
- La génération et le téléchargement sont sécurisés, réservés au client concerné et journalisés.
- Les formats techniques, la taille maximale, la durée de disponibilité, la protection des archives et les limites de génération seront définis dans la conception technique.

### Question 41 — Durée de disponibilité du fichier ZIP généré

**Décision validée : A**

- Le fichier ZIP généré est disponible au téléchargement pendant 24 heures à compter de la fin de sa génération.
- À l'expiration de ce délai, le fichier devient automatiquement indisponible et est supprimé automatiquement.
- Cette durée concerne le fichier ZIP généré ; elle ne modifie pas la durée de conservation du dossier de contestation ni des pièces sources conservées selon les règles applicables.
- Les modalités techniques de suppression effective, de gestion des échecs de génération et de traçabilité de l'opération seront précisées lors de la conception technique.

### Question 42 — Protection du fichier ZIP pendant sa disponibilité

**Décision validée : A**

- Le téléchargement du ZIP est accessible uniquement après authentification du client.
- Avant toute délivrance du fichier ou de son URL temporaire, le système vérifie côté serveur que le client authentifié est bien propriétaire du dossier de contestation concerné et qu'il est autorisé à consulter les éléments exportés.
- L'accès utilise une URL temporaire sécurisée, à durée limitée et non réutilisable au-delà de sa validité ; elle ne constitue jamais un moyen de contourner l'authentification ou le contrôle de propriété.
- Les contrôles d'autorisation sont appliqués à chaque demande de téléchargement. Les accès et tentatives refusées sont journalisés sans enregistrer de secret ni de jeton.
- Le mot de passe supplémentaire n'est pas requis. Les paramètres techniques de signature, durée de l'URL, stockage privé et révocation seront définis lors de la conception technique.

### Question 43 — Durée de validité de l'URL temporaire de téléchargement

**Décision validée : A**

- Chaque URL temporaire de téléchargement du ZIP est valable pendant 5 minutes à compter de son émission.
- À expiration, l'URL ne permet plus de télécharger le fichier ; le client doit être authentifié et refaire une demande depuis son espace pour obtenir une nouvelle URL, sous réserve que le ZIP soit toujours disponible dans sa fenêtre de 24 heures.
- Le renouvellement ne prolonge pas la durée de disponibilité du ZIP et ne contourne jamais le contrôle de propriété du dossier.
- La durée exacte, le mécanisme d'invalidation et la protection contre la réutilisation seront appliqués côté serveur et vérifiés lors de la conception technique.

### Question 44 — Nombre de téléchargements du même ZIP

**Décision validée : A**

- Le client peut télécharger le même ZIP sans limite de nombre pendant sa période de disponibilité de 24 heures.
- Chaque téléchargement exige une authentification valide, un contrôle serveur de propriété et une URL temporaire valide de 5 minutes.
- Chaque demande et chaque téléchargement effectif sont journalisés afin de permettre le suivi et la détection d'abus.
- Des protections techniques contre les abus (limitation de débit, détection d'activité anormale et mesures temporaires proportionnées) peuvent être appliquées sans instaurer de quota fonctionnel fixe de téléchargements.
- Ces protections ne doivent pas prolonger la disponibilité du ZIP ni contourner les contrôles d'accès ; leurs seuils et modalités seront définis lors de la conception technique.

### Question 45 — Gestion d'un échec de génération du ZIP

**Décision validée : B**

- En cas d'échec de génération du ZIP, le système effectue automatiquement jusqu'à 3 tentatives au total, en tenant compte de la première tentative.
- Les tentatives automatiques sont réservées aux erreurs considérées comme temporaires et récupérables ; une erreur définitive (par exemple une pièce source absente ou non autorisée) ne doit pas être répétée aveuglément.
- Si les 3 tentatives échouent, le client reçoit une information claire dans son espace et peut demander manuellement une nouvelle génération.
- L'échec, les tentatives, leur résultat et la relance manuelle éventuelle sont journalisés sans exposer de données sensibles.
- Une relance ne doit pas contourner les contrôles d'accès ni prolonger la période de disponibilité d'un ZIP déjà généré ; les règles de délai, de déduplication des tâches et de gestion des erreurs définitives seront précisées techniquement.

### Question 46 — Notification lorsque le ZIP est prêt

**Décision validée : C**

- Lorsque le ZIP est prêt, le système affiche une notification dans l'espace client et envoie un email au client concerné.
- L'email informe que le fichier est disponible et fournit un lien sécurisé vers l'espace client ou le dossier concerné ; il ne contient jamais le ZIP en pièce jointe.
- Le lien présent dans l'email ne contourne pas l'authentification, le contrôle de propriété ni l'émission d'une URL temporaire de téléchargement valable 5 minutes.
- La notification précise la date et l'heure d'expiration du ZIP, disponible pendant 24 heures à compter de la fin de sa génération.
- L'envoi, son résultat et la notification dans l'espace client sont historisés ; aucun secret ni fichier confidentiel n'est inclus dans l'email.

### Question 47 — Échec de l'envoi de l'email de notification du ZIP

**Décision validée : A**

- Si l'email de notification échoue, la notification reste disponible dans l'espace client ; l'échec de l'email ne bloque ni la génération ni le téléchargement du ZIP.
- Le système réessaie automatiquement l'envoi selon une politique de tentatives contrôlées et différées, avec un nombre maximal de tentatives et des délais définis lors de la conception technique.
- Les échecs et nouvelles tentatives sont journalisés ; les erreurs permanentes (adresse invalide, rejet définitif) ne font pas l'objet de répétitions inutiles.
- Le client conserve l'accès au ZIP selon les règles déjà validées : disponibilité de 24 heures, authentification et contrôle de propriété, URL temporaire de 5 minutes.
- L'état de livraison de l'email est distingué de l'état de disponibilité du fichier.

### Question 48 — Nombre de tentatives d'envoi de l'email de notification

**Décision validée : A**

- Le système autorise au maximum 3 tentatives d'envoi au total, en comptant le premier envoi.
- Les tentatives supplémentaires sont déclenchées uniquement pour les erreurs temporaires susceptibles d'être résolues ; un rejet définitif ou une adresse invalide met fin aux tentatives.
- Après le dernier échec, la notification reste accessible dans l'espace client et l'état d'échec de l'email est historisé.
- Le nombre de tentatives ne conditionne jamais la disponibilité du ZIP et ne modifie pas sa durée de validité de 24 heures.
- Les délais entre tentatives et la classification technique des erreurs seront définis lors de la conception technique.

### Question 49 — Information du client après échec définitif de l'email

**Décision validée : B**

- Après l'épuisement des 3 tentatives d'envoi, le système conserve dans l'espace client la notification habituelle indiquant que le ZIP est prêt, sans afficher de message distinct signalant l'échec de l'email.
- L'échec définitif de l'email reste enregistré dans les journaux techniques et de suivi de notification, avec les informations nécessaires au diagnostic, sans données sensibles.
- L'échec de l'email ne bloque pas l'accès au ZIP et ne modifie ni sa disponibilité de 24 heures ni les contrôles d'authentification, de propriété et d'URL temporaire.
- Cette décision concerne uniquement l'information présentée au client ; les équipes habilitées peuvent disposer d'un suivi technique des échecs selon leurs droits.

### Question 50 — Historique des générations ZIP du dossier de contestation

**Décision validée : A**

- Dans son espace authentifié, le client peut consulter l'historique des générations de ZIP liées à son dossier de contestation.
- Pour chaque génération, l'historique présente les informations utiles au client : date de demande, date de génération si elle a abouti, statut compréhensible (en attente, prêt, échec ou expiré) et date/heure d'expiration lorsque le fichier a été généré.
- L'historique ne révèle pas les détails techniques internes, les traces sensibles, les chemins de stockage ni les informations concernant d'autres utilisateurs.
- Les anciennes entrées d'historique restent consultables selon les règles de conservation du dossier ; un ZIP expiré ou supprimé n'est plus téléchargeable.
- La consultation de cet historique respecte les contrôles d'authentification et de propriété du dossier et est journalisée.

### Question 51 — Suppression par le client de l'historique des générations ZIP

**Décision validée : A**

- Le client ne peut pas supprimer lui-même les entrées de l'historique des générations ZIP de son dossier de contestation.
- L'historique est conservé selon les règles de conservation applicables au dossier et reste soumis aux règles de protection des données et de suppression/anonymisation déjà définies.
- Cette interdiction concerne la suppression individuelle ou globale depuis l'espace client ; elle n'empêche pas l'effacement ou l'anonymisation à l'échéance de conservation, ni l'exercice des droits légaux du client selon les procédures applicables.
- Les événements nécessaires à l'audit et à la traçabilité ne peuvent pas être altérés par le client.

### Question 52 — Accès du support à l'historique des générations ZIP

**Décision validée : A**

- Seuls les agents du support expressément autorisés à consulter le dossier de contestation peuvent consulter l'historique des générations ZIP associé.
- L'accès est limité aux informations nécessaires à la prise en charge du dossier ; les détails techniques internes et les données sensibles ne sont pas exposés par défaut.
- Chaque consultation est journalisée avec l'identité de l'agent, la date, le dossier concerné et le périmètre consulté.
- Les droits d'accès suivent les règles d'autorisation du dossier de récupération et peuvent être retirés ; aucune permission générale n'est accordée à l'ensemble du support.
- super_admin_root conserve l'accès complet selon les règles d'audit et de supervision déjà définies.

### Question 53 — Export de l'historique des générations ZIP par le client

**Décision validée : B**

- Le client peut consulter l'historique de ses générations ZIP uniquement dans son espace client authentifié.
- Aucun export autonome de cet historique au format PDF ou CSV n'est proposé au premier release.
- Cette règle ne limite pas le téléchargement du dossier lui-même dans les formats déjà prévus (PDF récapitulatif du dossier ou archive ZIP avec les pièces communicables).
- L'affichage de l'historique reste soumis au contrôle d'accès du client propriétaire et aux règles de journalisation déjà définies.

### Question 54 — Téléchargement du ZIP par le support

**Décision validée : A**

- Les agents du support, même lorsqu'ils sont expressément autorisés à consulter le dossier et son historique de générations ZIP, ne peuvent pas télécharger le fichier ZIP généré pour le client.
- Le téléchargement du ZIP est réservé au client propriétaire, depuis son espace authentifié, sous réserve des contrôles de session, de propriété, de disponibilité et de validité du lien temporaire déjà définis.
- Le rôle de consultation du support ne confère aucun droit de téléchargement ou de récupération des archives ; les interfaces et contrôles serveur doivent appliquer cette séparation.
- Les accès du support à l'historique restent journalisés selon la décision de la question 52.

### Question 55 — Accès aux PDF communicables par le support

**Décision validée : B**

- Un agent du support expressément autorisé à consulter un dossier de récupération peut télécharger uniquement les documents PDF communicables nécessaires au traitement de ce dossier.
- Cet accès ne donne aucun droit général de téléchargement de toutes les pièces : les documents sensibles, les pièces non communicables et les éléments exclus du périmètre autorisé restent inaccessibles au support.
- Chaque téléchargement doit faire l'objet d'un contrôle serveur des permissions, du périmètre du dossier et du caractère communicable du document ; l'autorisation de consulter le dossier ne suffit pas, à elle seule, à autoriser le téléchargement de toute pièce.
- Chaque tentative et chaque téléchargement effectif doivent être journalisés avec l'identité de l'agent, le dossier concerné, le document ou sa référence, la date et le résultat, sans enregistrer le contenu du document ni de données secrètes.
- Cette règle ne modifie pas la décision Q54 : le support ne peut toujours pas télécharger l'archive ZIP du dossier, dont le téléchargement reste réservé au client propriétaire depuis son espace authentifié.
- **Périmètre concerné :** Identity (dossier de récupération et autorisations du support), Media/Storage (accès et téléchargement des PDF) et Audit (traçabilité).
- **Points à préciser avant implémentation :** classification des pièces communicables, matrice des permissions, éventuelles restrictions par type de document et durée de conservation des journaux de téléchargement.

### Question 56 — Notification du client après téléchargement d'un PDF par le support

**Décision validée : A**

- Le client ne reçoit pas de notification individuelle à chaque consultation ou téléchargement d'un PDF par un agent du support autorisé.
- L'absence de notification ne supprime aucune obligation de traçabilité : chaque consultation et chaque tentative ou téléchargement effectif restent enregistrés dans les journaux internes d'audit selon les règles applicables.
- Les journaux doivent permettre d'identifier l'agent, le dossier concerné, le document ou sa référence, la date et le résultat de l'opération, sans enregistrer le contenu du document ni de données secrètes.
- Cette décision ne modifie pas les restrictions de la question 55 : le support ne télécharge que les PDF communicables nécessaires au traitement du dossier, et ne peut pas télécharger l'archive ZIP.
- **Périmètre concerné :** Identity (dossier et accès du support), Media/Storage (consultation et téléchargement des PDF), Audit (journalisation) et Communication (absence de notification événementielle au client).
- **Points à préciser avant implémentation :** modalités de consultation éventuelle de ces traces par le client, durée de conservation des journaux et règles de supervision interne.

### Question 57 — Consultation client de l'historique des téléchargements PDF

**Décision validée : A**

- Le client ne dispose pas d'une interface lui permettant de consulter l'historique des consultations ou téléchargements de PDF effectués par le support sur son dossier.
- Cet historique est réservé à l'audit interne et aux responsables expressément autorisés, selon leurs permissions et le périmètre nécessaire à leurs fonctions ; aucun accès général au journal d'audit n'est accordé.
- Les événements restent conservés et consultables par les personnes autorisées, avec contrôle d'accès et traçabilité des consultations du journal.
- Cette décision ne modifie pas les règles précédentes : le support ne télécharge que les PDF communicables nécessaires au traitement (Q55), aucune notification individuelle n'est envoyée au client après chaque téléchargement (Q56), et l'archive ZIP reste inaccessible au support (Q54).
- **Périmètre concerné :** Identity (dossier de récupération et droits du client), Audit (conservation et consultation des journaux), Media/Storage (événements de téléchargement).
- **Points à préciser avant implémentation :** rôles internes autorisés à consulter ces journaux, granularité des permissions, durée de conservation et modalités de supervision des accès aux traces.

### Question 58 — Autorité de consultation des journaux de téléchargements PDF

**Décision validée : A**

- La consultation de l'historique des consultations et téléchargements de PDF effectués par le support est réservée exclusivement au rôle `super_admin_root`.
- Les agents du support, les responsables support et les autres rôles ne disposent d'aucun accès à ces journaux, même lorsqu'ils sont autorisés à traiter ou consulter le dossier concerné.
- L'accès du `super_admin_root` doit être protégé par les contrôles d'autorisation applicables et chaque consultation de ces journaux doit elle-même être journalisée afin de préserver la traçabilité.
- Cette règle ne modifie pas les droits opérationnels du support définis précédemment : les agents expressément autorisés peuvent télécharger uniquement les PDF communicables nécessaires au traitement (Q55), sans notification individuelle au client (Q56) ; le client ne consulte pas cet historique (Q57) et le support ne peut pas télécharger l'archive ZIP (Q54).
- **Périmètre concerné :** Identity (dossier de récupération et séparation des rôles), Audit (journaux et accès aux traces), Media/Storage (événements de téléchargement).
- **Points à préciser avant implémentation :** modalités de consultation et de recherche, éventuel export des journaux par le `super_admin_root`, durée de conservation et protections contre toute altération.

### Question 59 — Export des journaux de téléchargements PDF

**Décision validée : B**

- Le rôle `super_admin_root` peut exporter l'historique des consultations et téléchargements de PDF effectués par le support, aux formats PDF et CSV.
- L'export est réservé exclusivement au `super_admin_root`, conformément à Q58. Aucun agent ou responsable support, ni aucun autre rôle, ne peut consulter ou exporter ces journaux.
- L'export contient uniquement les informations nécessaires à l'audit : identité de l'agent concerné, référence du dossier, référence du document, date/heure, nature de l'événement et résultat. Il exclut le contenu des documents, les mots de passe, jetons, secrets, données techniques sensibles et toute donnée non nécessaire.
- Chaque demande d'export doit être contrôlée côté serveur et journalisée avec l'identité du `super_admin_root`, la date/heure, le périmètre demandé, le format et le résultat. La consultation des journaux reste elle-même tracée.
- **Périmètre concerné :** Identity (séparation des rôles et dossier), Audit (consultation, export et traçabilité), Media/Storage (événements de téléchargement).
- **Points à préciser avant implémentation :** filtres et périodes autorisés, limites de volume, génération synchrone ou asynchrone, protection et durée de disponibilité du fichier exporté, ainsi que sa durée de conservation.

### Question 60 — Période couverte par un export des journaux PDF

**Décision validée : B**

- Le `super_admin_root` peut sélectionner librement les dates de début et de fin de la période à exporter.
- Chaque export PDF ou CSV est limité à une période maximale de 12 mois. Le contrôle de cette limite est effectué côté serveur ; l'interface seule ne constitue pas une protection.
- Une période plus longue doit être traitée au moyen de plusieurs exports distincts, chacun respectant la limite de 12 mois.
- Les bornes temporelles effectivement demandées doivent être enregistrées dans la trace de l'export, avec l'identité du demandeur, la date/heure, le format, le périmètre et le résultat.
- Cette limite ne modifie ni la durée de conservation des journaux ni les droits de consultation et d'export réservés au `super_admin_root` (Q58 et Q59).
- **Périmètre concerné :** Identity (autorisation), Audit (journaux et traçabilité des exports), Media/Storage (génération des fichiers).
- **Points à préciser avant implémentation :** fuseau horaire de référence, inclusion des bornes de période, comportement pour une période vide ou invalide, et gestion des exports volumineux.

### Question 61 — Protection des fichiers d'export des journaux PDF

**Décision validée : A**

- Après génération d'un export PDF ou CSV des journaux, le `super_admin_root` peut le télécharger au moyen d'une URL temporaire valable 5 minutes.
- L'accès au téléchargement exige une authentification active et une vérification côté serveur du droit du demandeur, de l'identité de l'export et de son état. L'URL temporaire ne constitue jamais un mécanisme de contournement de l'authentification ou de l'autorisation.
- L'URL expire après 5 minutes. Un nouveau lien peut être demandé depuis l'espace sécurisé tant que le fichier exporté est encore disponible, sous réserve des contrôles d'accès et de la politique de conservation définie pour les exports.
- Les demandes de génération, émissions de liens, tentatives de téléchargement et téléchargements aboutis ou refusés sont journalisés, sans enregistrer le jeton secret de l'URL ni le contenu du fichier dans les journaux.
- Le fichier ne doit pas être exposé par une URL publique permanente ; son stockage et son accès doivent rester privés.
- **Périmètre concerné :** Identity (autorisation réservée au `super_admin_root`), Audit (traçabilité des demandes et accès), Media/Storage (stockage privé, génération et téléchargement sécurisé).
- **Points à préciser avant implémentation :** durée maximale de disponibilité du fichier exporté, suppression automatique, possibilité de réutiliser plusieurs fois le même lien avant expiration, et comportement en cas de révocation de session ou de permission.

### Question 62 — Durée de conservation des fichiers d'export PDF/CSV

**Décision validée : A**

- Chaque fichier exporté contenant les journaux de téléchargements PDF est conservé pendant une durée maximale de 24 heures à compter de la fin de sa génération.
- À l'expiration de ce délai, le fichier est supprimé automatiquement du stockage privé et ne peut plus être téléchargé. La suppression doit être exécutée côté serveur et son résultat tracé.
- La suppression du fichier d'export ne supprime ni ne modifie les journaux d'audit sources, dont la durée de conservation relève de leur propre politique.
- Toute tentative d'accès après expiration doit être refusée, même si un ancien lien temporaire a été conservé.
- **Périmètre concerné :** Audit (séparation entre journaux sources et exports), Media/Storage (stockage privé, expiration et suppression), Identity (contrôle d'accès au téléchargement).
- **Points à préciser avant implémentation :** précision du point de départ du délai, traitement des échecs de suppression, purge de sauvegardes/caches et traçabilité des purges.

### Question 63 — Informations incluses dans l'export PDF/CSV des journaux de téléchargements

**Décision validée : B**

- L'export PDF/CSV comprend la date et l'heure de l'événement, l'identité de l'agent, le dossier concerné, la référence du document, le résultat du téléchargement, l'adresse IP et le type de navigateur/appareil lorsqu'ils sont disponibles et pertinents.
- Les informations techniques sont limitées à ces éléments de traçabilité ; les détails complets des requêtes, en-têtes, paramètres, contenus, secrets, jetons et données sensibles ne sont jamais inclus dans l'export.
- L'export reste réservé au `super_admin_root`, sur une période maximale de 12 mois par export, et son téléchargement est protégé par authentification et URL temporaire de 5 minutes.
- Chaque export est conservé au maximum 24 heures après sa génération, puis supprimé automatiquement sans altérer les journaux d'audit sources.
- La génération, l'émission du lien, les tentatives et les téléchargements de l'export sont eux-mêmes journalisés.
- **Périmètre concerné :** Audit (journalisation et export contrôlé), Identity (autorisation réservée au `super_admin_root`), Media/Storage (génération, stockage privé, téléchargement et purge).
- **Points à préciser avant implémentation :** format et normalisation de l'adresse IP, définition des familles/types de navigateur et appareil, disponibilité de ces données selon le canal d'accès et règles de minimisation applicables.

### Question 64 — Filtres disponibles avant l'export des journaux de téléchargements PDF

**Décision validée : B**

- Le `super_admin_root` peut filtrer les journaux avant export selon la période, l'agent, le résultat du téléchargement, le document, l'adresse IP et le dossier concerné.
- Les filtres sont appliqués côté serveur et respectent le périmètre d'autorisation du `super_admin_root`. Les valeurs saisies sont validées et normalisées ; aucune donnée ne doit permettre de contourner les contrôles d'accès.
- Les résultats affichés et exportés correspondent aux mêmes critères de filtrage. L'export reste limité à une période maximale de 12 mois et aux champs autorisés par la décision Q63.
- La consultation, l'application des filtres, la génération et le téléchargement des exports sont journalisés. Les journaux sources ne sont jamais modifiés par le filtrage ou l'export.
- **Périmètre concerné :** Audit (recherche et traçabilité), Identity (autorisation réservée au `super_admin_root`), Media/Storage (production des exports).
- **Points à préciser avant implémentation :** comportement des filtres combinés, recherche exacte ou partielle, pagination, limites de résultats et normalisation des adresses IP.

### Question 65 — Enregistrement de filtres favoris pour les journaux de téléchargements PDF

**Décision validée : B**

- Le `super_admin_root` peut enregistrer ses propres configurations de filtres favoris afin de les réutiliser lors de consultations et d'exports ultérieurs.
- Les favoris sont personnels au `super_admin_root` qui les a créés ; ils ne sont pas partagés avec les autres comptes ou responsables.
- Un favori conserve uniquement les critères de recherche nécessaires (période relative ou paramètres de filtre), jamais une copie des journaux, des documents, des résultats exportés, des secrets ou des données sensibles.
- À chaque réutilisation, les critères sont revalidés côté serveur et les autorisations actuelles sont appliquées. Un favori ne confère aucun droit supplémentaire et ne contourne ni la limite de 12 mois par export ni les règles de conservation et de téléchargement.
- La création, la modification et la suppression des favoris sont journalisées conformément aux règles d'audit.
- **Périmètre concerné :** Audit (recherche et traçabilité), Identity (espace personnel et contrôle réservé au `super_admin_root`).
- **Points à préciser avant implémentation :** nombre maximal de favoris, nommage, modification, suppression, gestion des dates relatives et comportement si un critère n'est plus disponible.

### Question 66 — Nombre maximal de filtres favoris du super_admin_root

**Décision validée : B**

- Le `super_admin_root` peut enregistrer jusqu'à 25 filtres favoris personnels pour la consultation et l'export des journaux de téléchargements PDF.
- Le plafond est appliqué côté serveur ; une création au-delà de 25 favoris est refusée sans supprimer ni remplacer automatiquement un favori existant.
- Le `super_admin_root` peut gérer ses favoris existants afin de libérer une place. Les favoris restent strictement personnels et ne sont pas partagés.
- Ce plafond ne modifie ni les droits d'accès aux journaux, ni les filtres disponibles, ni les limites de période et d'export définies précédemment.
- **Périmètre concerné :** Audit (recherche des journaux), Identity (espace personnel et autorisation du `super_admin_root`).
- **Points à préciser avant implémentation :** règles de nommage, unicité des noms, confirmation de suppression et traitement des favoris devenus invalides.

### Question 67 — Traitement d'un filtre favori devenu invalide

**Décision validée : B**

- Lorsqu'un filtre favori contient un critère devenu invalide ou indisponible (par exemple un agent, un dossier ou un document supprimé ou auquel l'accès n'est plus autorisé), le système signale explicitement le critère concerné.
- Le favori ne peut pas être réutilisé tant que le `super_admin_root` ne l'a pas corrigé ou supprimé. Aucun critère invalide n'est ignoré ou remplacé silencieusement.
- À chaque exécution, les critères et les autorisations sont revalidés côté serveur ; un favori enregistré ne conserve aucun droit d'accès et ne permet jamais de contourner les permissions actuelles.
- La correction ou suppression du favori est effectuée par son propriétaire ; ces opérations restent journalisées. Les journaux sources ne sont pas modifiés.
- **Périmètre concerné :** Audit (filtres et recherche), Identity (propriétaire et autorisations du `super_admin_root`).
- **Points à préciser avant implémentation :** distinction entre critère supprimé, renommé ou temporairement inaccessible ; message d'erreur ; possibilité de corriger le filtre depuis l'écran de recherche.

### Question 68 — Méthode de validation de la complétude fonctionnelle d'un domaine avant et pendant le développement

**Décision validée : B + C — approche combinée**

- **Avant le codage :** comparer systématiquement l'inventaire exhaustif des fonctions, les décisions validées, l'architecture, les relations inter-domaines et les exigences du premier release. Cette revue constitue le contrôle principal pour décider si le périmètre fonctionnel V1 est suffisamment défini pour commencer le développement.
- **Pendant le codage et les tests :** utiliser les tests unitaires, Feature, HTTP et intégration, les revues de code et les scénarios d'acceptation pour détecter les oublis, cas limites, incohérences ou fonctions manquantes qui n'auraient pas été identifiés au cadrage.
- La découverte d'une lacune pendant le développement ne remplace jamais la revue préalable : elle déclenche une mise à jour de la spécification officielle, l'analyse des impacts sur les domaines liés et la validation de la règle avant de modifier le comportement applicatif.
- Chaque lacune identifiée est ajoutée à l'inventaire avec un identifiant stable, une décision ou un statut explicite, des critères d'acceptation et des liens vers les fichiers et tests concernés.
- Le domaine ne peut être déclaré complet que lorsque la revue de couverture est terminée et que toutes les fonctions V1 sont soit implémentées et vérifiées, soit explicitement bloquées avec justification. Les fonctions reportées hors V1 restent tracées séparément.
- **Périmètre concerné :** tous les domaines MAHLINE V1 ; gouvernance fonctionnelle, architecture, développement, assurance qualité et traçabilité.
- **Règle de décision :** B est le contrôle préalable obligatoire ; C est le mécanisme complémentaire de détection et de correction pendant la réalisation, jamais un substitut au cadrage.

### Question 69 — Documents de référence et checklist pendant le codage d'un domaine

**Décision validée : C**

- Le registre général `FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md` conserve la vision transversale du premier release, les décisions question/réponse, les règles communes et la traçabilité globale.
- Chaque domaine dispose d'une spécification fonctionnelle détaillée, versionnée et propre à son périmètre. Cette spécification constitue la checklist opérationnelle obligatoire pour le développement du domaine.
- Les identifiants stables des fonctions sont cohérents entre le registre général, la spécification du domaine, la roadmap et, dès qu'ils sont définis, les fichiers de code, migrations, routes, permissions et tests.
- Avant chaque tâche de codage, le développeur consulte la spécification du domaine et la fiche de la fonction concernée, puis vérifie les dépendances et décisions transversales dans le registre général.
- Toute évolution d'une règle met à jour les documents concernés avant la poursuite de l'implémentation ; aucune divergence silencieuse entre registre général et spécification de domaine n'est admise.
- **Périmètre concerné :** tous les domaines MAHLINE V1 ; documentation fonctionnelle, architecture, développement et assurance qualité.
- **Conséquence :** le registre général assure la cohérence globale ; la spécification du domaine guide l'exécution et la vérification fonction par fonction.

### Question 70 — Contenu minimal obligatoire d'une spécification fonctionnelle par domaine

**Décision validée : C**

Chaque spécification de domaine MAHLINE V1 doit être suffisamment complète pour servir de référence fonctionnelle, architecturale et de checklist pendant le développement. Elle documente au minimum :

- **Fonctionnalités :** identifiant stable, objectif, description, périmètre V1, priorité et statut.
- **Acteurs et accès :** rôles concernés, responsabilités, permissions, politiques et règles de visibilité.
- **Règles métier :** préconditions, validations, états, transitions, calculs, contraintes et résultats attendus.
- **Scénarios :** parcours nominaux, variantes, cas limites, exceptions, refus, annulations et reprises.
- **Erreurs :** erreurs fonctionnelles et techniques pertinentes, conditions de déclenchement et comportement attendu.
- **Dépendances et relations inter-domaines :** domaines propriétaires, responsabilités, contrats, données échangées, déclencheurs et événements émis/reçus.
- **Données :** entités, attributs utiles, relations, contraintes d'intégrité, cycle de vie et règles de conservation pertinentes.
- **Architecture et composants concernés :** modèles, migrations, services, contrôleurs, routes, interfaces, permissions, jobs, notifications et fichiers à créer ou adapter, selon le besoin.
- **Tests et acceptation :** critères d'acceptation vérifiables, scénarios de tests unitaires/Feature/HTTP/intégration pertinents et liens vers les tests associés.
- **Traçabilité :** liens entre identifiant fonctionnel, décision du registre général, spécification, composants techniques, tests, anomalies et preuve de validation.
- Les éléments non applicables à un domaine doivent être indiqués comme tels avec justification, plutôt que laissés ambigus. Les décisions en attente, reports et exclusions V1 doivent être explicitement identifiés.
- **Périmètre concerné :** toutes les spécifications fonctionnelles des domaines du SaaS et du marketplace MAHLINE V1.
- **Conséquence :** aucune fonctionnalité ne passe au codage sur la seule base d'un intitulé ; sa règle, ses accès, ses dépendances et ses critères de validation doivent être définis.

### Question 71 — Organisation des identifiants fonctionnels entre domaines

**Décision validée : C**

- Chaque domaine possède un préfixe et une séquence d'identifiants stables qui lui sont propres (ex. `MED-001`, `SAL-001`, `FIN-001`).
- Les sous-fonctions peuvent utiliser une hiérarchie explicite rattachée à la fonction parente (ex. `SAL-001.01`, `SAL-001.02`). La hiérarchie ne doit pas remplacer l'identifiant stable de la fonction principale.
- Une fonction partagée ou une interaction entre domaines conserve un propriétaire fonctionnel clairement désigné. Les autres domaines la référencent par son identifiant canonique ; ils ne créent pas de copie ni de nouvel identifiant concurrent pour la même règle.
- Lorsqu'un besoin implique plusieurs domaines, chaque domaine conserve ses propres fonctions et identifiants pour ses responsabilités ; les liens et contrats entre ces fonctions sont documentés explicitement.
- Un identifiant publié ne doit jamais être réattribué à une autre fonction, même après suppression, report ou abandon. Les changements de périmètre et remplacements conservent une trace de l'ancien identifiant.
- **Périmètre concerné :** tous les domaines et toutes les spécifications MAHLINE V1.
- **Conséquence :** la nomenclature reste lisible par domaine, les sous-fonctions sont traçables et les responsabilités partagées sont reliées sans duplication.

### Question 72 — Gestion des fonctionnalités communes entre domaines (VALIDÉ)
- **Question :** Comment gérer les fonctionnalités communes à plusieurs domaines ?
- **Réponse :** C — conserver chaque règle métier dans son domaine propriétaire et placer uniquement les composants techniques réellement transversaux dans Shared, avec des contrats explicites.
- **Statut :** VALIDÉ — règle d'architecture applicable au premier release.
- **Décision :**
  - Chaque domaine reste propriétaire de ses entités, règles métier, cas d'utilisation, validations et décisions métier.
  - Un besoin utilisé par plusieurs domaines ne justifie pas de déplacer sa règle métier dans Shared.
  - Core accueille l'infrastructure technique générique ; Shared accueille seulement les composants transversaux réutilisables qui ne possèdent pas de règle métier propre à un domaine.
  - Les échanges entre domaines passent par des contrats explicites, DTO/value objects adaptés, interfaces ou événements ; aucun domaine ne doit appeler les détails internes d'un autre domaine.
  - Une fonction métier partagée conserve un propriétaire fonctionnel canonique et un identifiant stable ; les domaines consommateurs référencent cette fonction et documentent leurs propres responsabilités sans la dupliquer.
- **Conséquence pour Finance :** Finance est un domaine métier autonome, distinct de Sales. Sales reste propriétaire du parcours commercial, du panier, du checkout et des commandes ; Finance est propriétaire des opérations monétaires et documents financiers marketplace. Subscriptions / SaaS Billing reste autonome pour la facturation des abonnements MAHLINE aux coopératives. Les contrats entre ces domaines doivent être spécifiés avant leur implémentation.
- **Périmètre concerné :** tous les domaines MAHLINE V1 ; Core et Shared (technique), Sales, Finance, Subscriptions / SaaS Billing, Delivery, Inventory, Identity, Communication, Media, Audit et les autres domaines du référentiel. Facturation est une capacité rattachée au domaine propriétaire du flux, pas un domaine autonome.
- **Règle :** ne pas créer un domaine Shared fourre-tout et ne pas dupliquer une fonction métier sous prétexte qu'elle est consommée par plusieurs domaines.

## Référentiel fonctionnel V1 par domaine — synchronisé avec la roadmap

Cette matrice constitue la vue fonctionnelle transversale du premier release. Elle doit rester strictement alignée avec la section 1.2 de `DOMAIN_DEVELOPMENT_ROADMAP.md`. Elle identifie les familles de fonctions et leur propriétaire ; elle ne remplace pas les spécifications détaillées de chaque domaine.

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
| Finance | facturation marketplace par coopérative/commande, documents financiers client et coopérative, paiements/COD et transactions, remboursements/avoirs, commissions configurables par coopérative sur le montant des produits hors livraison, reversements, rapprochement et états financiers | panier, commande commerciale, abonnement SaaS |
| Delivery | expéditions par coopérative, colis, tarifs de trajet, transporteurs, affectation, suivi, preuve de livraison, incidents, refus, retours et réexpéditions | panier, commande commerciale et comptabilisation des paiements |
| Subscriptions / SaaS Billing | plans SaaS, souscriptions, renouvellements, changements de plan, périodes de grâce, impayés, factures et paiements dus à MAHLINE, entitlements, quotas et suspension/réactivation | factures marketplace et reversements aux coopératives |
| Communication | modèles, e-mails, notifications, préférences techniques, files, retries, délivrabilité et historique d'envoi | règles métier déclenchant la communication |
| Audit | événements d'audit métier, acteur, sujet, horodatage, avant/après, motif, consultation autorisée, intégrité et rétention | stockage des fichiers et logique métier source |
| CMS | pages, sections, bannières, menus, FAQ, SEO, brouillons, publication, planification et contenus multilingues | médias, produits, coopératives et commandes |
| Dashboard | indicateurs et tableaux de bord par rôle/périmètre, agrégations des domaines sources et contrôles d'accès | propriété des données opérationnelles ou financières |

**Règles de lecture et de passage au codage :**
- Cette matrice est un inventaire directeur, pas une déclaration que toutes les fonctions sont déjà spécifiées ou développées.
- Les identifiants stables seront attribués dans chaque spécification de domaine après comparaison avec les décisions et les fonctions déjà documentées ; aucun identifiant ne doit être inventé ou réutilisé.
- Chaque domaine doit disposer de sa spécification détaillée conforme aux règles validées en Q70, avec acteurs, permissions, règles, états, scénarios, erreurs, critères d'acceptation, contrats, données, événements, composants et tests.
- Avant de coder, comparer cette matrice, les décisions du registre, la spécification du domaine et l'audit réel du dépôt. Si une fonction essentielle V1 reste ambiguë ou sans critère d'acceptation, poursuivre le cadrage ciblé ; si le périmètre essentiel est complet et cohérent, valider le domaine pour le codage.
- Les fonctions techniques PDF, stockage et envoi sont consommées auprès de Media et Communication. La propriété de la facture reste à Finance pour la marketplace et à Subscriptions / SaaS Billing pour les abonnements MAHLINE.
- `Facturation` désigne ici une capacité fonctionnelle, pas un domaine autonome : ne pas créer une seconde implémentation de factures qui ferait doublon avec Finance ou SaaS Billing.

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
  - Aucune suppression ne doit être exécutée avant confirmation explicite.  - Le traitement doit identifier et supprimer proprement toutes les associations concernées, sans supprimer les éléments métier eux-mêmes (produits, catégories, coopératives, etc.).
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
  - La notification reste conservée en base et aucune suppression physique n’est effectuée par cette action.  - Les traces d’audit restent conservées.
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
- **Portée :** l'annulation ne supprime ni ne renvoie les fichiers déjà téléversés avec succès. L'état de chaque fichier doit être clairement distingué (en attente, en cours, réussi, échoué ou annulé). Les éventuels fichiers partiels temporaires doivent être nettoyés de manière sûre côté serveur.- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

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

### Question 197 — Suppression d'une image originale et de ses variantes

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** la suppression physique d'un média comprend son original et toutes ses variantes optimisées, mais elle n'est autorisée qu'après vérification qu'aucune référence encore valide ne dépend de ce média.
- **Portée :** les références des différents modules doivent être vérifiées avant toute suppression physique. Une référence encore active bloque la suppression du fichier partagé et de ses variantes. La suppression des références et le traitement des échecs de suppression suivent les règles de suppression Media déjà validées, avec contrôle des permissions, confirmation explicite et journalisation.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 198 — Contrôle de l'intégrité des images

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** après téléversement, Media vérifie que le fichier est lisible et que son format réel correspond au format annoncé. Après traitement, chaque variante générée est également contrôlée afin de confirmer qu'elle est complète et affichable.
- **Portée :** toute variante corrompue ou invalide est écartée et ne doit pas être distribuée. L'incident est journalisé sans exposer de détails techniques sensibles à l'utilisateur. Le fichier original reste soumis à son propre contrôle d'intégrité ; un échec de validation empêche son utilisation comme média valide. Les contrôles techniques précis seront définis pendant l'audit.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 199 — Analyse de sécurité des fichiers téléversés

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** tous les fichiers téléversés dans Media font l'objet d'une analyse antivirus avant leur utilisation ou leur diffusion. Tout fichier suspect ou détecté comme malveillant est placé en quarantaine et reste inaccessible aux utilisateurs et aux modules.
- **Portée :** l'analyse concerne les formats acceptés par Media, notamment les images, PDF et vidéos. Le fichier en quarantaine ne peut pas être prévisualisé, téléchargé, associé à une entité métier ni distribué publiquement. L'incident est journalisé et sa gestion est réservée aux rôles autorisés. Le moteur antivirus, les délais d'analyse, les règles de conservation et la procédure de levée de quarantaine seront définis pendant l'audit technique.
- **Statut :** règle fonctionnelle enregistrée ; choix du moteur et paramètres à définir lors de l'audit technique ; implémentation non commencée.

### Question 200 — Visibilité d'un fichier pendant l'analyse antivirus

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** après le téléversement, le fichier peut apparaître dans Media avec le statut « En attente de validation » pendant l'analyse antivirus.
- **Portée :** ce statut informe l'utilisateur que le téléversement a été reçu, sans autoriser l'utilisation du fichier. Tant que l'analyse n'est pas terminée et positive, le fichier ne peut être ni prévisualisé, ni téléchargé, ni associé à une entité métier, ni diffusé. Les accès et changements de statut sont contrôlés côté serveur.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 201 — Information de l'utilisateur après l'analyse antivirus

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** à la fin de l'analyse antivirus, Media met à jour le statut du fichier et informe l'utilisateur par une notification dans MAHLINE. L'email est réservé aux incidents de sécurité importants.
- **Portée :** la notification indique le résultat utile à l'utilisateur sans divulguer de détails techniques sensibles. Les incidents importants peuvent déclencher un email aux responsables autorisés selon des critères à définir pendant l'audit technique. La notification et les éventuels emails respectent les permissions, la confidentialité et les règles du domaine de communication.
- **Statut :** règle fonctionnelle enregistrée ; critères d'incident important à définir lors de l'audit technique ; implémentation non commencée.

### Question 202 — Traitement d'un fichier détecté comme malveillant

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** tout fichier détecté comme malveillant reste en quarantaine. Le super_admin_root reçoit une notification et peut demander sa suppression définitive après une confirmation explicite.
- **Portée :** le fichier en quarantaine demeure inaccessible aux utilisateurs et aux modules, y compris pour l'aperçu, le téléchargement, l'association et la diffusion. La suppression est réservée au super_admin_root, contrôlée côté serveur et journalisée. La suppression physique suit les contrôles de références et les règles de suppression Media déjà validées. Aucun fichier n'est supprimé automatiquement à la seule suite d'une détection antivirus.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 203 — Traitement d'un faux positif antivirus

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** le super_admin_root peut demander une nouvelle analyse d'un fichier en quarantaine. Si cette nouvelle analyse conclut que le fichier est sain, sa libération de quarantaine nécessite encore la validation explicite du super_admin_root.
- **Portée :** le fichier reste inaccessible pendant la nouvelle analyse et jusqu'à sa libération validée. Une nouvelle analyse qui détecte encore une menace maintient le fichier en quarantaine. La demande, les résultats et la décision de libération sont journalisés ; les contrôles d'accès et la notification des incidents restent applicables. Les modalités de seconde analyse seront définies pendant l'audit technique.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 204 — Conservation de l'historique des incidents antivirus

- **Réponse :** D (validation de la recommandation).
- **Décision validée :** conserver durablement l'historique des incidents antivirus, des analyses, des résultats, des décisions de quarantaine/libération et des suppressions. Cet historique est consultable uniquement par le super_admin_root.
- **Portée :** l'historique est append-only et journalisé ; les utilisateurs et les autres rôles ne peuvent ni le consulter ni le modifier. Les données conservées doivent exclure les secrets et informations techniques non nécessaires. Les obligations de conservation, d'archivage et de protection des données seront vérifiées pendant l'audit technique et juridique.
- **Statut :** règle fonctionnelle enregistrée ; implémentation non commencée.

### Question 205 — Formats vidéo acceptés par Media

- **Réponse :** B (validation de la recommandation).
- **Décision validée :** Media accepte les fichiers vidéo MP4, MOV et WebM au premier release.
- **Portée :** le système vérifie le format réel du fichier et les codecs utilisés, sans se fier uniquement à l'extension. Les vidéos acceptées sont converties automatiquement vers un format de diffusion standard adapté à la compatibilité, à la qualité et à la livraison. L'original est conservé temporairement jusqu'à vérification de la réussite de la conversion et de la lecture du fichier converti. Les codecs précis, les profils pris en charge et les limites techniques restent à fixer pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 206 — Limites de durée et de résolution des vidéos

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** au premier release, une vidéo ne peut pas dépasser 60 secondes et sa résolution maximale est de 1080p. La limite de poids de 20 Mo par vidéo, déjà validée, reste applicable indépendamment de ces deux limites.
- **Portée :** les trois limites (durée, résolution et poids) sont contrôlées côté serveur avant que la vidéo soit acceptée et traitée. Les règles précises de détection des métadonnées, de tolérance aux fichiers mal formés et de traitement des vidéos non conformes seront définies pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 207 — Gestion des échecs de conversion vidéo

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** si la conversion vidéo échoue, Media conserve l'original et effectue jusqu'à trois tentatives automatiques. Si elles échouent toutes, l'utilisateur reçoit un message compréhensible et peut demander une nouvelle tentative manuelle.
- **Portée :** l'original n'est pas supprimé tant qu'une conversion réussie et la lecture du fichier converti n'ont pas été vérifiées. Les nouvelles tentatives manuelles restent soumises aux permissions et aux contrôles de sécurité. Les délais entre tentatives, la détection des échecs et la politique de nettoyage des fichiers temporaires seront définis pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 208 — Conservation temporaire des originaux vidéo

- **Réponse :** B (validation de la recommandation).
- **Décision validée :** après conversion réussie et vérification effective de la lecture du fichier converti, Media conserve le fichier vidéo original pendant 24 heures, puis le supprime automatiquement.
- **Portée :** le délai de 24 heures commence uniquement après validation complète de la conversion. Si la conversion ou sa vérification échoue, l'original est conservé et la politique de nouvelle tentative de Q207 continue de s'appliquer. La suppression automatique doit être sécurisée, journalisée et ne pas affecter la vidéo convertie ni ses références. Les modalités techniques de planification, de reprise et de traçabilité seront définies pendant l'audit.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 209 — Comptabilisation du stockage Media

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** le quota de stockage d'une coopérative comptabilise tous les fichiers physiques effectivement stockés pour son espace Media : fichiers originaux, vidéos converties, miniatures et variantes optimisées des images.
- **Portée :** les fichiers et variantes générés automatiquement ne sont pas exclus du calcul. Le stockage mutualisé et les références partagées entre modules doivent être comptabilisés de manière cohérente, sans double comptage artificiel d'un même fichier physique dans l'espace d'une coopérative. Les modalités exactes de rattachement, de calcul, de mise à jour et d'affichage du quota seront vérifiées pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 210 — Indisponibilité du moteur antivirus

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** si le moteur antivirus est indisponible ou ne répond pas, Media bloque les nouveaux imports jusqu'à ce que le moteur soit de nouveau opérationnel. Aucun fichier ne peut être considéré comme sain ni être utilisé, téléchargé, associé à une ressource métier ou distribué sans résultat d'analyse positif.
- **Portée :** les fichiers déjà en attente d'analyse restent inaccessibles jusqu'à l'obtention d'un résultat positif. Le système doit signaler clairement l'indisponibilité sans exposer de détails techniques sensibles. Les délais de détection, la surveillance du moteur, les alertes destinées au super_admin_root et la reprise des analyses en attente seront définis pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 211 — Interface de gestion de la quarantaine

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** Media fournit une interface dédiée, accessible uniquement au super_admin_root, pour consulter et gérer les fichiers mis en quarantaine.
- **Portée :** l'interface présente au minimum le nom du fichier, la coopérative concernée, la date de détection et le motif de mise en quarantaine. Le super_admin_root peut, selon les règles déjà validées, demander une nouvelle analyse ou demander la suppression définitive après confirmation. Les fichiers restent inaccessibles tant qu'ils ne sont pas explicitement libérés après une analyse positive. L'accès à l'interface et chaque action sont contrôlés côté serveur et journalisés. Les détails complémentaires de présentation et de conservation seront vérifiés pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 212 — Cohérence des sauvegardes et suppressions Media

- **Réponse :** A (validation de la recommandation).
- **Décision validée :** les sauvegardes doivent préserver de manière cohérente les fichiers Media (originaux et variantes nécessaires) ainsi que leurs références et métadonnées associées. Une suppression définitive validée ne doit pas être annulée par une restauration ordinaire.
- **Portée :** les mécanismes de sauvegarde et de restauration doivent maintenir l'intégrité entre fichiers physiques, variantes, références inter-modules, métadonnées, quotas et journaux nécessaires. Une restauration ordinaire ne doit pas réintroduire un fichier dont la suppression définitive a été validée après le point de sauvegarde ; toute restauration exceptionnelle doit suivre une procédure contrôlée et journalisée, sans contourner les règles de suppression et d'autorisation. Les stratégies exactes de sauvegarde, de restauration, de cohérence temporelle et de reprise seront définies pendant l'audit technique.
- **Statut :** règle fonctionnelle validée et enregistrée ; implémentation non commencée.

### Question 257 — Prix affiché sur la carte d'un produit avec plusieurs variantes (VALIDÉ)

- **Question :** Si un produit possède plusieurs variantes avec des prix différents, quel prix afficher sur la carte du produit ?
- **Réponse :** A — afficher le prix applicable le plus bas avec la mention « À partir de ».
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Products et Catalog ; carte produit marketplace, variantes et prix applicables.
- **Conséquences :**
  - La carte produit affiche le prix applicable le plus bas parmi les variantes commercialisables, précédé de la mention « À partir de ».
  - Le prix applicable de chaque variante suit Q256 : prix spécifique de variante lorsqu'il est renseigné, sinon héritage du prix de base obligatoire du produit.
  - Le prix affiché doit être calculé côté serveur à partir des données de prix faisant autorité ; aucune valeur transmise par le navigateur n'est considérée comme fiable.
  - La fiche produit présente les prix des variantes sélectionnables afin que le client puisse connaître le prix exact avant l'ajout au panier.
  - Sales revalide le prix de la variante sélectionnée et fige le prix applicable dans le panier/la commande conformément à Q256 ; une modification ultérieure du catalogue ne réécrit pas les commandes existantes.
  - Si aucune variante commercialisable n'est disponible, la carte ne doit pas présenter un prix trompeur comme prix achetable ; l'état de disponibilité est affiché selon les règles de Catalog/Inventory.
- **Impact :** donner immédiatement au client un repère de prix attractif et exact sans masquer l'existence de variantes plus coûteuses ; conserver un calcul cohérent entre carte, fiche, panier et commande.
- **Points restant à préciser :** formatage monétaire/devise et arrondis ; inclusion/exclusion des variantes indisponibles du prix minimum ; gestion des promotions et prix barrés ; comportement si le produit n'a pas de variantes.

### Question 258 — Variantes prises en compte dans le prix minimum de la carte (VALIDÉ)

- **Question :** Pour calculer le prix minimum affiché sur la carte, faut-il tenir compte des variantes temporairement indisponibles ?
- **Réponse :** A — exclure les variantes en rupture de stock ; afficher le prix minimum parmi les variantes disponibles.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Catalog, Products et Inventory ; calcul du prix d'appel et disponibilité des variantes.
- **Conséquences :**
  - Le prix « À partir de » est calculé uniquement à partir des variantes commercialisables et actuellement disponibles.
  - Une variante indisponible ou en rupture de stock ne participe pas au calcul du prix minimum affiché sur la carte.
  - La disponibilité utilisée doit provenir d'Inventory, selon la quantité réellement disponible après prise en compte des réservations, et être vérifiée côté serveur.
  - Catalog/Products ne doit pas exposer ni recalculer le stock physique ; il consomme l'information de disponibilité nécessaire à la présentation.
  - La disponibilité peut évoluer entre l'affichage de la carte et l'ajout au panier ; Sales doit donc revalider la disponibilité et le prix au moment de l'opération.
  - Si aucune variante n'est disponible, aucun prix « À partir de » achetable n'est affiché ; la carte indique l'indisponibilité selon les règles de présentation du catalogue.
- **Impact :** éviter d'attirer le client avec un prix correspondant uniquement à une variante qu'il ne peut pas acheter, tout en maintenant la séparation Catalog/Products et Inventory.
- **Points restant à préciser :** traitement des produits sans variantes ; seuil ou statut exact définissant une variante commercialisable ; comportement en cas de retard de synchronisation de disponibilité ; promotions et affichage du prix barré.

### Question 259 — Prix affiché pour un produit sans variantes (VALIDÉ)

- **Question :** Comment afficher le prix d'un produit qui ne possède aucune variante ?
- **Réponse :** A — afficher directement son prix de base.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Products et Catalog ; affichage des prix sur les cartes et fiches produits.
- **Conséquences :**
  - Pour un produit sans variante, la carte affiche directement le prix de base du produit, sans la mention « À partir de ».
  - Le prix de base est obligatoire conformément à Q256 et constitue le prix applicable de ce produit.
  - Catalog expose ce prix depuis la source de vérité Products ; Sales le revalide côté serveur et le fige dans le panier/la commande.
  - Les règles de disponibilité du produit restent applicables : un produit non commercialisable ou indisponible ne doit pas être présenté comme immédiatement achetable.
- **Impact :** assurer une présentation cohérente : prix direct pour un produit sans variantes, « À partir de » pour un produit à variantes lorsque les conditions de Q257-Q258 sont réunies.
- **Points restant à préciser :** devise et formatage monétaire ; promotions/prix barrés ; comportement détaillé de disponibilité des produits sans variantes.

### Version 3.40 — 13 novembre 2026
- Enregistrement de Q259 : un produit sans variantes affiche directement son prix de base, sans « À partir de ».
- Le prix de base Products est exposé par Catalog puis revalidé et figé par Sales ; les règles de disponibilité restent applicables.
- Devise, formatage, promotions et disponibilité détaillée restent à préciser.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.39 — 13 novembre 2026
- Enregistrement de Q258 : exclure du prix minimum les variantes en rupture/indisponibles ; afficher le minimum parmi les variantes réellement disponibles.
- La disponibilité provient d'Inventory ; Sales revalide disponibilité et prix lors de l'ajout au panier.
- Si aucune variante n'est disponible, la carte indique l'indisponibilité sans prix d'appel achetable.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.38 — 13 novembre 2026
- Enregistrement de Q257 : la carte produit affiche le prix applicable minimum parmi les variantes commercialisables, avec la mention « À partir de ».
- Le calcul est côté serveur et cohérent avec Q256 ; la fiche expose les prix des variantes et Sales revalide puis fige le prix sélectionné.
- Devise, arrondis, variantes indisponibles, promotions et produits sans variantes restent à préciser.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.37 — 13 novembre 2026
- Enregistrement de Q256 : prix de base obligatoire au niveau produit ; prix spécifique facultatif par variante, avec héritage du prix de base si absent.
- Le prix applicable est affiché par Catalog et figé par Sales dans le panier/la commande ; le navigateur ne fait pas autorité sur le prix.
- Devise, arrondis, promotions et présentation de variantes à prix différents restent à spécifier.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.36 — 13 novembre 2026
- Enregistrement de Q255 : sans réponse du client à l'échéance configurable, seule la ligne concernée est annulée automatiquement.
- Ajout des notifications préalables, de l'expiration côté serveur, de l'idempotence et de la gestion des réponses concurrentes.
- Le délai par défaut, le paramétrage, le canal de notification et les frais de livraison restent à préciser.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.35 — 13 novembre 2026
- Enregistrement de Q254 : en cas de refus client d'une modification proposée, seule la ligne concernée est annulée ; les autres lignes de la sous-commande continuent.
- Définition des responsabilités Sales, Inventory, Finance et Communication pour le recalcul des montants, la libération de stock et l'information du client.
- Les règles de recalcul des frais, d'annulation de toutes les lignes et d'articulation multi-coopératives restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.34 — 13 novembre 2026
- Enregistrement de Q253 : la coopérative peut proposer une réduction de quantité ou un remplacement de produit, mais toute modification requiert l'acceptation explicite du client.
- Définition des responsabilités Sales, Cooperatives, Catalog/Products, Inventory, Finance et Communication ; isolation des propositions au niveau de la sous-commande.
- Les délais de réponse, refus/absence de réponse, traitement partiel et ajustements de frais/prix restent à préciser avant codage.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.33 — 13 novembre 2026
- Enregistrement de Q252 : adoption officielle d'une stratégie V1 priorisant le parcours vertical client → coopérative → transporteur.
- Définition d'un Finance V1 minimal et d'un phasage par dépendances ; les fonctions avancées restent au backlog sans bloquer le premier parcours exploitable.
- Maintien des frontières de domaines, des exigences de sécurité et des règles non encore tranchées (notamment l'acceptation client d'une réduction de quantité).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.32 — 13 novembre 2026
- Enregistrement de Finance Q251 : la dernière version validée reste officielle et utilisable pendant l'examen d'une nouvelle version.
- Après validation de la nouvelle version, l'ancienne devient « remplacée » et reste dans l'historique ; distinction à prévoir entre dernière version déposée et dernière version validée.
- Aucun code modifié ; aucune fusion.

### Version 3.31 — 13 novembre 2026
- Enregistrement de Finance Q250 : tout remplacement, y compris après validation, crée une nouvelle version au statut « à vérifier ».
- Aucune version précédente n'est écrasée ; la nouvelle pièce repasse par le circuit de validation Q247-Q248. Aucun code modifié ; aucune fusion.

### Version 3.30 — 13 novembre 2026
- Enregistrement de Finance Q249 : après un refus, le `cooperative_admin` peut déposer une nouvelle version au statut « à vérifier ».
- Conservation de la version refusée, de son motif et du lien d'historique ; le nouveau dépôt repasse par le circuit de validation Q247-Q248.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.29 — 13 novembre 2026
- Enregistrement de Finance Q248 : validation/refus par `super_admin_root` sur toutes les coopératives et `super_admin` affecté sur son périmètre ; le `cooperative_admin` ne valide pas ses propres dépôts.
- Séparation dépôt/contrôle, vérification serveur et journalisation de la décision par version ; aucun code modifié et aucune fusion.

### Version 3.28 — 13 novembre 2026
- Enregistrement de Finance Q247 : un justificatif déposé est immédiatement visible par les personnes autorisées, mais conserve le statut « à vérifier » jusqu'à validation.
- Le dépôt n'est pas une acceptation financière ; le statut est suivi par version et les changements sont journalisés. L'autorité de validation reste à définir.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.27 — 13 novembre 2026
- Enregistrement de Finance Q246 : ajout et remplacement des justificatifs autorisés à `super_admin_root`, au `super_admin` affecté et au `cooperative_admin` de la coopérative concernée.
- Chaque opération est contrôlée côté serveur et journalisée ; le remplacement crée une nouvelle version sans écraser l'historique Q245.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.26 — 13 novembre 2026
- Enregistrement de Finance Q245 : conserver toutes les versions successives des justificatifs avec date, auteur et historique ; seule la dernière est considérée comme actuelle.
- Les versions antérieures restent consultables selon les habilitations, sans écrasement, avec traçabilité des ajouts et remplacements.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.25 — 13 novembre 2026
- Enregistrement de Finance Q244 : téléchargement individuel uniquement des justificatifs dans le premier release ; aucun téléchargement groupé ZIP.
- Maintien des contrôles d'accès, liens privés temporaires et journaux d'audit par justificatif.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.24 — 13 novembre 2026
- Enregistrement de Finance Q243 : le `cooperative_admin` peut consulter et télécharger les justificatifs des créances de sa propre coopérative.
- Maintien de l'isolation entre coopératives ; contrôle serveur et journalisation de chaque téléchargement, avec liens privés et temporaires.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.23 — 13 novembre 2026
- Enregistrement de Finance Q242 : consultation des créances MAHLINE contestées ou annulées et de leurs justificatifs autorisée à `super_admin_root`, au `super_admin` affecté et au `cooperative_admin` de la coopérative concernée.
- Restriction du périmètre par coopérative, refus d'accès aux rôles `user` et `customer` par défaut, contrôle serveur et journalisation des consultations/exports.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.22 — 13 novembre 2026
- Enregistrement de Finance Q241 : conserver dans les rapports financiers la créance initiale, l'écriture corrective, le motif, les justificatifs et l'auteur de la décision d'annulation.
- Garantir la traçabilité historique et distinguer l'annulation des imputations déjà exécutées et des créances distinctes de Q233.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.21 — 13 novembre 2026
- Enregistrement de Finance Q240 : si la contestation est acceptée et la créance MAHLINE déclarée invalide, annuler uniquement le solde restant par une écriture corrective distincte.
- Préserver la créance initiale et toutes les imputations exécutées ; les montants déjà versés restent une créance distincte à récupérer selon Q233, sans double annulation.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.20 — 13 novembre 2026
- Enregistrement de Finance Q239 : après rejet formel de la contestation, réactiver automatiquement la créance MAHLINE valide en conservant son rang FIFO initial et sans réimputation rétroactive.
- Le rang reste déterminé par la date de création immuable (Q236), puis l'ULID croissant en cas d'égalité (Q237).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.19 — 13 novembre 2026
- Enregistrement de Finance Q238 : suspendre uniquement la créance MAHLINE contestée et l'exclure temporairement du recouvrement, sans bloquer les autres créances non contestées.
- Précision de périmètre : cette règle concerne les créances MAHLINE issues de montants déjà versés à tort (Q233), distinctes des crédits ordinaires contestés (Q230-Q232).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.18 — 13 novembre 2026
- Enregistrement de Finance Q237 : lorsque plusieurs créances ont exactement la même date et heure de création, les départager par leur identifiant ULID en ordre croissant.
- La date de création initiale reste le premier critère FIFO (Q236) ; l'ULID de la créance constitue le critère secondaire stable et déterministe.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.17 — 13 novembre 2026
- Enregistrement de Finance Q236 : l'ancienneté FIFO d'une créance est déterminée par sa date de création initiale, conservée de manière immuable.
- Les changements de statut, validations ultérieures, contestations, suspensions et reports ne modifient pas cette date ni le rang de recouvrement.
- Un critère technique stable et déterministe reste à définir pour les dates identiques.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.16 — 13 novembre 2026
- Enregistrement de Finance Q235 : lorsque plusieurs créances MAHLINE existent pour une coopérative, récupérer la plus ancienne en premier (FIFO) et l'apurer avant de passer à la suivante.
- En cas de reversement insuffisant, reporter le solde de la créance la plus ancienne sans modifier sa priorité ; les créances plus récentes restent en attente.
- Alignement avec Q234 : les créances MAHLINE restent prioritaires sur les crédits ordinaires coopératifs, qui conservent leur propre FIFO.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.15 — 13 novembre 2026
- Enregistrement de Finance Q234 : récupérer prioritairement les créances de MAHLINE sur la coopérative avant l'imputation des crédits ordinaires.
- Après règlement des créances prioritaires, appliquer le solde du reversement aux crédits ordinaires selon FIFO ; reporter les soldes non réglés sans altérer leur ancienneté.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.14 — 13 novembre 2026
- Enregistrement de Finance Q233 : les montants déjà versés à la coopérative au titre d'un crédit invalidé deviennent une créance distincte de MAHLINE.
- Récupérer cette créance par déduction sur les prochains reversements, avec traçabilité et solde ; distinguer cette récupération de l'annulation du solde non imputé (Q232).
- Les règles de priorité, plafonds et cas de reversements insuffisants restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.13 — 13 novembre 2026
- Enregistrement de Finance Q232 : si une contestation est acceptée, annuler le solde restant du crédit invalide par une écriture corrective distincte.
- Conserver le crédit initial, les imputations antérieures et tous les liens de traçabilité ; aucune suppression ou réécriture d'historique.
- Le traitement correctif des montants déjà imputés sur des reversements exécutés reste à cadrer.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.12 — 13 novembre 2026
- Enregistrement de Finance Q231 : lorsqu'une contestation est rejetée, le crédit valide est réactivé en conservant son rang FIFO d'origine.
- La suspension n'altère pas l'ancienneté ; aucune réimputation rétroactive des reversements déjà exécutés n'est effectuée.
- Les modalités de résolution, la preuve, le traitement des crédits partiellement imputés, la concurrence et l'idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.11 — 13 novembre 2026
- Enregistrement de Finance Q230 : suspendre uniquement le crédit contesté ; les autres crédits non contestés continuent d'être imputés selon FIFO.
- La suspension ne vaut ni annulation ni suppression ; le traitement de résolution, le rang FIFO après réactivation et les habilitations restent à préciser.
- Alignement de la matrice Finance sur l'isolation d'une contestation au niveau du crédit concerné.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.10 — 13 novembre 2026
- Enregistrement de Finance Q229 : lorsque plusieurs crédits coopérative sont en attente, les imputer selon FIFO, du crédit le plus ancien au plus récent.
- Un crédit ancien est apuré avant de passer au suivant ; si le reversement est insuffisant, son solde est reporté et les crédits plus récents restent en attente.
- Horodatage de référence, départage des créations simultanées, concurrence et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.09 — 13 novembre 2026
- Enregistrement de Finance Q228 : tout crédit coopérative supérieur au prochain reversement est imputé automatiquement en plusieurs fois ; le solde non utilisé est reporté aux reversements suivants jusqu'à épuisement.
- Chaque imputation est plafonnée au solde du crédit et au montant du reversement, avec historique et liens de traçabilité conservés ; aucune validation manuelle n'est requise pour le report.
- Priorité entre crédits multiples, reversements nuls/annulés, concurrence, rapprochement et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.08 — 13 novembre 2026
- Enregistrement de Finance Q227 : avant exécution du reversement, intégrer la correction de commission au montant à verser ; après exécution, créer un crédit au bénéfice de la coopérative, imputable sur son prochain reversement.
- Le crédit est une écriture distincte, liée au remboursement, à la correction de commission, à la commande et au reversement initial ; le suivi du solde doit empêcher toute double régularisation.
- Imputation partielle/totale, report du solde, concurrence, rapprochement et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.07 — 13 novembre 2026
- Enregistrement de Finance Q226 : dès la confirmation définitive du remboursement par Sales, Finance enregistre immédiatement la correction de commission.
- Le rapprochement périodique et la réception effective des fonds par le client ne retardent pas la correction ; une demande non confirmée ne la déclenche pas.
- Alignement du déclenchement avec le recalcul cumulatif et les protections définis par Q223 à Q225 ; contrat d'événement, reprise et idempotence restent à préciser techniquement.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.06 — 13 novembre 2026
- Enregistrement de Finance Q225 : pour des remboursements partiels successifs, recalculer la commission à annuler sur le montant net cumulé remboursé et comptabiliser uniquement la différence par rapport aux corrections déjà enregistrées.
- Chaque correction reste une écriture distincte liée à la commission initiale et au remboursement déclencheur ; le cumul est plafonné à la commission initiale et les remboursements ne doivent pas être comptés deux fois.
- Les règles techniques de précision, d'arrondi au centime, de concurrence et d'idempotence restent à définir.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.05 — 13 novembre 2026
- Enregistrement de Finance Q224 : l'assiette de correction d'une commission après remboursement partiel est le montant net des produits effectivement remboursés, après remise et hors livraison.
- Alignement avec Q218 et Q223 : taux de commission figé à la commande, écriture corrective distincte et historique initial conservé ; les règles techniques de centimes et de remboursements successifs restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.04 — 13 novembre 2026
- Enregistrement de Finance Q223 : une commission est corrigée à hauteur du montant des produits remboursés ; remboursement total = annulation totale correspondante, remboursement partiel = correction proportionnelle.
- La correction est enregistrée séparément et liée à la commission initiale et au remboursement, sans effacer l'historique.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.03 — 13 novembre 2026
- Enregistrement de Finance Q222 : seuls les produits éligibles à une promotion participent à l'assiette de prorata et à la répartition d'une remise globale ; les produits exclus sont écartés.
- Alignement de la règle de ventilation : calcul sur les montants pré-remise des seuls produits éligibles, puis application du plus fort reste et du départage Q221.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.02 — 13 novembre 2026
- Enregistrement de Finance Q221 : en cas d'égalité des restes fractionnaires, départager par identifiant stable de coopérative en ordre croissant.
- Précision de Q220 : la règle du plus fort reste dispose désormais d'un critère déterministe de départage.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.01 — 13 novembre 2026
- Enregistrement de Finance Q220 : les centimes résiduels d'une remise répartie sont affectés selon la méthode du plus fort reste, avec départage déterministe des égalités.
- La somme des parts par sous-commande doit correspondre exactement à la remise globale ; aucun écart de centime n'est accepté.

### Version 3.00 — 13 novembre 2026
- Enregistrement de Finance Q219 : une remise globale de panier multi-coopératives est répartie proportionnellement aux montants de produits de chaque coopérative avant remise.
- La ventilation figée par sous-commande alimente le calcul du montant net et de la commission de chaque coopérative ; les règles d'arrondi et de reliquat restent ouvertes.

### Version 2.99 — 13 novembre 2026
- Enregistrement de Finance Q218 : la commission est calculée sur le montant net des produits après déduction des remises, hors frais de livraison.
- Les montants avant remise, remises, assiette nette, taux et commission doivent être traçables par commande ; les règles de répartition des remises multi-coopératives restent ouvertes.

### Version 2.98 — 13 novembre 2026
- Enregistrement de Finance Q217 : chaque nouveau taux possède une date d'effet définie par `super_admin_root` ; les commandes déjà créées conservent le taux figé lors de leur création.
- Confirmation de l'absence d'effet rétroactif sur les commandes antérieures à la date d'effet.

### Version 2.97 — 13 novembre 2026
- Enregistrement de Finance Q216 : le taux de commission est contractuel et seul `super_admin_root` peut le saisir ou le modifier ; l'historique et la justification contractuelle doivent être traçables.

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
| FR-011 | Commissions et reversements aux coopératives | Partiellement défini — Q213 à Q215 validées ; modalités de calcul détaillées, rapprochement et exceptions à compléter |
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
  - **Conséquences / contraintes :** le `super_admin` ne peut agir que pour une coopérative dont il est responsable ; le `super_admin_root` conserve une autorité globale. La réactivation doit être une action explicite, après vérification/correction de la cause de suspension, et être journalisée (acteur, date/heure, incident, destinataire, motif et état avant/après). Aucune réactivation automatique n'est autorisée. Les modalités précises de vérification préalable et les éventuelles restrictions selon le type d'incident restent à définir.  - **Question 68 — récidive immédiate après réactivation par le `super_admin` :** D — le `super_admin_root` décide au cas par cas de la conduite à tenir.
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

## Matrice Media — questions 1 à 90 (réponses validées)

- **Statut :** Q1 à Q90 validées ; les décisions ci-dessous constituent le cadrage fonctionnel du premier release.
- **Règle commune :** toutes les actions restent soumises au périmètre coopératif, aux permissions du module, à l'état antivirus et à la journalisation prévue. Les décisions ne créent aucun contournement des Policies/Gates ni des règles métier.

### 1. Périmètre, accès et gouvernance
- **Q1 — Périmètre de la bibliothèque : A.** Root : toutes les coopératives ; super_admin : coopératives affectées ; cooperative_admin et user : leur propre coopérative, avec les permissions applicables.
- **Q2 — Téléversement : B.** Root, super_admin et cooperative_admin peuvent téléverser ; user ne le peut pas. Chaque fichier est validé (format, taille, quota, antivirus).
- **Q3 — Consultation et aperçu : décision validée.** Les quatre rôles consultent et prévisualisent les médias autorisés dans leur périmètre ; aucun aperçu/téléchargement pour un fichier en attente antivirus ou en quarantaine.
- **Q4 — Téléchargement individuel de l'original : A.** Les quatre rôles, selon périmètre et permissions ; contrôle serveur à chaque requête, antivirus propre obligatoire.
- **Q5 — Métadonnées descriptives : A.** Les trois administrateurs modifient nom/titre/description/catégorie/tags dans leur périmètre ; user est en lecture seule. Le remplacement physique est distinct.
- **Q6 — Associations : A.** Les trois administrateurs créent/suppriment des associations dans leur périmètre ; user consulte seulement. Retirer une association ne supprime ni l'entité métier ni un média encore référencé.
- **Q7 — Demande de suppression : C.** Root et super_admin seulement, dans leur périmètre ; contrôle des références avant toute suppression physique.
- **Q8 — Quotas et capacité : A.** Root seul gère les quotas globaux, les plans et les capacités exceptionnelles ; les autres rôles consultent la consommation de leur périmètre.
### 2. Sécurité, traitement et administration
- **Q9 — Incidents de sécurité : A.** Root seul consulte l'historique antivirus, infections, quarantaines, rescans, libérations et suppressions de sécurité ; journal append-only et immuable.
- **Q10 — Rescan et libération de quarantaine : A.** Root seul demande un nouveau scan ; après résultat propre, une approbation explicite root distincte est nécessaire pour libérer le fichier. Tout est journalisé.
- **Q11 — Journaux d'activité Media : C.** Root et super_admin dans leur périmètre ; cooperative_admin et user n'y accèdent pas. Les incidents de sécurité restent root-only.
- **Q12 — Relance de traitements échoués : A.** Root et super_admin dans leur périmètre peuvent relancer manuellement ; les autres rôles ne le peuvent pas. Les retries automatiques sont gérés par le système.
- **Q13 — Métadonnées techniques : A.** Les trois administrateurs voient les détails techniques dans leur périmètre ; user ne voit que les informations simplifiées utiles.
- **Q14 — Remplacement d'un fichier : B.** Les trois administrateurs dans leur périmètre. Remplacement atomique : validation complète du nouveau fichier avant substitution ; en cas d'échec, l'ancien reste utilisable et les associations sont préservées.
- **Q15 — Téléversement par lot : A.** Les trois administrateurs peuvent envoyer jusqu'à 20 fichiers par lot ; user ne peut pas. Progression, état, reprise et annulation par fichier.
- **Q16 — Annulation/reprise d'envoi : A.** L'auteur gère ses envois ; root supervise tous les envois, super_admin intervient dans ses coopératives affectées, cooperative_admin dans sa coopérative ; user sans droit d'envoi.
- **Q17 — Médias supprimés/en attente de suppression : A.** Consultation réservée à root et super_admin dans leur périmètre ; pas de corbeille ni restauration ordinaire.
- **Q18 — Nouvelle tentative de suppression physique échouée : A.** Root et super_admin dans leur périmètre, avec confirmation fraîche à chaque tentative.
- **Q19 — Restauration exceptionnelle depuis sauvegarde : B.** Root et super_admin dans leur périmètre, avec justification, contrôles de périmètre, antivirus avant disponibilité et audit immuable. Une restauration ordinaire ne réintroduit pas un fichier définitivement supprimé.
- **Q20 — Statistiques Media : A.** Root global/par coopérative ; super_admin sur ses coopératives ; cooperative_admin sur sa coopérative ; user voit seulement une consommation simplifiée.
- **Q21 — Paramètres techniques globaux : A.** Root seul modifie formats, limites, dimensions, qualité WebP et règles de traitement ; modifications journalisées.
- **Q22 — Permissions Media des utilisateurs : A.** Root seul attribue, modifie ou retire les permissions Media.
- **Q23 — Fichier en attente antivirus : A.** Les quatre rôles voient le statut dans leur périmètre, mais aucun aperçu, téléchargement, association ou distribution avant validation.
- **Q24 — Détails de quarantaine : B.** Root et super_admin dans leur périmètre ; cooperative_admin/user n'y accèdent pas. Voir les détails ne donne aucun droit de rescan ou de libération.
- **Q25 — Erreurs de traitement : A.** Détails techniques visibles par root et super_admin dans leur périmètre ; les autres reçoivent un message simple d'indisponibilité.
- **Q26 — Export de la liste Media : C.** Root seul ; aucun export pour les autres rôles. Exclure les fichiers en attente/quarantaine et les informations sensibles de sécurité.
- **Q27 — Recherche Media : A.** Recherche limitée au périmètre et aux permissions du rôle ; les états antivirus et restrictions sont respectés.
- **Q28 — Dossiers personnalisés : A.** Pas de dossiers personnalisés au premier release ; organisation automatique uniquement.
- **Q29 — Règles de classification automatique : A.** Root seul les modifie ; changements journalisés.
- **Q30 — Médias non associés : A.** Les quatre rôles peuvent consulter les médias non associés dans leur périmètre, sous réserve des permissions.
- **Q31 — Liens de partage publics/externes : A.** Aucun lien public au premier release ; accès privé soumis aux contrôles MAHLINE.
- **Q32 — Historique des modifications : A.** Root et super_admin dans leur périmètre seulement ; cooperative_admin/user n'accèdent pas à l'historique détaillé.
- **Q33 — Médias archivés/inactifs : A.** Les quatre rôles peuvent les consulter dans leur périmètre ; cela n'accorde aucun droit supplémentaire de modification ou téléchargement.
- **Q34 — Réactivation d'un média archivé/inactif : A.** Root et super_admin dans leur périmètre ; vérification des références, permissions et antivirus. La quarantaine ne peut pas être contournée.
- **Q35 — Téléchargement groupé : C.** Root seul ; le téléchargement individuel reste régi par Q4.
- **Q36 — Détails techniques des sauvegardes : A.** Root seul consulte emplacements, volumes, dates/états, restaurations et incidents. Les autres rôles voient seulement leur consommation selon Q8.
- **Q37 — Identité des auteurs d'actions Media : A.** Détails visibles par root et super_admin dans leur périmètre ; pas de détails pour cooperative_admin/user.
- **Q38 — Statistiques d'activité par utilisateur : A.** Root et super_admin dans leur périmètre seulement (envois, remplacements, téléchargements, associations).
- **Q39 — Seuils d'alerte de stockage : A.** Root seul configure les seuils : alerte à 80 %, alerte renforcée à 95 %, blocage des nouveaux envois à 100 % ; capacité exceptionnelle également root-only.
- **Q40 — Archivage/désactivation d'un média : A.** Root et super_admin dans leur périmètre ; consultation selon Q33 et réactivation selon Q34.
### 3. Produits, associations, statistiques et optimisation des images
- **Q41 — Modification de catégorie : A.** Les trois administrateurs dans leur périmètre ; user en lecture seule ; aucune modification implicite des associations.
- **Q42 — Gestion des tags/mots-clés : A.** Les trois administrateurs dans leur périmètre ; user en lecture seule.
- **Q43 — Titre et description : A.** Les trois administrateurs dans leur périmètre ; toute modification est journalisée.
- **Q44 — Paramètres d'affichage : A.** Les trois administrateurs gèrent image principale et ordre/position de galerie ; user consulte seulement.
- **Q45 — Changement d'image principale produit : A.** Les trois administrateurs choisissent parmi les médias déjà associés ; aucun fichier physique supprimé ; validation antivirus obligatoire.
- **Q46 — Réorganisation de galerie : A.** Les trois administrateurs modifient l'ordre dans leur périmètre ; associations préservées.
- **Q47 — Même média sur plusieurs produits : A.** Les trois administrateurs peuvent créer plusieurs références dans leur périmètre ; un seul fichier physique, associations indépendantes.
- **Q48 — Retrait d'une image de galerie : A.** Les trois administrateurs retirent l'association ; le fichier reste dans la bibliothèque et ses autres références restent intactes.
- **Q49 — Consultation intermodules : A.** Les quatre rôles consultent les médias utilisés dans plusieurs modules seulement si leurs droits dans le module concerné le permettent ; un accès dans un module ne donne pas accès aux autres.
- **Q50 — Statistiques détaillées d'un média : A.** Root et super_admin dans leur périmètre voient références produits/modules, téléchargements et dernière utilisation.
- **Q51 — Historique des téléchargements : A.** Root et super_admin dans leur périmètre voient utilisateur, date/heure, média et nombre de téléchargements.
- **Q52 — Historique des remplacements : A.** Root et super_admin dans leur périmètre voient auteur, date, caractéristiques de l'ancien fichier et résultat du nouveau traitement.
- **Q53 — Classement des médias les plus utilisés : A.** Root et super_admin dans leur périmètre ; calcul selon téléchargements, vues et associations produits.
- **Q54 — Médias récemment ajoutés : A.** Les quatre rôles les voient dans leur périmètre avec date et statut ; attente antivirus sans aperçu/téléchargement, quarantaine inaccessible.
- **Q55 — Médias récemment modifiés : A.** Les quatre rôles les voient dans leur périmètre ; l'historique détaillé reste réservé à root/super_admin.
- **Q56 — Médias inutilisés/non associés : A.** Les quatre rôles les consultent dans leur périmètre ; aucun droit supplémentaire de modification ou suppression.
- **Q57 — Détails d'échec technique : A.** Root et super_admin dans leur périmètre voient les détails ; les autres voient un message simple.
- **Q58 — Médias volumineux/forte consommation : A.** Les trois administrateurs peuvent les identifier dans leur périmètre ; cela ne donne aucun droit de modifier les quotas.
- **Q59 — Doublons SHA-256 : A.** Les trois administrateurs consultent les doublons dans leur périmètre ; aucune suppression automatique.
- **Q60 — Demande de fusion de doublons : A.** Root et super_admin dans leur périmètre ; vérifier hashes, références, permissions et antivirus, transférer les références puis journaliser.
- **Q61 — Confirmation de fusion : A.** Root et super_admin dans leur périmètre confirment explicitement ; opération atomique, source préservée en cas d'échec.
- **Q62 — Annulation d'une fusion en attente : A.** Root et super_admin dans leur périmètre ; une fusion exécutée n'est pas annulable directement et exige une restauration contrôlée journalisée.
- **Q63 — Historique des fusions : A.** Root et super_admin seulement, dans leur périmètre ; médias concernés, acteur, date, références transférées et résultat.
- **Q64 — Consultation des versions optimisées : A.** Les quatre rôles consultent la variante adaptée dans leur périmètre après validation antivirus ; original conservé 24 h après validation des variantes, puis supprimé automatiquement.
- **Q65 — Téléchargement de l'original pendant les 24 h : A.** Les quatre rôles selon Q4 ; la période de conservation ne crée aucun droit supplémentaire. Après expiration, seule la version optimisée demeure.
- **Q66 — Échec d'optimisation d'image : A.** Conserver l'original et l'utiliser en fallback si possible ; trois tentatives automatiques, puis relance manuelle par root/super_admin ; message simple aux autres ; aucune suppression automatique.
- **Q67 — Demande de réoptimisation : A.** Root et super_admin dans leur périmètre seulement ; action journalisée et soumise aux limites techniques.
- **Q68 — Détails des variantes optimisées : A.** Les trois administrateurs voient miniature/normal/grand, dimensions, taille et format ; user voit seulement l'affichage adapté.
- **Q69 — Suppression manuelle d'une variante obsolète : A.** Root et super_admin dans leur périmètre ; vérifier les dépendances, ne jamais supprimer l'original, permettre la régénération et journaliser.
- **Q70 — Informations de compatibilité : A.** Les quatre rôles voient les informations adaptées à leur niveau ; les réglages techniques avancés restent gouvernés par Q13/Q21.
### 4. Vidéos et documents PDF
- **Q71 — Média dont le format original n'est pas compatible navigateur : A.** Les quatre rôles peuvent consulter une version compatible approuvée par antivirus ; sinon message simple, sans exposition du fichier brut.
- **Q72 — Conversion en cours : A.** Les quatre rôles voient le statut dans leur périmètre ; aucun aperçu, téléchargement du résultat, association ou distribution avant validation antivirus et traitement.
- **Q73 — Lecture vidéo convertie : A.** Les quatre rôles peuvent lire la vidéo convertie avec succès dans leur périmètre après validation antivirus.
- **Q74 — Modification de miniature vidéo : A.** Les trois administrateurs dans leur périmètre ; user en lecture seule.
- **Q75 — Choix de l'instant de capture pour miniature vidéo : A.** Les trois administrateurs sélectionnent l'instant dans leur périmètre ; génération automatique, prévisualisation et retour possible à la miniature automatique.
- **Q76 — Suppression de miniature personnalisée : A.** Les trois administrateurs peuvent revenir à la miniature automatique dans leur périmètre ; vidéo originale, associations et autres variantes restent intactes.
- **Q77 — Informations techniques détaillées d'une vidéo : A.** Les trois administrateurs consultent durée, résolution, formats, taille, état de conversion et codecs ; user voit des informations simples utiles à la lecture.
- **Q78 — Téléchargement de l'original vidéo avant fin de conversion : A.** Après validation antivirus, les quatre rôles peuvent télécharger l'original selon Q4 même si la conversion est en cours ; aucun accès avant validation antivirus.
- **Q79 — Relance manuelle d'une conversion vidéo échouée : A.** Root et super_admin dans leur périmètre, après les trois tentatives automatiques ; action journalisée.
- **Q80 — Suppression physique avec associations conservées : A.** Interdite pour tous les rôles ; détacher les associations autorisées, vérifier qu'aucune référence valide ne subsiste, puis seulement supprimer original et variantes et journaliser.
- **Q81 — Consultation de documents PDF : A.** Les quatre rôles dans leur périmètre, sous réserve des permissions du module métier ; l'accès Media ne contourne jamais les droits sur le document.
- **Q82 — Remplacement d'un PDF associé : A.** Les trois administrateurs dans leur périmètre ; valider format/taille (5 Mo max), antivirus et nouveau fichier avant remplacement ; conserver associations et ancien fichier si échec.
- **Q83 — Demande de suppression d'un PDF associé : A.** Root et super_admin dans leur périmètre ; vérifier références et obligations légales/métier de conservation avant toute suppression physique.
- **Q84 — Téléchargement de PDF : A.** Les quatre rôles selon permissions métier et périmètre, après validation antivirus et hors quarantaine/blocage ; téléchargement journalisé.
- **Q85 — Statistiques d'utilisation des PDF : A.** Root et super_admin dans leur périmètre ; consultations, téléchargements, documents les plus utilisés et dernière utilisation.
- **Q86 — Métadonnées descriptives d'un PDF : A.** Les trois administrateurs modifient titre, description, catégorie, mots-clés et nom affiché dans leur périmètre ; le contenu physique reste inchangé et les modifications sont journalisées.
- **Q87 — Association d'un PDF à plusieurs éléments métier : A.** Les trois administrateurs dans leur périmètre ; un seul fichier physique, associations indépendantes ; retirer une association ne supprime pas le fichier.
- **Q88 — Ordre d'affichage des PDF d'un produit : A.** Les trois administrateurs dans leur périmètre ; seul l'ordre change, sans modification du fichier ou des associations ; action journalisée.
- **Q89 — PDF récemment ajoutés/modifiés : A.** Les quatre rôles dans leur périmètre voient nom, date, statut et association ; historique détaillé réservé à root/super_admin.
- **Q90 — Consultation des doublons PDF : A.** Les trois administrateurs dans leur périmètre ; détection SHA-256, aucune suppression automatique ; fusion/suppression suivent Q60–Q63 et les règles de références.

- **Limites de périmètre confirmées :** pas de dossiers personnalisés, pas de liens publics de partage, pas de corbeille/restauration ordinaire, pas de suppression automatique des doublons, et aucune suppression physique tant qu'une référence valide ou une obligation de conservation subsiste.

### Complément à la matrice Media — question 91
- **Q91 — Demande de fusion de deux documents PDF identiques : A.** Root et super_admin peuvent demander la fusion dans leur périmètre respectif.
- **Conséquences / contraintes :** avant toute fusion, le système vérifie l'identité des fichiers (SHA-256), l'ensemble des références, les permissions et l'état antivirus. Les références valides sont transférées de manière contrôlée ; l'opération est journalisée. Aucune fusion ne doit contourner les règles d'accès ou de conservation.

### Version 2.16 — 13 novembre 2026
- Ajout de la décision Media Q91 : demande de fusion de doublons PDF réservée à root et super_admin dans leur périmètre.
- Aucune modification du code applicatif ; aucune fusion de branche.
### Complément à la matrice Media — question 92
- **Q92 — Confirmation définitive de la fusion de deux PDF : A.** Root et super_admin peuvent confirmer explicitement la fusion dans leur périmètre respectif.
- **Conséquences / contraintes :** la confirmation est distincte de la demande. L'opération doit être atomique : si une étape échoue, le document source et ses références restent intacts. Les contrôles de hash, références, permissions et antivirus sont réexécutés au moment de confirmer ; l'acteur, la date, les documents concernés, les références transférées et le résultat sont journalisés.

### Version 2.17 — 13 novembre 2026
- Ajout de la décision Media Q92 : confirmation explicite de fusion réservée à root et super_admin dans leur périmètre, avec garantie d'atomicité.
- Aucune modification du code applicatif ; aucune fusion de branche.
### Complément à la matrice Media — question 93
- **Q93 — Annulation d'une demande de fusion PDF en attente : A.** Root et super_admin peuvent annuler une demande non exécutée dans leur périmètre respectif.
- **Conséquences / contraintes :** l'annulation est permise uniquement tant que la fusion n'a pas été exécutée. Une fusion déjà exécutée ne peut pas être annulée directement ; toute récupération nécessite une restauration contrôlée, autorisée et journalisée. L'acteur, la date, le motif, les documents concernés et l'état de la demande sont tracés.

### Version 2.18 — 13 novembre 2026
- Ajout de la décision Media Q93 : annulation d'une demande de fusion en attente réservée à root et super_admin dans leur périmètre.
- Aucune modification du code applicatif ; aucune fusion de branche.
### Complément à la matrice Media — question 94
- **Q94 — Consultation de l'historique des fusions PDF : A.** Root et super_admin peuvent consulter l'historique détaillé dans leur périmètre respectif.
- **Conséquences / contraintes :** l'historique indique les documents concernés, l'auteur, la date et l'heure, les références transférées, le résultat de l'opération et, le cas échéant, le motif de l'annulation. Les autres rôles n'ont pas accès à cet historique détaillé. Les événements sont conservés de manière traçable et non modifiable par les utilisateurs.

### Version 2.19 — 13 novembre 2026
- Ajout de la décision Media Q94 : historique détaillé des fusions PDF consultable par root et super_admin dans leur périmètre.
- Aucune modification du code applicatif ; aucune fusion de branche.
### Complément à la matrice Media — question 95
- **Q95 — Téléchargement groupé de plusieurs PDF : A.** Root uniquement, dans son périmètre global.
- **Conséquences / contraintes :** le téléchargement groupé reste réservé à root dans le premier release, conformément à la règle générale de téléchargement groupé des médias (Q35). Chaque PDF doit être revalidé individuellement selon les permissions, le périmètre, l'état antivirus et les règles de conservation. Les fichiers en attente, en quarantaine ou bloqués sont exclus. L'opération est journalisée.

### Version 2.20 — 13 novembre 2026
- Ajout de la décision Media Q95 : téléchargement groupé de PDF réservé à root.
- Aucune modification du code applicatif ; aucune fusion de branche.
### Complément à la matrice Media — question 96
- **Q96 — Limite de téléchargement groupé de PDF : A.** 50 PDF maximum par opération, regroupés dans une archive ZIP.
- **Conséquences / contraintes :** la limite s'applique à chaque opération de téléchargement groupé réservée à root (Q95). Chaque document est contrôlé individuellement avant inclusion ; les documents non autorisés, en attente de validation antivirus, en quarantaine ou bloqués sont exclus. La génération de l'archive doit être contrôlée côté serveur et l'opération journalisée.

### Question 269 — Affichage des promotions dans la marketplace

- **Question :** Quels éléments afficher au client lorsqu'une promotion est active ?
- **Réponse :** A — prix normal barré, prix promotionnel et badge indiquant le pourcentage de réduction.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Sur la carte produit et la fiche produit, afficher le prix normal barré, le prix promotionnel applicable et le taux de réduction (ex. « -12,75 % »).
  - N'afficher ces éléments promotionnels que pendant la période de validité active (Q263).
  - Pour un produit à variantes, le prix promotionnel est calculé sur le prix applicable de la variante ; l'affichage doit rester cohérent avec la variante sélectionnée et ne pas annoncer un prix trompeur (Q256–Q265).
  - Si plusieurs prix de variantes sont présentés sur la carte, le prix « À partir de » et le taux annoncé doivent correspondre à une même variante disponible ; ne jamais combiner le prix minimum d'une variante avec le taux d'une autre.
  - Hors promotion, afficher le prix normal sans prix barré ni badge promotionnel.
  - Catalog fournit les données d'affichage ; Sales revalide le prix de la variante sélectionnée et fige les valeurs réellement appliquées à la vente.
- **À préciser ultérieurement :** règles d'affichage sur les listes à variantes, tri/filtre par prix, et présentation des promotions expirées (aucun badge actif après expiration).

### Question 268 — Arrondi du prix promotionnel

- **Question :** Comment arrondir le résultat du calcul en pourcentage lorsqu'il comporte plus de deux décimales ?
- **Réponse :** A — arrondir au centime le plus proche.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Le prix promotionnel calculé est arrondi au centime de MAD le plus proche, selon l'arrondi commercial (au demi-centime, arrondi vers le haut).
  - Exemple : 99 DH avec une réduction de 12,75 % donne 86,3775 DH, arrondi à 86,38 DH.
  - Les calculs intermédiaires utilisent une représentation décimale exacte ; aucun flottant binaire n'est autorisé pour les montants.
  - Cet arrondi explicite concerne le résultat monétaire calculé de la promotion ; il ne permet jamais de corriger automatiquement un prix ou un taux saisi invalide (Q262, Q267).
  - Le même résultat doit être obtenu dans Products, Catalog, Sales et Finance ; Sales conserve le prix effectivement appliqué dans le panier/commande.
- **Responsabilités :** Products conserve le taux ; Catalog calcule/expose le prix promotionnel ; Sales revalide et fige le prix appliqué ; Finance consomme les montants figés.
- **Règle technique :** centraliser la règle d'arrondi monétaire pour éviter des écarts entre domaines et interfaces ; tests obligatoires sur les cas de demi-centime et les limites de précision.

### Question 267 — Précision du taux promotionnel

- **Question :** Quelle précision autoriser pour le pourcentage de réduction ?
- **Réponse :** B — jusqu'à deux décimales.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Le taux de réduction peut comporter jusqu'à deux décimales (ex. 10 %, 10,5 % ou 10,50 %).
  - Le taux doit être strictement supérieur à 0 et inférieur ou égal à 90 % (Q266).
  - Toute valeur comportant plus de deux décimales ou dépassant 90 % doit être refusée côté serveur, sans arrondi ni troncature silencieux.
  - Le taux est stocké et calculé avec une représentation décimale exacte, jamais en virgule flottante binaire.
  - La règle de calcul et d'arrondi du prix final remisé reste à définir avant codage.
- **Responsabilités :** Products valide et conserve le taux ; Catalog calcule le prix affiché ; Sales revalide et fige le montant réellement appliqué.
- **À préciser avant codage :** règle d'arrondi monétaire du résultat et comportement si le prix calculé comporte une fraction inférieure au centime.

### Question 266 — Taux maximal de réduction

- **Question :** Quel taux maximal de réduction une coopérative peut-elle définir pour une promotion ?
- **Réponse :** C — maximum 90 %.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Le taux de réduction d'une promotion doit être strictement positif et ne peut pas dépasser 90 %.
  - Toute valeur supérieure à 90 % doit être refusée côté serveur ; la validation côté client ne suffit pas.
  - Une réduction de 100 % est donc interdite.
  - Le taux est unique au niveau du produit et s'applique au prix normal applicable de chaque variante (Q264–Q265).
  - Les règles monétaires déjà décidées restent applicables au prix final ; aucune modification silencieuse par arrondi ou troncature.
- **À préciser avant codage :** précision autorisée pour le taux (entier ou décimales), règle exacte d'arrondi du prix remisé et traitement des prix finaux inférieurs à un centime.

### Question 265 — Mode de calcul de la promotion sur les variantes

- **Question :** Comment calculer la promotion d'un produit applicable à toutes ses variantes ?
- **Réponse :** B — appliquer un pourcentage de réduction au prix applicable de chaque variante.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - La coopérative définit un taux de réduction unique au niveau du produit.
  - Le taux s'applique au prix normal applicable de chaque variante : prix spécifique de variante s'il existe, sinon prix de base hérité du produit (Q256).
  - Exemple : pour une réduction de 10 %, une variante à 100 DH passe à 90 DH et une variante à 150 DH passe à 135 DH.
  - La promotion n'est active que pendant sa période de validité (Q263) et s'applique à toutes les variantes (Q264).
  - Le taux doit être validé côté serveur dans une plage autorisée ; la plage maximale/minimale et la précision autorisée du taux restent à définir.
  - Le prix remisé doit respecter la limite de deux décimales et les règles de calcul monétaire exact (Q261–Q262). Aucun arrondi ou troncature silencieux n'est autorisé.
  - Catalog expose le prix remisé applicable ; Sales revalide et fige le prix réellement appliqué à la variante dans la vente.
- **Responsabilités :** Products stocke le taux et la période de promotion ; Catalog calcule/expose le prix promotionnel applicable ; Sales revalide et fige les montants de la vente ; Finance consomme les montants figés.
- **À préciser avant codage :** règle d'arrondi lorsque le calcul en pourcentage produit plus de deux décimales, plage/precision du taux, cumul éventuel avec d'autres remises (actuellement hors périmètre).

### Question 264 — Portée des promotions sur les variantes

- **Question :** Comment appliquer une promotion lorsqu'un produit possède plusieurs variantes ?
- **Réponse :** A — une seule promotion au niveau du produit, appliquée à toutes ses variantes.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - La promotion est définie au niveau du produit, jamais individuellement sur une variante au premier release.
  - Lorsqu'une promotion du produit est active, elle s'applique à toutes les variantes de ce produit.
  - Les variantes conservent leurs prix applicables propres selon la règle Q256 : prix spécifique facultatif, sinon héritage du prix de base du produit.
  - Le prix promotionnel doit être défini et validé selon les règles monétaires déjà retenues ; le calcul du prix remisé pour chaque variante devra respecter la précision maximale de deux décimales et ne pourra pas être corrigé silencieusement.
  - Catalog expose le prix final applicable à chaque variante et Sales revalide puis fige le prix réellement sélectionné.
- **Responsabilités :** Products possède la promotion du produit et les prix des variantes ; Catalog calcule/expose le prix applicable ; Sales valide et fige le prix de la variante choisie.
- **Hors périmètre :** promotions différentes par variante au premier release.
- **À préciser ultérieurement :** si le prix promotionnel du produit est un montant fixe identique pour toutes les variantes ou une remise calculée sur le prix applicable de chaque variante ; le mode de calcul doit être arrêté avant codage des promotions.

### Question 263 — Promotions simples au premier release

- **Question :** Les coopératives doivent-elles pouvoir proposer des promotions sur leurs produits ?
- **Réponse :** B — promotions simples avec prix normal, prix promotionnel et dates de début et de fin.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Une coopérative peut définir un prix normal et un prix promotionnel pour un produit.
  - Chaque promotion comporte une date/heure de début et une date/heure de fin ; elle n'est applicable que pendant cette période.
  - Le prix promotionnel doit être inférieur au prix normal.
  - En dehors de la période active, le prix normal s'applique.
  - Les règles monétaires Q260–Q262 restent applicables : MAD au premier release, deux décimales maximum, validation serveur et aucun arrondi/troncature silencieux.
  - Catalog expose le prix effectivement applicable et peut présenter le prix normal barré avec le prix promotionnel lorsqu'une promotion est active.
  - Sales revalide le prix applicable au moment pertinent du parcours d'achat et fige le prix et les informations de promotion dans le panier/commande selon les règles de gel déjà définies ; une modification ultérieure de la promotion ne réécrit pas une commande déjà confirmée.
- **Responsabilités :** Products possède les paramètres de prix et de promotion ; Catalog détermine/expose le prix affichable ; Sales valide et fige le prix commercial de la vente ; Finance consomme les montants figés.
- **Hors périmètre confirmé pour cette décision :** campagnes multi-produits, coupons, codes promo, cumul de promotions et promotions complexes.
- **À préciser ultérieurement :** fuseau horaire et bornes exactes d'activation, gestion des promotions qui se chevauchent, visibilité du prix barré hors période, promotions par variante et droits de validation/publication.

### Question 262 — Saisie d'un prix avec plus de deux décimales

- **Question :** Comment traiter un prix saisi par une coopérative avec plus de deux décimales ?
- **Réponse :** A — refuser la saisie et demander un prix avec deux décimales maximum.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Toute création ou modification de prix doit être refusée côté serveur si la valeur comporte plus de deux décimales significatives.
  - Le formulaire doit signaler clairement l'erreur et demander à la coopérative de corriger le prix.
  - Aucune conversion silencieuse par arrondi ou troncature n'est autorisée.
  - La validation s'applique aux prix de base et aux prix spécifiques des variantes, ainsi qu'à toute interface/API permettant de modifier ces prix.
- **Responsabilités :** Products applique la règle de validation à l'écriture ; Catalog n'expose que des prix conformes ; Sales consomme les prix valides et les fige dans le panier/commande.
- **Règle d'intégrité :** validation obligatoire côté serveur, même si le formulaire effectue aussi une validation côté client ; les valeurs invalides ne doivent jamais être enregistrées ni utilisées pour une vente.

### Question 261 — Précision des prix en MAD

- **Question :** Comment gérer les décimales des prix au premier release ?
- **Réponse :** A — deux décimales maximum.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Les prix sont exprimés en MAD avec une précision maximale de deux décimales (ex. 129,50 DH).
  - Les montants sont stockés et calculés avec une représentation décimale exacte adaptée aux montants monétaires ; les nombres flottants binaires ne doivent pas servir aux calculs financiers.
  - Les règles d'arrondi doivent être cohérentes entre Products, Catalog, Sales et Finance ; aucun arrondi silencieux ne doit modifier un montant déjà figé dans une commande.
  - L'affichage peut omettre les zéros décimaux non significatifs, mais la valeur monétaire reste limitée à deux décimales.
- **Responsabilités :** Products/Catalog valident et exposent les prix ; Sales calcule et fige les montants ; Finance consomme les montants figés selon la même précision.
- **Points restant à cadrer :** règle d'arrondi pour les calculs de taxes, remises, frais et répartitions ; règles fiscales détaillées hors de cette décision.
- **Règle d'intégrité :** validation serveur des montants et de leur précision ; pas de calcul monétaire en float ni de conversion implicite.

### Question 260 — Devise des prix Marketplace

- **Question :** Quelle devise utiliser pour les prix de la marketplace au premier release ?
- **Réponse :** B — MAD par défaut, avec possibilité d'ajouter d'autres devises ultérieurement.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Le dirham marocain (MAD) est la devise d'affichage et de référence du premier release.
  - Le modèle de prix et les calculs ne doivent pas être conçus de manière à rendre une autre devise impossible à ajouter ultérieurement.
  - L'ajout de devises supplémentaires est hors périmètre du premier release et nécessitera des règles explicites de conversion, d'arrondi, de tarification et de traitement financier avant activation.
  - Les prix figés dans le panier et la commande doivent conserver la devise utilisée au moment de leur création ; Finance consomme les montants et la devise transmis par Sales sans conversion implicite.
- **Responsabilités :** Products/Catalog exposent les prix en MAD au premier release ; Sales fige le montant et la devise dans le panier/commande ; Finance consomme ces données figées. L'extension multi-devises fera l'objet d'un cadrage dédié.
- **Règle d'intégrité :** aucun calcul ni affichage ne doit mélanger des montants de devises différentes ; aucune conversion automatique ou taux de change implicite n'est autorisé au premier release.

### Question 259 — Prix d'un produit sans variante

- **Question :** Comment afficher le prix d'un produit qui ne possède aucune variante ?
- **Réponse :** A — afficher directement son prix de base.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :** la carte et la fiche d'un produit sans variante affichent directement le prix de base, sans la mention « À partir de ». Sales revalide ce prix côté serveur et le fige dans le panier puis dans la commande.
- **Responsabilités :** Products porte le prix de base ; Catalog l'expose ; Sales revalide et fige le prix applicable.
- **Règle d'intégrité :** le prix utilisé pour l'achat est recalculé/relus côté serveur ; aucune valeur de prix fournie par le navigateur n'est fiable.

### Question 258 — Variantes indisponibles dans le prix minimum affiché

- **Question :** Les variantes indisponibles doivent-elles participer au prix minimum affiché sur la carte produit ?
- **Réponse :** A — exclure les variantes en rupture ou indisponibles.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Seules les variantes commercialisables et actuellement disponibles participent au calcul du prix minimum affiché avec « À partir de ».
  - Inventory fournit la disponibilité en tenant compte des réservations ; le stock physique interne n'est pas exposé au public.
  - Si aucune variante n'est disponible, la carte indique l'indisponibilité sans afficher un prix d'appel laissant croire qu'un achat est possible.
  - Sales revalide le prix et la disponibilité au panier/checkout ; une disponibilité affichée peut être réévaluée avant confirmation.
- **Responsabilités :** Inventory est la source de vérité de la disponibilité ; Catalog présente le prix minimum applicable ; Sales effectue la validation transactionnelle avant l'achat.
- **Règle d'intégrité :** ne jamais accepter une variante indisponible sur la seule base de l'état affiché côté client ; disponibilité et prix sont contrôlés côté serveur.

### Question 257 — Prix affiché sur une carte avec plusieurs variantes

- **Question :** Quel prix afficher sur la carte d'un produit dont les variantes ont des prix différents ?
- **Réponse :** A — afficher le prix applicable le plus bas avec la mention « À partir de ».
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :** la carte affiche « À partir de » suivi du plus bas prix applicable parmi les variantes commercialisables et disponibles. La fiche produit présente les prix applicables des variantes sélectionnables. Sales revalide le prix de la variante choisie et le fige dans le panier/commande.
- **Dépendances :** appliquer l'héritage du prix de base défini en Q256 et l'exclusion des variantes indisponibles définie en Q258.
- **Règle d'intégrité :** calcul du minimum et validation du prix côté serveur ; ne jamais se fier au prix transmis par le navigateur.

### Question 256 — Gestion du prix du produit et de ses variantes

- **Question :** Où doit être enregistré le prix d'un produit qui peut exister en plusieurs variantes ?
- **Réponse :** C — le produit possède un prix de base et chaque variante peut définir un prix différent.
- **Statut :** VALIDÉ — Marketplace V1.
- **Décision :**
  - Chaque produit possède un prix de base obligatoire pour être publié et vendu.
  - Chaque variante peut définir un prix de vente propre, facultatif.
  - Lorsqu'une variante ne définit pas de prix spécifique, le prix de base du produit s'applique.
  - Lorsqu'une variante définit un prix spécifique, celui-ci devient le prix applicable à cette variante.
  - Le prix réellement applicable doit être clairement affiché sur la fiche produit et transmis à Sales au moment de l'ajout au panier/checkout.
  - Sales doit figer le prix applicable et les informations de variante dans la ligne de commande ; une modification ultérieure du prix du catalogue ne modifie pas une commande déjà créée.
  - La devise, la précision/arrondi, les éventuels prix promotionnels, la gestion des prix historiques et les règles d'affichage lorsque plusieurs variantes ont des prix différents restent à définir avant les fonctionnalités correspondantes.
- **Responsabilités :** Products porte le prix de base et les données de variante ; Catalog expose le prix applicable à la marketplace ; Sales fige le prix au panier/commande selon les règles validées ; Finance consomme les montants figés sans recalculer les prix catalogue.
- **Règle de sécurité et d'intégrité :** valider côté serveur les prix et la variante sélectionnée ; ne jamais faire confiance au prix envoyé par le navigateur ; conserver la cohérence entre prix affiché, panier et ligne de commande.

### Question 255 — Absence de réponse du client à une proposition de modification

- **Question :** Si le client ne répond pas à une proposition de réduction de quantité ou de remplacement, quel traitement appliquer ?
- **Réponse :** A — après un délai défini, seule la ligne concernée est annulée automatiquement.
- **Statut :** VALIDÉ — premier release.
- **Décision :**
  - Chaque proposition comporte une échéance de réponse explicite, calculée selon un délai configurable.
  - Le client reçoit une notification de la proposition et une notification préalable à l'expiration selon les règles de Communication.
  - À l'expiration sans réponse, Sales clôt la proposition comme expirée et annule automatiquement uniquement la ligne concernée.
  - Les autres lignes de la même sous-commande et les autres sous-commandes de la commande multi-coopératives continuent leur traitement normal.
  - La ligne d'origine, la proposition, l'échéance, les notifications et l'événement d'expiration/annulation restent conservés dans l'historique.
  - Inventory ne réserve pas de stock pour la ligne annulée ; toute réservation existante liée à cette ligne est libérée une seule fois. Les autres lignes ne sont pas affectées.
  - Sales recalcule les montants et transmet à Finance les montants définitifs et leur ventilation ; Communication conserve la preuve des notifications.
- **Frontières de responsabilité :** Sales pilote l'échéance, l'expiration, l'annulation de ligne et les totaux ; Communication envoie les notifications ; Inventory libère le stock ciblé ; Finance enregistre les montants transmis.
- **Points à préciser avant codage :** durée par défaut, paramétrage éventuel par coopérative, moment et canal de la notification préalable, comportement si une réponse arrive au même instant que l'expiration, règles de frais de livraison et annulation de toutes les lignes.
- **Règle de sécurité et d'intégrité :** expiration exécutée côté serveur, transition atomique et idempotente ; vérifier que la proposition est toujours en attente et que la ligne n'est pas déjà annulée/traitée ; arbitrer les réponses concurrentes par verrouillage/contrôle de version ; journaliser l'échéance et l'acteur système.

### Question 254 — Conséquence du refus client d'une modification proposée

- **Question :** Si le client refuse une réduction de quantité ou un remplacement de produit proposé par la coopérative, quel traitement appliquer ?
- **Réponse :** A — seule la ligne concernée est annulée ; les autres produits de la sous-commande continuent.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Sales, Cooperatives, Inventory, Finance et Communication ; traitement partiel d'une sous-commande multi-lignes.
- **Décision :**
  - Le refus explicite du client clôt la proposition de modification concernée et entraîne l'annulation de la ligne de commande correspondante uniquement.
  - Les autres lignes de la même sous-commande restent actives et poursuivent leur traitement normal, sous réserve de leur disponibilité et de leur confirmation par la coopérative.
  - La sous-commande n'est pas annulée dans son ensemble du seul fait du refus portant sur une ligne.
  - Sales conserve la ligne initiale, la proposition, la réponse du client et l'événement d'annulation dans l'historique ; la ligne n'est ni supprimée ni écrasée.
  - Inventory ne réserve pas de stock pour la ligne annulée ; toute réservation existante liée à cette ligne doit être libérée sans double mouvement. Les autres lignes conservent leur traitement de stock indépendant.
  - Sales recalcule les totaux commerciaux de la sous-commande et les frais éventuels selon les règles de tarification applicables, puis transmet à Finance les montants définitifs et leur ventilation. Finance ne recalcule pas la commande commerciale de sa propre initiative.
  - La coopérative peut confirmer et préparer les autres lignes éligibles. Le client est informé de l'annulation partielle et de la poursuite du reste de sa commande.
- **Frontières de responsabilité :** Sales pilote l'état de la ligne, les totaux et l'historique ; Cooperatives traite les lignes restantes ; Inventory gère disponibilité et réservations ; Finance enregistre les conséquences financières transmises par Sales ; Communication notifie le client.
- **Points à préciser avant codage :** règles exactes de recalcul des frais de livraison après retrait d'une ligne, gestion du cas où toutes les lignes de la sous-commande sont annulées, et articulation avec une commande multi-coopératives dont les autres sous-commandes continuent.
- **Règle de sécurité et d'audit :** vérifier côté serveur que le répondant est le client propriétaire de la commande et que la proposition est encore en attente ; rendre le refus idempotent, journaliser la décision et empêcher toute annulation ou libération de stock en double.

### Question 253 — Modification d'une commande par la coopérative en cas d'indisponibilité

- **Question :** Lorsqu'une coopérative ne peut pas fournir la quantité demandée ou le produit commandé, quelles modifications peut-elle proposer et à quelles conditions la commande peut-elle être confirmée ?
- **Réponse :** C — la coopérative peut proposer une réduction de quantité ou un remplacement de produit, mais le client doit accepter toute modification.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Sales, Cooperatives, Inventory, Catalog/Products, Communication ; workflow de traitement des sous-commandes.
- **Décision :**
  - La coopérative ne peut pas modifier unilatéralement la quantité ou remplacer un produit dans une commande déjà passée.
  - Depuis le traitement de sa sous-commande, elle peut soumettre au client une proposition explicite de réduction de quantité et/ou de remplacement par un autre produit.
  - Toute proposition doit identifier les lignes concernées, les quantités initiales et proposées, le produit initial et le produit de remplacement le cas échéant, ainsi que les éventuelles différences de prix et leurs conséquences sur le total et les frais.
  - La modification ne devient effective qu'après acceptation explicite du client, enregistrée et historisée. Avant cette acceptation, la commande et ses montants initiaux restent inchangés.
  - La coopérative ne peut confirmer définitivement que les lignes et quantités acceptées, sous réserve des contrôles de disponibilité et de réservation du stock par Inventory.
  - Pour un panier multi-coopératives, la proposition et la réponse du client sont isolées au niveau de la sous-commande et des lignes concernées ; elles ne doivent pas modifier les sous-commandes des autres coopératives.
  - Le client doit être informé de la proposition et de son résultat via l'espace client et les moyens de communication prévus.
- **Frontières de responsabilité :** Sales conserve la commande, ses lignes, les propositions et la réponse du client ; Cooperatives initie la proposition dans son périmètre ; Catalog/Products fournit les références des produits proposés ; Inventory confirme la disponibilité et gère les réservations après confirmation ; Finance reçoit les montants commerciaux définitifs et figés transmis par Sales ; Communication délivre les notifications.
- **Impact :** empêcher toute modification unilatérale de l'achat, préserver le consentement du client et garantir la cohérence des montants, du stock et des sous-commandes.
- **Points à préciser avant codage complet :** délai de réponse du client, comportement en cas de refus ou d'absence de réponse, possibilité de confirmer les autres lignes de la même sous-commande, limites et critères d'équivalence d'un produit de remplacement, recalcul des frais de livraison et gestion des écarts de prix.
- **Règle de sécurité et d'audit :** contrôler côté serveur l'identité du client, l'appartenance de la proposition à sa commande, l'habilitation de la coopérative sur la sous-commande, la disponibilité du produit de remplacement et l'état de la commande ; journaliser proposition, notification, acceptation/refus et toute transition, avec protection contre les doubles soumissions.

### Question 251 — Statut de la version validée pendant le remplacement

- **Question :** Lorsqu'une nouvelle version est déposée pour remplacer un justificatif déjà validé, quel statut doit conserver l'ancienne version pendant que la nouvelle est « à vérifier » ?
- **Réponse :** A — l'ancienne version reste officiellement validée et utilisable jusqu'à la validation de la nouvelle.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; continuité de validité des justificatifs versionnés.
- **Conséquences :**
  - Le dépôt d'une nouvelle version ne révoque pas automatiquement la dernière version validée.
  - L'ancienne version demeure la preuve officiellement validée et utilisable tant que la nouvelle version est en attente ou refusée.
  - Lorsque la nouvelle version est validée, elle devient la version validée courante et l'ancienne passe au statut « remplacée », tout en restant conservée et consultable dans l'historique selon les habilitations.
  - Les statuts « dernière version déposée », « dernière version validée » et « version remplacée » doivent être distingués pour éviter toute ambiguïté dans le dossier financier.
  - Les changements de statut sont contrôlés côté serveur et journalisés.
- **Impact :** assurer la continuité de validité d'une preuve financière sans interrompre le dossier pendant une nouvelle vérification.
- **Points techniques à préciser :** représentation distincte de la version la plus récente et de la dernière version validée, comportement en cas de refus, remplacements concurrents, sélection de la preuve utilisée dans les opérations financières et idempotence.

### Question 250 — Remplacement d'un justificatif déjà validé

- **Question :** Après validation d'un justificatif, faut-il empêcher sa modification ou son remplacement ?
- **Réponse :** C — le remplacement est possible, mais chaque nouvelle version repasse au statut « à vérifier ».
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; cycle de vie, versionnement et validation des justificatifs financiers.
- **Conséquences :**
  - Un justificatif déjà validé reste conservé dans l'historique et n'est jamais écrasé ni supprimé lors d'un remplacement.
  - Le remplacement autorisé selon Q246 crée une nouvelle version liée à la version précédente.
  - Toute nouvelle version, y compris lorsqu'elle remplace une pièce précédemment validée, reçoit le statut initial « à vérifier » et doit repasser par le circuit de validation Q247-Q248.
  - Tant que la nouvelle version n'est pas validée, elle ne peut pas être considérée comme une preuve financière acceptée ; l'historique conserve le statut et la décision de chaque version.
  - Les opérations de dépôt, remplacement, validation et refus restent soumises aux contrôles serveur, aux périmètres de rôle et à la journalisation.
- **Impact :** garantir qu'aucune modification de preuve déjà acceptée ne contourne le contrôle financier et préserver une piste d'audit complète.
- **Points techniques à préciser :** définition de la version courante lorsqu'une version plus récente est en attente ou refusée, usage opérationnel de la dernière version validée, concurrence des remplacements, liens entre versions et idempotence.

### Question 249 — Nouvelle version après refus d'un justificatif

- **Question :** Lorsqu'un justificatif est refusé, que doit-il se passer ?
- **Réponse :** B — le `cooperative_admin` peut déposer une nouvelle version, qui reprend le statut « à vérifier ».
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; cycle de correction et de nouvelle soumission des justificatifs refusés.
- **Conséquences :**
  - Le justificatif refusé et sa version restent conservés dans l'historique ; ils ne sont ni écrasés ni supprimés.
  - Le `cooperative_admin` peut déposer une nouvelle version pour remplacer la pièce refusée dans le dossier courant de sa coopérative.
  - La nouvelle version reçoit un statut initial « à vérifier » et doit suivre le circuit de validation défini en Q247-Q248.
  - Le refus précédent, son motif, son auteur, sa date et le lien entre les versions restent consultables par les personnes habilitées.
  - Le dépôt de la nouvelle version est soumis aux habilitations, au contrôle serveur et à la journalisation définis en Q246.
- **Impact :** permettre la correction des pièces sans perdre la piste d'audit ni contourner la séparation entre dépôt et validation.
- **Points techniques à préciser :** lien explicite entre versions successives, règles de remplacement après validation, motifs et pièces de refus, notification du déposant, prévention des dépôts concurrents et idempotence.

### Question 248 — Autorité de validation des justificatifs

- **Question :** Qui peut valider ou refuser un justificatif déposé par une coopérative ?
- **Réponse :** D — `super_admin_root` et `super_admin` affecté ; le `cooperative_admin` ne peut pas valider ses propres justificatifs.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; validation ou refus des justificatifs transmis dans les dossiers financiers.
- **Conséquences :**
  - Le `super_admin_root` peut valider ou refuser les justificatifs de toutes les coopératives.
  - Le `super_admin` peut valider ou refuser uniquement les justificatifs des coopératives qui lui sont affectées.
  - Le `cooperative_admin` peut déposer et remplacer les justificatifs de sa propre coopérative conformément à Q246, mais ne peut pas valider ni refuser ses propres dépôts.
  - Les rôles `user` et `customer` ne disposent pas de cette autorisation par défaut.
  - La validation ou le refus est contrôlé côté serveur, limité au périmètre coopératif autorisé et journalisé avec l'acteur, la décision, la version du justificatif et l'horodatage.
  - Le dépôt conserve son statut « à vérifier » jusqu'à une décision prise par un acteur habilité, conformément à Q247.
- **Impact :** instaurer une séparation des responsabilités entre le dépôt d'une preuve par la coopérative et son contrôle par un responsable MAHLINE.
- **Points techniques à préciser :** transitions de statut, motif obligatoire de refus, éventuelle possibilité de soumettre une nouvelle version après refus, notifications, gestion des validations concurrentes et idempotence.

### Question 247 — Statut initial d'un justificatif transmis

- **Question :** Lorsqu'un `cooperative_admin` ajoute un justificatif, faut-il imposer une validation avant qu'il soit considéré comme officiellement accepté dans le dossier financier ?
- **Réponse :** D — le justificatif est immédiatement visible, mais son statut reste « à vérifier » jusqu'à validation.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; cycle de vie et vérification des justificatifs ajoutés ou remplacés sur les dossiers de créances MAHLINE.
- **Conséquences :**
  - Dès son dépôt, le justificatif est visible dans le dossier par les utilisateurs autorisés conformément aux habilitations Q242-Q246.
  - Son statut initial est « à vérifier » ; le dépôt ne vaut pas validation financière ni acceptation définitive de la preuve.
  - Le justificatif reste identifié comme non vérifié tant qu'une décision de validation n'a pas été enregistrée.
  - Les versions antérieures restent conservées conformément à Q245 ; le statut de vérification est suivi pour chaque version.
  - Le dépôt, les changements de statut, l'auteur et l'horodatage sont journalisés.
- **Impact :** permettre la transmission et la consultation immédiates des pièces sans confondre dépôt documentaire et contrôle financier.
- **Points techniques à préciser :** rôle habilité à valider/refuser, transitions de statut, motifs de refus, notification du déposant, traitement d'un remplacement pendant une vérification, version vérifiée de référence et audit des décisions.

### Question 246 — Habilitations pour ajouter ou remplacer un justificatif

- **Question :** Qui peut ajouter ou remplacer un justificatif associé à une créance MAHLINE ?
- **Réponse :** C — `super_admin_root`, `super_admin` affecté et `cooperative_admin` de la coopérative concernée.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; ajout et remplacement des justificatifs liés aux créances MAHLINE contestées ou annulées et à leurs écritures correctives.
- **Conséquences :**
  - `super_admin_root` peut ajouter ou remplacer les justificatifs de toutes les coopératives.
  - Le `super_admin` peut intervenir uniquement sur les coopératives qui lui sont affectées.
  - Le `cooperative_admin` peut ajouter ou remplacer les justificatifs uniquement pour les créances de sa propre coopérative.
  - Les rôles `user` et `customer` ne disposent pas de cette autorisation par défaut.
  - Chaque ajout ou remplacement est soumis à un contrôle d'accès côté serveur et journalisé avec l'acteur, la coopérative, la créance, la pièce et l'horodatage.
  - Tout remplacement crée une nouvelle version ; les versions précédentes sont conservées conformément à Q245 et ne peuvent pas être écrasées ou supprimées par cette opération.
  - Les restrictions de téléchargement individuel, de consultation et d'isolation entre coopératives définies en Q242-Q245 restent applicables.
- **Impact :** permettre à la coopérative de fournir ses preuves et aux responsables MAHLINE autorisés de compléter les dossiers, tout en assurant la traçabilité et la séparation des données.
- **Points techniques à préciser :** permissions dédiées à l'ajout/remplacement, validation des formats et tailles, stockage privé, empreinte d'intégrité, détection des doublons, versionnement atomique, journalisation et tests anti-IDOR ainsi que tests de révocation d'affectation.

### Question 245 — Historique des versions des justificatifs

- **Question :** Lorsqu'un justificatif est remplacé par une nouvelle version, que doit-il arriver à l'ancienne version ?
- **Réponse :** A — conserver toutes les versions, avec date, auteur et historique ; seule la dernière version est présentée comme actuelle.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; pièces justificatives liées aux créances contestées ou annulées, décisions et écritures correctives.
- **Conséquences :**
  - Le remplacement d'un justificatif crée une nouvelle version sans écraser ni supprimer les versions précédentes.
  - Chaque version conserve au minimum sa date d'ajout, son auteur et son lien avec la pièce ou la créance concernée.
  - L'historique permet d'identifier la version actuellement applicable et de consulter les versions antérieures par les utilisateurs autorisés.
  - Les droits de consultation et de téléchargement de Q242-Q243 s'appliquent à chaque version ; les téléchargements restent individuels conformément à Q244.
  - Les événements d'ajout et de remplacement sont journalisés ; les anciennes versions restent protégées contre toute modification ou suppression non autorisée.
- **Impact :** préserver les preuves et la traçabilité des décisions financières dans le temps, y compris lorsqu'une pièce est corrigée ou actualisée.
- **Points techniques à préciser :** stockage immuable ou versionné, empreinte d'intégrité, métadonnées obligatoires, limites de versions et de taille, gestion d'une version erronée, conservation légale, contrôle d'accès historique et tests d'intégrité.

### Question 244 — Téléchargement individuel ou groupé des justificatifs

- **Question :** Faut-il permettre le téléchargement d'un dossier complet regroupant tous les justificatifs d'une créance (par exemple au format ZIP) ?
- **Réponse :** B — téléchargement individuel uniquement ; pas de téléchargement groupé ZIP dans le premier release.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; téléchargement des pièces justificatives des créances contestées ou annulées, selon les habilitations Q242-Q243.
- **Conséquences :**
  - Les utilisateurs autorisés téléchargent chaque justificatif individuellement.
  - Aucun export groupé, archive ZIP ou téléchargement en lot de tous les justificatifs d'une créance n'est proposé dans le premier release.
  - Les contrôles d'accès, liens privés temporaires et journaux d'audit définis en Q242-Q243 s'appliquent à chaque téléchargement individuel.
  - Un besoin de téléchargement groupé pourra être réévalué après le premier release, sans élargir implicitement les permissions existantes.
- **Impact :** réduire le risque d'exposition accidentelle de pièces et limiter la complexité des téléchargements pour la première version.
- **Points techniques à préciser :** téléchargement individuel robuste, noms de fichiers non révélateurs, contrôle du type et de la taille, expiration des liens, journalisation et gestion des échecs/reprises.

### Question 243 — Téléchargement des justificatifs par le cooperative_admin

- **Question :** Le `cooperative_admin` peut-il télécharger les justificatifs des créances de sa coopérative, ou seulement les consulter à l'écran ?
- **Réponse :** A — il peut consulter et télécharger les justificatifs de sa coopérative.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; consultation et téléchargement des pièces justificatives des créances contestées ou annulées, dans le périmètre autorisé par Q242.
- **Conséquences :**
  - Le `cooperative_admin` peut consulter à l'écran et télécharger les justificatifs associés aux créances de sa propre coopérative.
  - Cette autorisation ne donne aucun accès aux dossiers, données ou pièces d'une autre coopérative ; les restrictions de Q242 restent applicables.
  - Chaque téléchargement doit être contrôlé côté serveur et journalisé avec l'acteur, la coopérative, la ressource et l'horodatage.
  - Les liens de téléchargement doivent être privés et temporaires ; une URL connue, copiée ou devinée ne doit jamais permettre de contourner les autorisations.
- **Impact :** permettre au responsable de la coopérative de récupérer les pièces nécessaires au suivi et à la justification de ses créances, avec une traçabilité complète.
- **Points techniques à préciser :** durée de validité des liens temporaires, formats autorisés, limites de taille, téléchargement multiple, révocation d'accès après changement d'affectation, journalisation et tests anti-IDOR.

### Question 242 — Accès aux détails et justificatifs des créances contestées ou annulées

- **Question :** Qui peut consulter les détails d'une créance MAHLINE contestée ou annulée et ses justificatifs ?
- **Réponse :** C — `super_admin_root`, le `super_admin` affecté et le `cooperative_admin` de la coopérative concernée.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et autorisations ; consultation des créances MAHLINE contestées ou annulées, de leurs écritures correctives, motifs, décisions et justificatifs (Q238-Q241).
- **Conséquences :**
  - `super_admin_root` peut consulter les dossiers de toutes les coopératives.
  - Un `super_admin` ne peut consulter que les dossiers des coopératives qui lui sont affectées.
  - Un `cooperative_admin` ne peut consulter que les dossiers de sa propre coopérative.
  - Les rôles `user` et `customer` n'ont pas accès à ces dossiers ni à leurs justificatifs par défaut.
  - Les contrôles d'accès s'appliquent côté serveur à chaque consultation, téléchargement et export ; connaître ou deviner un identifiant de créance ou une URL de justificatif ne confère aucun droit.
  - Les utilisateurs autorisés ne voient que le périmètre de leur coopérative ; aucune donnée financière ou pièce justificative d'une autre coopérative ne doit être exposée.
  - Toute consultation ou export de justificatif doit être journalisé avec l'acteur, la coopérative, la ressource et l'horodatage, conformément aux règles d'audit.
- **Impact :** permettre aux responsables de la coopérative concernée de comprendre et justifier les écritures qui la touchent, tout en préservant l'isolation des données entre coopératives.
- **Points techniques à préciser :** résolution des affectations `super_admin`-coopérative, gestion des changements d'affectation, permissions granulaires de lecture/téléchargement/export, stockage privé des justificatifs, URLs temporaires, journalisation, tests anti-IDOR et vérification des accès après révocation d'une affectation.

### Question 241 — Traçabilité financière de l'annulation d'une créance MAHLINE

- **Question :** Lorsqu'une créance MAHLINE est annulée après une contestation acceptée, faut-il conserver une trace de cette annulation dans les rapports financiers ?
- **Réponse :** A — conserver la créance initiale, l'écriture corrective, le motif, les justificatifs et l'auteur de la décision.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; traçabilité comptable, rapports financiers et audit des créances MAHLINE annulées après contestation (Q240).
- **Conséquences :**
  - Les rapports financiers doivent conserver une présentation traçable de la créance initiale et de son écriture corrective distincte, sans effacer ni masquer l'historique de l'opération.
  - La piste d'audit doit permettre de retrouver le motif de l'annulation, les justificatifs associés, la décision de contestation et l'identité de l'auteur de cette décision.
  - Le montant annulé doit être distingué des imputations déjà exécutées et des créances distinctes encore recouvrables au titre de Q233 ; aucune double annulation ni double récupération ne doit être produite.
  - Les données historiques doivent rester consultables dans les rapports et états financiers selon les droits d'accès applicables.
- **Impact :** garantir la transparence des états financiers, la justification des corrections et la capacité de contrôle a posteriori.
- **Points techniques à préciser :** format et fréquence des rapports, niveaux de détail et filtres, conservation des justificatifs, liens entre écritures et décision, habilitations de consultation, export PDF/CSV, horodatage, intégrité de l'audit et tests de rapprochement.

### Question 240 — Contestation acceptée et invalidation d'une créance MAHLINE

- **Question :** Si la contestation est acceptée et que la créance MAHLINE est déclarée invalide, que faut-il faire ?
- **Réponse :** A — annuler uniquement le solde restant dû par une écriture corrective distincte, conserver l'historique et ne pas effacer les imputations déjà exécutées.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; résolution défavorable à MAHLINE d'une contestation portant sur une créance reconnue au titre de montants déjà versés à tort à la coopérative (Q233, Q238-Q239).
- **Conséquences :**
  - Lorsque la contestation est formellement acceptée et que la créance est déclarée invalide, Finance annule uniquement le solde restant à recouvrer au moyen d'une écriture corrective distincte, liée à la créance d'origine et à la décision de contestation.
  - La créance initiale, son origine, son montant historique, ses statuts antérieurs, ses imputations et toutes les écritures associées restent conservés ; aucune suppression ni réécriture rétroactive n'est autorisée.
  - Les imputations déjà exécutées ne sont ni effacées ni recalculées. Les montants déjà versés à tort à la coopérative demeurent traités séparément comme une créance MAHLINE distincte à récupérer, conformément à Q233 ; ils ne doivent pas être annulés une seconde fois.
  - L'annulation du solde restant ne doit pas modifier les autres créances de la coopérative ni les crédits ordinaires.
- **Impact :** traiter une contestation acceptée sans conserver un solde devenu invalide, tout en garantissant l'intégrité comptable, la traçabilité et l'absence de double correction.
- **Points techniques à préciser :** autorité habilitée à accepter la contestation, preuve et horodatage de décision, calcul exact du solde annulable, écriture corrective et liens de traçabilité, traitement des imputations partielles, prévention des doubles traitements, concurrence, audit et idempotence.

### Question 239 — Résolution favorable d'une contestation de créance MAHLINE

- **Question :** Lorsqu'une contestation de créance MAHLINE est rejetée et que la créance est confirmée comme valide, que doit-il se passer ?
- **Réponse :** A — réactiver automatiquement la créance avec son rang FIFO initial.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; reprise du recouvrement d'une créance MAHLINE suspendue pendant une contestation (Q238).
- **Conséquences :**
  - Lorsque la contestation est formellement rejetée et que la créance est confirmée valide, son état redevient éligible au recouvrement sans créer une nouvelle créance.
  - La créance reprend son rang FIFO initial, déterminé par sa date de création immuable (Q236), puis par son ULID croissant en cas d'horodatage identique (Q237).
  - La période de suspension liée à la contestation ne réinitialise ni la date de création, ni l'ancienneté, ni le rang de la créance.
  - Les imputations déjà exécutées avant la suspension restent inchangées ; la reprise ne provoque aucune réimputation rétroactive des reversements passés.
  - Les autres créances continuent d'être traitées conformément à leurs règles de priorité ; la réactivation réintègre la créance dans le classement FIFO applicable aux reversements futurs.
- **Impact :** garantir qu'une contestation rejetée ne pénalise pas l'ancienneté de la créance et ne modifie pas l'historique des reversements déjà exécutés.
- **Points techniques à préciser :** autorité habilitée à rejeter la contestation, preuve et horodatage de décision, transitions d'état autorisées, traitement des imputations partielles, ordre de reprise face aux autres créances, audit, concurrence et idempotence.

### Question 238 — Contestation d'une créance MAHLINE

- **Question :** Une créance MAHLINE peut-elle être suspendue lorsqu'une coopérative la conteste ?
- **Réponse :** A — oui, suspendre uniquement la créance contestée ; les autres créances restent récupérables.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; recouvrement des créances MAHLINE sur les reversements dus aux coopératives.
- **Conséquences :**
  - Une contestation suspend uniquement la créance concernée ; elle ne bloque ni les autres créances non contestées de la même coopérative ni le fonctionnement normal des reversements.
  - La créance suspendue est temporairement exclue du recouvrement et de l'imputation sur les reversements tant que la contestation n'est pas résolue.
  - La suspension ne vaut ni annulation, ni abandon, ni suppression de la créance ; son montant restant dû, son origine, ses imputations antérieures et sa date FIFO initiale sont conservés.
  - Les autres créances non contestées continuent d'être recouvrées selon la priorité FIFO définie par Q234 à Q237 ; une créance suspendue ne doit pas bloquer les créances suivantes qui sont éligibles au recouvrement.
  - La règle concerne la créance MAHLINE reconnue au titre de montants déjà versés à tort à la coopérative (Q233), et non les crédits ordinaires de coopérative traités séparément par Q230 à Q232.
- **Impact :** permettre le traitement contradictoire d'une créance sans immobiliser les autres sommes dues à MAHLINE ni effacer l'historique financier.
- **Points techniques à préciser :** acteurs autorisés à ouvrir/clore une contestation, états et motifs, justificatifs et horodatage, décision et preuve de résolution, réactivation ou annulation après décision, conservation du rang FIFO, traitement des imputations partielles, audit, concurrence et idempotence.

### Question 237 — Départage des créances créées au même instant

- **Question :** Si deux créances ont exactement la même date et heure de création, comment les départager ?
- **Réponse :** A — par identifiant ULID croissant.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; départage déterministe des créances MAHLINE de même date de création pour l'application du FIFO.
- **Conséquences :**
  - Le premier critère de classement reste la date de création initiale immuable, conformément à Q236.
  - Lorsque plusieurs créances d'une même coopérative portent exactement la même date et heure de création, elles sont départagées par leur identifiant ULID, dans l'ordre lexicographique croissant.
  - Ce second critère fournit un ordre total, stable et reproductible, indépendant de l'ordre d'affichage, de lecture ou de traitement des créances.
  - L'identifiant utilisé pour le départage est celui de la créance elle-même ; il ne doit pas être remplacé par un identifiant de relation, de reversement ou d'écriture d'imputation.
- **Impact :** rendre le classement FIFO déterministe même lorsque plusieurs créances sont enregistrées avec un horodatage identique.
- **Points techniques à préciser :** format/collation de comparaison ULID (comparaison normalisée et insensible aux différences de casse), garantie d'unicité, cohérence des ULID historiques, transactions concurrentes et tests de classement.

### Question 236 — Date de référence de l'ancienneté d'une créance MAHLINE

- **Question :** Comment déterminer quelle créance est la plus ancienne lorsque plusieurs créances existent ?
- **Réponse :** A — la date de création initiale de la créance.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; détermination du rang FIFO entre créances MAHLINE dues par une même coopérative.
- **Conséquences :**
  - L'ancienneté d'une créance est déterminée par sa date de création initiale, enregistrée lors de sa création.
  - Cette date de référence est immuable : une validation ultérieure, un changement de statut, une contestation, une suspension ou un report de solde ne la remplace pas et ne modifie pas le rang FIFO.
  - Le recouvrement continue conformément à Q235 : la créance ayant la date de création initiale la plus ancienne est apurée avant la suivante.
  - En cas de dates de création identiques, le système devra appliquer un critère technique de départage stable, déterministe et indépendant de l'ordre d'affichage ou de traitement.
- **Impact :** garantir que le rang FIFO des créances repose sur une référence chronologique objective et ne peut pas être manipulé par des changements ultérieurs d'état.
- **Points techniques à préciser :** précision et fuseau de l'horodatage, départage des créations simultanées (identifiant stable ou séquence monotone), transactions concurrentes, migration des créances historiques et idempotence.

### Question 235 — Priorité entre plusieurs créances MAHLINE

- **Question :** Si plusieurs créances de MAHLINE existent simultanément pour une même coopérative, laquelle doit être récupérée en premier ?
- **Réponse :** A — la plus ancienne créance en premier (FIFO).
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; ordre de recouvrement des créances de MAHLINE sur une coopérative et imputation des reversements.
- **Conséquences :**
  - Lorsque plusieurs créances MAHLINE sont ouvertes pour une même coopérative, Finance récupère la plus ancienne avant toute créance plus récente.
  - La créance en tête de file doit être apurée avant de commencer le recouvrement de la suivante.
  - Si un reversement ne suffit pas à solder la créance la plus ancienne, son solde est reporté et conserve sa priorité sur les reversements suivants ; les créances plus récentes restent en attente.
  - Cette règle FIFO s'applique à l'intérieur de la catégorie des créances MAHLINE ; conformément à Q234, les créances restent prioritaires sur les crédits ordinaires coopératifs, lesquels ne sont imputés qu'après apurement des créances, selon leur propre FIFO.
  - Chaque créance conserve son identité, son origine, son solde et son historique d'imputations ; aucun recouvrement ne peut être effectué deux fois.
- **Impact :** définir un ordre de recouvrement déterministe, traçable et stable entre plusieurs créances d'une même coopérative.
- **Points techniques à préciser :** horodatage de référence et départage déterministe des créances créées simultanément, éligibilité des reversements, plafonds, créance contestée/suspendue, coopérative inactive ou fermée, concurrence, rapprochement et idempotence.

### Question 234 — Priorité de récupération d'une créance sur les crédits ordinaires

- **Question :** Lorsqu'une coopérative possède à la fois des crédits ordinaires à recevoir et une créance envers MAHLINE issue d'un crédit invalidé, quelle priorité appliquer sur ses prochains reversements ?
- **Réponse :** A — récupérer d'abord la créance de MAHLINE, puis imputer les crédits ordinaires selon FIFO.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; ordre d'imputation des reversements, créances coopératives et crédits ordinaires.
- **Conséquences :**
  - À chaque reversement éligible, Finance affecte d'abord le montant disponible au recouvrement des créances de MAHLINE envers la coopérative.
  - Une fois les créances prioritaires réglées, le solde du reversement est affecté aux crédits ordinaires coopératives selon FIFO, conformément à Q229.
  - Si le reversement ne suffit pas à solder la créance prioritaire, le reliquat de créance est reporté sur les reversements suivants ; les crédits ordinaires restent en attente et conservent leur ancienneté FIFO.
  - Les créances et les crédits ordinaires restent des objets financiers distincts, avec soldes, historiques, liens d'origine et écritures d'imputation propres.
  - Aucun montant ne peut être récupéré ou imputé deux fois ; chaque opération doit être atomique, traçable et idempotente.
- **Impact :** établir une priorité déterministe de recouvrement pour MAHLINE sans perdre l'ordre FIFO des crédits ordinaires.
- **Points techniques à préciser :** ordre entre plusieurs créances, définition des reversements éligibles, montant maximum récupérable par échéance, reversement nul/annulé, coopérative inactive ou fermée, contestation de la créance, concurrence, rapprochement et idempotence.

### Question 233 — Créance sur les montants déjà versés après invalidation d'un crédit

- **Question :** Lorsqu'un crédit contesté a déjà été partiellement ou totalement imputé sur des reversements exécutés, comment traiter les montants déjà versés à la coopérative si la contestation est acceptée ?
- **Réponse :** A — créer une créance distincte sur la coopérative, récupérable par déduction sur ses prochains reversements.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; contestations de crédits, créances coopératives et reversements.
- **Conséquences :**
  - Les montants du crédit invalide déjà imputés et effectivement versés à la coopérative sont reconnus comme une créance distincte de MAHLINE sur cette coopérative.
  - La créance est liée au crédit d'origine, à la décision de contestation et aux reversements sur lesquels les montants avaient été imputés.
  - Finance récupère automatiquement cette créance par déduction sur les prochains reversements dus à la coopérative, selon les règles d'imputation à préciser.
  - Le solde non encore imputé du crédit initial est annulé par l'écriture corrective distincte définie en Q232 ; les montants déjà versés ne sont pas effacés ni confondus avec cette annulation.
  - La créance et chaque récupération doivent disposer d'un historique, d'un solde restant et d'une traçabilité comptable ; aucune double récupération n'est autorisée.
- **Impact :** séparer l'annulation du solde de crédit non utilisé de la récupération des montants déjà versés, tout en conservant une piste d'audit complète.
- **Points techniques à préciser :** priorité de cette créance par rapport aux crédits FIFO, taux/plafond de récupération par reversement, reversements insuffisants ou nuls, coopérative inactive/fermée, contestation ultérieure, arrondis, concurrence, rapprochement et idempotence.

### Question 232 — Crédit reconnu invalide après contestation acceptée

- **Question :** Si une contestation est acceptée et que le crédit est reconnu comme invalide, quelle action doit prendre MAHLINE ?
- **Réponse :** A — annuler le solde restant du crédit par une écriture corrective distincte, en conservant tout l'historique.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; résolution des contestations et correction des crédits coopératives.
- **Conséquences :**
  - Lorsque la contestation est acceptée, Finance annule le solde encore disponible du crédit reconnu invalide au moyen d'une écriture corrective distincte.
  - Le crédit initial et toutes ses imputations antérieures restent conservés et consultables ; aucune écriture n'est supprimée ou réécrite.
  - L'écriture corrective est liée au crédit initial et à la décision de contestation, avec motif, auteur, date et montant annulé.
  - Seul le solde restant non encore imputé peut être annulé par cette opération ; les montants déjà imputés sur des reversements exécutés nécessitent un traitement correctif distinct, qui reste à définir et ne doit pas être effacé rétroactivement.
  - Le crédit annulé est exclu définitivement des prochaines imputations FIFO ; l'annulation doit empêcher toute réactivation ou double correction non autorisée.
- **Impact :** l'invalidation d'un crédit est traitée par une écriture financière traçable, sans altérer l'historique comptable.
- **Points techniques à préciser :** traitement des montants déjà imputés, acteurs et preuves de décision, statut final du crédit, autorisation d'annulation, arrondis, concurrence, audit et idempotence.

### Question 231 — Réactivation d'un crédit après contestation rejetée

- **Question :** Si une contestation est rejetée et que le crédit est reconnu comme valide, comment MAHLINE doit-elle le réactiver ?
- **Réponse :** A — réactiver le crédit avec son rang FIFO d'origine, comme si la suspension n'avait pas modifié sa priorité.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; résolution des contestations et ordre FIFO d'imputation des crédits coopératives.
- **Conséquences :**
  - Lorsqu'une contestation est rejetée et que le crédit est confirmé valide, Finance réactive le crédit suspendu.
  - Le crédit retrouve son rang FIFO d'origine, déterminé par sa date/heure de création et les règles de départage qui seront définies techniquement ; la période de suspension ne crée pas une nouvelle date d'ancienneté.
  - Les autres crédits continuent d'être imputés pendant la suspension conformément à Q230 ; la réactivation ne provoque pas de réimputation rétroactive des reversements déjà exécutés.
  - Le solde restant du crédit reprend sa place dans la file FIFO et pourra être imputé sur les prochains reversements, selon les règles générales de plafonnement et de traçabilité.
  - La résolution de la contestation, la décision de rejet et la réactivation doivent être liées et historisées ; aucune écriture d'origine n'est effacée ou réécrite.
- **Impact :** une contestation finalement rejetée ne pénalise pas le rang d'ancienneté du crédit valide.
- **Points techniques à préciser :** preuve et autorité de résolution, horodatage de décision, traitement d'un crédit partiellement imputé avant suspension, verrouillage/concurrence, journalisation et idempotence.

### Question 230 — Contestation d'un crédit coopérative

- **Question :** Si un crédit coopérative est créé puis fait l'objet d'une contestation, que doit faire MAHLINE ?
- **Réponse :** A — suspendre uniquement le crédit contesté ; les autres crédits continuent selon FIFO.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; contestation, suspension et imputation des crédits coopératives sur les reversements.
- **Conséquences :**
  - Seul le crédit faisant l'objet d'une contestation est suspendu et exclu temporairement de toute imputation sur les reversements.
  - Les autres crédits non contestés restent éligibles à l'imputation selon l'ordre FIFO défini en Q229 ; la contestation d'un crédit ne bloque pas les crédits légitimes de la coopérative.
  - Le crédit contesté conserve son solde, son historique et ses liens avec les écritures d'origine ; sa suspension ne vaut ni annulation ni suppression.
  - La décision de résolution de la contestation devra déterminer si le crédit est réactivé pour reprendre son rang FIFO d'origine ou s'il est annulé/corrigé par une écriture distincte ; cette règle de résolution reste à cadrer.
- **Impact :** une contestation est isolée au niveau du crédit concerné sans interrompre l'imputation des autres crédits valides de la coopérative.
- **Points techniques à préciser :** acteurs habilités à ouvrir/résoudre une contestation, états et motifs, preuve et délai de traitement, conservation du rang FIFO après réactivation, journalisation, concurrence et idempotence.

### Question 229 — Ordre d'imputation de plusieurs crédits coopérative

- **Question :** Si une coopérative possède plusieurs crédits en attente, dans quel ordre MAHLINE doit-elle les imputer sur ses reversements ?
- **Réponse :** A — FIFO : imputer d'abord le crédit le plus ancien.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; ordre d'imputation des crédits coopératives reportés sur les reversements successifs.
- **Conséquences :**
  - Finance impute les crédits en attente selon leur ordre chronologique de création, du plus ancien au plus récent.
  - Le crédit le plus ancien est imputé en priorité jusqu'à son épuisement avant de passer au crédit suivant.
  - Si le reversement disponible ne permet pas d'épuiser le crédit le plus ancien, son solde est conservé et reporté ; les crédits plus récents restent en attente.
  - La règle FIFO s'applique de manière identique à chaque reversement et ne dépend pas de l'ordre d'affichage ou de traitement technique des crédits.
  - Chaque imputation reste une écriture traçable liée au crédit d'origine et au reversement concerné ; le cumul imputé ne peut dépasser ni le crédit disponible ni le montant du reversement.
- **Impact :** les crédits coopératives sont apurés dans un ordre stable et prévisible, sans favoriser les crédits récents au détriment des plus anciens.
- **Points techniques à préciser :** définition de l'horodatage de référence, départage déterministe des crédits créés au même instant, verrouillage/concurrence, traitement des crédits annulés ou contestés et idempotence.

### Question 228 — Imputation d'un crédit coopérative supérieur au prochain reversement

- **Question :** Lorsqu'un crédit coopérative est supérieur au montant du prochain reversement, comment traiter le solde restant ?
- **Réponse :** A — imputer automatiquement le crédit sur les reversements successifs jusqu'à son épuisement.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; gestion des crédits coopératives issus des corrections de commission après exécution d'un reversement.
- **Conséquences :**
  - À chaque reversement suivant, Finance impute automatiquement le montant disponible dans la limite du montant de ce reversement.
  - Si le crédit est supérieur au reversement, le reversement est réduit à zéro et le solde créditeur restant est reporté automatiquement sur les reversements ultérieurs.
  - Si le reversement est supérieur ou égal au crédit restant, le crédit est intégralement imputé et le reliquat du reversement est versé normalement à la coopérative.
  - Chaque imputation conserve le lien avec le crédit d'origine, la correction de commission, le remboursement, la commande et le reversement concerné.
  - Le solde du crédit doit être recalculé à partir des imputations enregistrées ; aucune imputation ne peut dépasser le solde disponible ni être comptabilisée deux fois.
  - Le crédit reste visible et traçable jusqu'à son épuisement complet ; aucune demande de validation manuelle n'est nécessaire pour les reports successifs.
- **Impact :** le crédit coopérative est apuré automatiquement sur plusieurs reversements si nécessaire, sans perte de solde ni modification des reversements déjà exécutés.
- **Points techniques à préciser :** modèle comptable du crédit et de ses imputations, ordre de priorité si plusieurs crédits coexistent, traitement des reversements nuls/annulés, concurrence et verrouillage, rapprochement, arrondis et idempotence.

### Question 227 — Régularisation d'une commission corrigée après reversement à la coopérative

- **Question :** Lorsqu'une correction de commission est enregistrée après un remboursement, mais que la coopérative a déjà reçu son reversement, comment MAHLINE doit-elle régulariser le montant dû à la coopérative ?
- **Réponse :** C — si le reversement n'a pas encore été effectué, ajuster le montant à verser ; s'il a déjà été effectué, créer un crédit au bénéfice de la coopérative, imputable sur son prochain reversement.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; régularisation des reversements coopératives après correction de commission, en articulation avec Sales et le traitement des remboursements.
- **Conséquences :**
  - Si le reversement concerné n'est pas encore exécuté, Finance intègre la correction de commission au calcul du montant net restant à verser à la coopérative.
  - Si le reversement a déjà été exécuté, Finance enregistre un crédit distinct au bénéfice de la coopérative, sans modifier ni effacer le reversement initial.
  - Le crédit est conservé dans le compte financier de la coopérative et imputé sur son prochain reversement ; il reste traçable jusqu'à son imputation complète.
  - Chaque régularisation est liée à la correction de commission, au remboursement confirmé, à la commande et au reversement concerné.
  - Le calcul du solde doit tenir compte des crédits déjà créés ou imputés afin d'éviter toute double régularisation.
- **Impact :** les coopératives conservent un historique financier exact ; une correction postérieure au reversement devient un crédit reportable plutôt qu'une demande de remboursement immédiat à la coopérative.
- **Points techniques à préciser :** représentation du crédit coopérative, imputation partielle ou totale sur les prochains reversements, gestion d'un solde créditeur supérieur au prochain reversement, ordre des opérations concurrentes, rapprochement et idempotence.
 
### Question 226 — Moment d'enregistrement de la correction de commission après confirmation du remboursement

- **Question :** Lorsqu'un remboursement est confirmé par Sales, à quel moment Finance doit-elle enregistrer la correction de commission ?
- **Réponse :** A — immédiatement après la confirmation définitive du remboursement par Sales.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; déclenchement de la correction de commission après confirmation du remboursement, en articulation avec Sales.
- **Conséquences :**
  - Dès que Sales transmet la confirmation définitive d'un remboursement, Finance déclenche sans délai le recalcul cumulatif de la commission à annuler conformément aux Questions 223 à 225.
  - Finance comptabilise uniquement la différence entre le montant cumulatif cible de correction et les corrections déjà enregistrées, en conservant une écriture distincte liée à la commission d'origine et au remboursement déclencheur.
  - L'enregistrement de la correction n'attend ni le rapprochement financier périodique, ni la réception effective des fonds par le client.
  - La confirmation définitive émise par Sales constitue le déclencheur métier ; une demande de remboursement encore en attente ou non confirmée ne déclenche aucune correction.
  - Le traitement reste soumis aux règles de plafonnement, de non-double-comptage, de traçabilité et d'idempotence définies dans les Questions 223 à 225.
- **Impact :** les commissions sont corrigées au moment où le remboursement devient définitif dans Sales ; Finance n'accumule pas de corrections en attente du rapprochement périodique ou du retour des fonds.
- **Points techniques à préciser :** contrat d'événement ou de transmission Sales → Finance, garantie de livraison/rejeu, traitement des échecs et idempotence du déclenchement immédiat ; les règles de précision/arrondi au centime et de concurrence restent également à définir.
 
### Question 225 — Remboursements partiels successifs et cumul des corrections de commission

- **Question :** Lorsqu'une même commande fait l'objet de plusieurs remboursements partiels successifs, comment calculer les corrections de commission ?
- **Réponse :** B — recalculer la commission à annuler sur le montant net cumulé de tous les remboursements, puis comptabiliser uniquement la différence avec les corrections déjà enregistrées.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; corrections de commissions liées aux remboursements successifs, avec références et montants confirmés transmis par Sales.
- **Conséquences :**
  - Pour chaque remboursement confirmé, recalculer le montant total de commission qui doit être annulé à partir du montant net cumulé des produits remboursés sur la commande, après remises et hors frais de livraison.
  - Déduire du montant cible les corrections de commission déjà comptabilisées pour cette commission d'origine ; seule la différence restante constitue la nouvelle correction.
  - Enregistrer chaque correction dans une écriture distincte, liée à la commission initiale et au remboursement qui la déclenche ; conserver les écritures antérieures sans modification ni suppression.
  - Plafonner le cumul des corrections à la commission initialement comptabilisée : aucune correction ne peut conduire à annuler davantage que cette commission.
  - Garantir qu'un même remboursement confirmé ne soit pas pris en compte deux fois dans le cumul.
  - Utiliser le taux figé pour la commande d'origine et maintenir l'exclusion des frais de livraison de l'assiette.
  - Sales transmet à Finance les références et montants nets confirmés de chaque remboursement ; Finance tient le cumul de référence et comptabilise uniquement l'écart à corriger.
- **Impact :** les remboursements successifs produisent une correction financière cumulative cohérente, sans double annulation ni dépassement de la commission initiale, tout en préservant une piste d'audit par remboursement.
- **Point technique à préciser :** précision décimale et règle d'arrondi au centime lors du recalcul cumulatif, ordre transactionnel et idempotence du traitement concurrent de remboursements.

### Question 224 — Assiette de calcul de la commission à annuler lors d'un remboursement partiel

- **Question :** Pour un remboursement partiel, quel montant doit servir à calculer la commission à annuler ?
- **Réponse :** A — le montant net des produits effectivement remboursés, après remise et hors frais de livraison.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; correction des commissions après remboursement partiel, en articulation avec les montants et références de remboursement confirmés par Sales.
- **Conséquences :**
  - Calculer la commission à corriger sur le montant net réellement remboursé pour les produits, après prise en compte des remises effectivement appliquées.
  - Exclure les frais de livraison de l'assiette, conformément aux règles de commission déjà validées.
  - Utiliser le taux de commission figé pour la commande d'origine ; ne pas appliquer un taux contractuel ultérieur.
  - Conserver une écriture de correction distincte, rattachée à la commission initiale et au remboursement concerné ; ne pas modifier ni supprimer l'écriture initiale.
  - Sales transmet à Finance le détail des produits, montants nets remboursés, remises et référence du remboursement ; Finance calcule et comptabilise la correction.
- **Impact :** les corrections de commission suivent la valeur nette réellement remboursée des produits et restent cohérentes avec l'assiette de commission initiale définie par Q218.
- **Point technique à préciser :** calcul au centime, remboursements successifs et plafonnement du cumul des corrections à la commission initialement comptabilisée, sans double correction d'un même montant remboursé.

### Question 223 — Correction de la commission après remboursement

- **Question :** Lorsqu'une commande est totalement ou partiellement remboursée, comment traiter la commission MAHLINE déjà calculée sur cette commande ?
- **Réponse :** A — annuler la commission correspondant au montant remboursé ; pour un remboursement partiel, seule la part proportionnelle est annulée.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; remboursements, avoirs et corrections de commissions, avec données de remboursement issues du flux commercial Sales.
- **Conséquences :**
  - En cas de remboursement total, annuler la totalité de la commission associée au montant des produits remboursés.
  - En cas de remboursement partiel, corriger la commission au prorata du montant net des produits remboursés, selon le taux de commission figé pour la commande.
  - Enregistrer une écriture de correction/contrepassation liée à la commission d'origine et au remboursement correspondant.
  - Conserver intégralement l'historique de la commission initiale et de chaque correction ; ne pas supprimer ni réécrire les écritures historiques.
  - Les frais de livraison restent hors assiette de commission, conformément aux décisions précédentes.
  - Sales transmet à Finance les références et montants de remboursement confirmés ; Finance reste propriétaire de la correction financière et de la commission.
- **Impact :** les commissions dues à MAHLINE reflètent les remboursements effectifs, tout en préservant une piste d'audit complète et l'immutabilité des écritures initiales.
- **Point technique à préciser :** méthode exacte de calcul au centime pour les remboursements partiels, gestion des remboursements successifs et plafonnement du cumul des corrections à la commission initialement comptabilisée.

### Question 222 — Éligibilité des produits à la répartition d'une remise globale

- **Question :** Les produits exclus d'une promotion doivent-ils participer à la répartition de la remise globale entre coopératives ?
- **Réponse :** A — non ; seuls les produits éligibles à la promotion participent au calcul du prorata et à la répartition de la remise.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et Sales ; éligibilité promotionnelle et ventilation des remises globales sur panier multi-coopératives.
- **Conséquences :**
  - Exclure de l'assiette de répartition tous les produits non éligibles à la promotion concernée.
  - Calculer les parts proportionnelles des coopératives uniquement à partir des montants des produits éligibles avant remise.
  - Appliquer ensuite la méthode du plus fort reste et le départage stable défini par Q220 et Q221, sur cette seule assiette éligible.
  - Les produits exclus ne reçoivent aucune part de la remise globale et ne doivent pas réduire la part attribuable aux produits éligibles.
  - Si aucun produit du panier n'est éligible, aucune remise promotionnelle globale n'est répartie.
  - Sales doit transmettre à Finance l'éligibilité et la ventilation figées par ligne/sous-commande ; Finance conserve les montants éligibles, la remise attribuée et les bases de calcul.
- **Impact :** la base de prorata des remises globales est limitée aux produits éligibles ; les produits exclus sont neutralisés dans la répartition.
- **Point technique à préciser :** format du contrat Sales → Finance pour transmettre l'éligibilité par ligne et traitement des promotions combinées lors de la conception technique.

### Question 221 — Départage des restes fractionnaires égaux

- **Question :** Lorsque plusieurs coopératives ont exactement le même reste fractionnaire lors de la répartition d'une remise au plus fort reste, quel critère utiliser pour les départager ?
- **Réponse :** A — utiliser l'identifiant stable de la coopérative, par ordre croissant.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et Sales ; déterminisme de la ventilation des remises globales multi-coopératives.
- **Conséquences :**
  - Appliquer le critère uniquement lorsque plusieurs coopératives ont un reste fractionnaire égal au rang de distribution du centime résiduel.
  - Attribuer la priorité à l'identifiant stable de coopérative dont la valeur est la plus petite selon l'ordre de comparaison défini techniquement.
  - Le critère doit être stable, reproductible et indépendant de l'ordre d'affichage, de traitement ou de création des sous-commandes.
  - Conserver la garantie que la somme des remises attribuées est exactement égale à la remise globale.
- **Impact :** le départage des égalités dans la méthode du plus fort reste est désormais fonctionnellement défini.

### Question 220 — Affectation des centimes résiduels d'une remise répartie
- **Question :** Après la répartition proportionnelle d'une remise globale entre plusieurs coopératives, comment affecter les centimes résiduels dus aux arrondis ?
- **Réponse :** C — appliquer une méthode déterministe au plus fort reste.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et Sales ; calcul et ventilation des remises globales par sous-commande coopérative.
- **Conséquences :**
  - Calculer d'abord les parts théoriques proportionnelles de la remise globale, puis leurs montants arrondis au centime inférieur.
  - Distribuer les centimes restant à affecter aux coopératives présentant les plus grands restes fractionnaires, dans l'ordre décroissant.
  - En cas d'égalité des restes, départager les coopératives par leur identifiant stable, dans l'ordre croissant, conformément à Q221.
  - Garantir que la somme des remises attribuées aux sous-commandes est exactement égale à la remise globale accordée ; aucun centime ne doit être créé ou perdu.
  - Sales transmet à Finance la ventilation finale figée par sous-commande ; Finance conserve cette ventilation pour le calcul de l'assiette nette et des commissions.
- **Impact :** le premier release dispose d'une règle reproductible de répartition au centime près pour les remises globales multi-coopératives.
- **Point technique à préciser :** les règles de comparaison des identifiants et le calcul en unités monétaires entières seront fixés pendant la conception technique.

### Question 219 — Répartition d'une remise globale entre coopératives

- **Question :** Lorsqu'un panier multi-coopératives bénéficie d'une remise globale, comment répartir cette remise entre les coopératives ?
- **Réponse :** A — répartir la remise proportionnellement au montant des produits de chaque coopérative avant remise.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et Sales ; allocation des remises de panier et calcul des montants nets par sous-commande coopérative.
- **Conséquences :**
  - La part de remise attribuée à chaque coopérative est calculée au prorata du montant de ses seuls produits éligibles avant remise par rapport au montant total des produits éligibles du panier ; les produits exclus sont ignorés.
  - La somme des parts attribuées doit être égale à la remise globale effectivement accordée ; aucun montant de remise ne doit être créé, perdu ou comptabilisé deux fois.
  - La part de remise affectée à chaque coopérative est déduite de son montant de produits avant calcul de l'assiette nette de commission, conformément à Q218.
  - Sales doit transmettre à Finance la ventilation figée de la remise par sous-commande coopérative ; Finance reste propriétaire du calcul et de la traçabilité financière de la commission.
  - La règle d'arrondi au centime, l'affectation du reliquat d'arrondi, les produits éventuellement exclus d'une promotion et le traitement des annulations ou remboursements partiels restent à préciser.
- **Impact :** le premier release doit permettre de ventiler une remise globale multi-coopératives au prorata des montants produits avant remise, puis de calculer séparément le montant net et la commission de chaque coopérative.

### Question 218 — Assiette de commission après remise

- **Question :** Lorsqu'une commande bénéficie d'une remise, sur quel montant de produits MAHLINE doit-elle calculer sa commission ?
- **Réponse :** B — la commission est calculée sur le montant des produits après déduction des remises, hors frais de livraison.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; calcul de la commission à partir des montants définitifs de la commande.
- **Conséquences :**
  - L'assiette de commission correspond au montant net des produits après déduction des remises applicables à ces produits.
  - Les frais de livraison restent exclus de l'assiette, conformément à Q215.
  - Le calcul doit utiliser les montants de remise effectivement appliqués et figés sur la commande ; il ne doit pas être recalculé à partir de prix catalogue susceptibles d'évoluer.
  - Les éléments nécessaires à l'explication du calcul (montant produits avant remise, remises déduites, montant net retenu, taux appliqué et commission calculée) doivent être conservés dans les données financières de la commande.
  - La répartition des remises globales entre plusieurs coopératives ou lignes de commande, ainsi que les effets des annulations et remboursements partiels, restent à préciser.
- **Impact :** Finance V1 doit calculer la commission sur la valeur nette des produits effectivement facturée, après remises et hors livraison, en s'appuyant sur les montants figés transmis par Sales.

### Question 217 — Date d'effet d'un nouveau taux de commission

- **Question :** Lorsqu'un taux de commission contractuel est modifié, à partir de quand le nouveau taux doit-il s'appliquer aux commandes ?
- **Réponse :** B — le nouveau taux s'applique à partir d'une date d'effet définie par `super_admin_root` ; les commandes déjà créées conservent le taux applicable au moment de leur création.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; versionnement et application des conditions de commission.
- **Conséquences :**
  - Chaque modification du taux comporte une date d'effet explicite définie par `super_admin_root`.
  - Une commande créée avant cette date conserve le taux et les paramètres de commission figés lors de sa création, même si son paiement, son encaissement COD ou son reversement intervient après la date d'effet.
  - Les commandes créées à partir de la date d'effet utilisent le nouveau taux applicable à leur coopérative.
  - Le système doit pouvoir retrouver le taux applicable à une commande à partir de ses données financières figées et de l'historique des versions contractuelles.
  - La modification ne réécrit pas rétroactivement les commissions des commandes existantes.
- **Impact :** Finance V1 doit gérer des versions de taux avec dates d'effet et figer les paramètres de commission sur chaque commande afin de garantir la stabilité des calculs et leur auditabilité.

### Question 216 — Autorité de modification du taux de commission

- **Question :** Qui peut saisir ou modifier le taux de commission contractuel d'une coopérative ?
- **Réponse :** D — le taux est défini par contrat avec MAHLINE ; seul le rôle `super_admin_root` peut le saisir ou le modifier dans le système.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; gestion des conditions contractuelles de commission par coopérative.
- **Conséquences :**
  - Le taux applicable à une coopérative doit être fondé sur les conditions contractuelles convenues avec MAHLINE.
  - Seul `super_admin_root` dispose du droit de créer ou modifier le taux dans MAHLINE.
  - `super_admin`, `cooperative_admin` et `user` ne peuvent pas modifier ce paramètre ; la coopérative ne peut pas changer unilatéralement son taux depuis son espace.
  - Toute modification doit conserver l'historique, l'auteur, la date d'effet et la référence/justification contractuelle afin de garantir la traçabilité.
  - Les règles de notification à la coopérative, de prise d'effet sur les commandes déjà créées et de gestion d'un avenant restent à préciser.
- **Impact :** Finance V1 doit protéger le paramétrage du taux par une autorisation réservée à `super_admin_root` et conserver les versions successives des conditions contractuelles.

### Question 215 — Base de calcul de la commission MAHLINE

- **Question :** Sur quelle base MAHLINE doit-elle calculer sa commission sur les commandes marketplace ?
- **Réponse :** D — le taux de commission est configurable par coopérative et s'applique au montant des produits uniquement, hors frais de livraison.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance ; paramétrage des conditions de commission par coopérative et calcul des montants dus.
- **Conséquences :**
  - Chaque coopérative peut être associée à un taux de commission qui lui est propre.
  - L'assiette de calcul comprend le montant des produits de la commande ; les frais de livraison en sont exclus.
  - Le taux et les éléments de calcul appliqués doivent être conservés avec les données financières de la commande afin de garantir la traçabilité et de pouvoir expliquer le montant de commission.
  - Les règles de modification du taux, sa date d'effet, le traitement des remises, des annulations, des remboursements partiels et des commandes multi-coopératives restent à préciser.
- **Impact :** Finance V1 doit gérer un paramétrage de commission par coopérative et calculer la commission sur la seule part produits, indépendamment des frais de livraison.

### Question 214 — Fréquence de reversement des encaissements COD à MAHLINE

- **Question :** Après avoir encaissé les paiements COD, à quelle fréquence le transporteur doit-il reverser les fonds à MAHLINE ?
- **Réponse :** C — les reversements sont périodiques, par exemple hebdomadaires, ou suivent le calendrier prévu au contrat avec le transporteur.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance et contrat d'intégration avec Delivery / transporteurs.
- **Conséquences :**
  - MAHLINE accepte un calendrier de reversement défini contractuellement pour chaque transporteur ; un rythme hebdomadaire est un exemple, pas une fréquence universelle imposée.
  - Finance doit suivre les montants COD encaissés par le transporteur, les montants attendus selon le calendrier, les montants effectivement reversés et les montants reçus par MAHLINE.
  - Chaque versement doit être rapprochable des encaissements et commandes concernés ; les écarts et retards doivent rester identifiables.
  - Les délais exacts, les justificatifs, les seuils de retard, les pénalités éventuelles et la procédure de contestation restent à préciser dans les règles contractuelles et les questions Finance dédiées.
- **Impact :** le modèle Finance V1 doit prendre en charge des échéances périodiques configurables par transporteur, sans supposer un reversement par commande ni imposer un calendrier unique.

### Question 213 — Circuit d'encaissement et de reversement du paiement COD

- **Question :** Lorsqu'un client paie en espèces à la livraison, comment les fonds doivent-ils circuler entre le transporteur, MAHLINE et la coopérative ?
- **Réponse :** B — le transporteur encaisse le paiement puis reverse les fonds à MAHLINE ; MAHLINE calcule et répartit les montants dus aux coopératives.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Finance, avec contrats d'intégration vers Delivery, Sales et les coopératives.
- **Conséquences :**
  - Le transporteur est chargé de collecter le montant COD auprès du client et de reverser les fonds à MAHLINE.
  - Finance enregistre et suit la réception des fonds, en les reliant aux commandes et aux expéditions concernées.
  - Finance calcule la part revenant à chaque coopérative ainsi que les commissions dues à MAHLINE, puis suit les reversements aux coopératives.
  - Les règles de rapprochement, les délais et preuves de reversement du transporteur, les statuts de paiement, les écarts, les paiements partiels et les modalités exactes de versement restent à préciser par des questions dédiées.
  - Delivery fournit les informations d'encaissement et de livraison nécessaires ; Sales fournit les références et montants commerciaux figés. Aucun de ces domaines ne devient propriétaire de la comptabilisation financière.
- **Impact :** le périmètre Finance V1 doit inclure le suivi des fonds COD reçus de la part des transporteurs, leur allocation et les reversements aux coopératives. Les détails de calcul des commissions et les cas d'exception restent ouverts.

### Version 2.96 — 13 novembre 2026
- Enregistrement de Q215 : commission MAHLINE configurable par coopérative, calculée sur le montant des produits hors livraison.
- Mise à jour de FR-011 en statut partiellement défini ; les règles de calcul détaillées, le rapprochement et les cas d'exception restent à cadrer.

### Version 2.95 — 13 novembre 2026
- Enregistrement de Q214 : les reversements COD du transporteur à MAHLINE sont périodiques et suivent le calendrier contractuel propre au transporteur.
- Ajout du suivi des montants encaissés, attendus, reversés et reçus ; les retards, écarts et justificatifs détaillés restent à cadrer.

### Version 2.94 — 13 novembre 2026
- Enregistrement de Q213 : le transporteur encaisse le COD puis reverse les fonds à MAHLINE, qui répartit les montants dus aux coopératives et suit les commissions/reversements.
- Précision des contrats Finance avec Delivery et Sales ; les règles de rapprochement, délais, écarts et paiements partiels restent à cadrer.

### Version 2.93 — 13 novembre 2026
- Ajout du référentiel fonctionnel V1 par domaine, synchronisé avec la matrice de la roadmap, afin d'aligner les familles de fonctions et leurs propriétaires.
- Clarification de l'autonomie de Finance et de la propriété des factures marketplace (Finance) versus abonnements MAHLINE (Subscriptions / SaaS Billing) ; Facturation n'est pas un domaine autonome dans cette architecture V1.
- Application de Q72 : aucune règle métier dans Core/Shared ; fonctions techniques transversales uniquement et contrats explicites.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 2.92 — 13 novembre 2026
- Validation de Q72 : les règles métier restent dans leur domaine propriétaire ; Core/Shared sont limités aux responsabilités techniques et transversales, avec contrats explicites.
- Clarification demandée : Finance est autonome et distinct de Sales ; Subscriptions / SaaS Billing et Delivery conservent leurs périmètres autonomes.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.91 — 13 novembre 2026
- Ajout de la décision Q71 : identifiants stables par domaine, sous-identifiants hiérarchiques et références canoniques entre domaines pour éviter les doublons et préserver la propriété fonctionnelle.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.90 — 13 novembre 2026
- Ajout de la décision Q70 : contenu minimum obligatoire et complet des spécifications de domaine, de la définition fonctionnelle jusqu'aux relations inter-domaines, données, composants techniques, tests et traçabilité.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.89 — 13 novembre 2026
- Ajout de la décision de méthode Q69 : C — registre général pour la vision et la traçabilité, spécification versionnée par domaine comme checklist de codage, avec identifiants fonctionnels cohérents et liens vers code/tests.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.88 — 13 novembre 2026
- Ajout de la décision de méthode Q68 : B + C — revue exhaustive obligatoire avant codage, complétée par la détection des lacunes pendant le développement et les tests, avec mise à jour contrôlée des spécifications.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.87 — 13 novembre 2026
- Ajout de la décision Identity Q67 : B — un favori contenant un critère invalide est signalé et bloqué jusqu'à correction ou suppression ; aucun critère n'est ignoré silencieusement.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.86 — 13 novembre 2026
- Ajout de la décision Identity Q66 : B — plafond de 25 filtres favoris personnels pour le `super_admin_root`, refus côté serveur au-delà du plafond sans remplacement automatique.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.85 — 13 novembre 2026
- Ajout de la décision Identity Q65 : B — filtres favoris personnels au `super_admin_root`, réutilisables après revalidation serveur et sans partage ni élévation de droits.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.84 — 13 novembre 2026
- Ajout de la décision Identity Q64 : B — filtres avant export par agent, résultat, document, IP et dossier, en complément de la période ; application serveur et journalisation.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.83 — 13 novembre 2026
- Ajout de la décision Identity Q63 : B — export PDF/CSV incluant date/heure, agent, dossier, document, résultat, adresse IP et type de navigateur/appareil lorsque disponibles ; exclusion des détails techniques complets, secrets et contenus sensibles.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.82 — 13 novembre 2026
- Ajout de la décision Identity Q62 : A — conservation des exports PDF/CSV pendant 24 heures maximum après génération, puis suppression automatique sans altérer les journaux d'audit sources.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.81 — 13 novembre 2026
- Ajout de la décision Identity Q61 : A — téléchargement des exports PDF/CSV via une URL temporaire valable 5 minutes, avec authentification et autorisation serveur obligatoires.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.80 — 13 novembre 2026
- Ajout de la décision Identity Q60 : B — période sélectionnable pour l'export des journaux PDF, limitée à 12 mois maximum par export et contrôlée côté serveur.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.79 — 13 novembre 2026
- Ajout de la décision Identity Q59 : B — export PDF ou CSV des journaux de téléchargements PDF réservé au `super_admin_root`, avec périmètre minimisé et journalisation de chaque export.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.78 — 13 novembre 2026
- Ajout de la décision Identity Q58 : A — consultation de l'historique des téléchargements PDF du support réservée exclusivement au `super_admin_root` ; chaque consultation des journaux est elle-même tracée.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.77 — 13 novembre 2026
- Ajout de la décision Identity Q57 : A — l'historique des consultations/téléchargements PDF par le support n'est pas accessible au client ; il reste réservé à l'audit interne et aux responsables expressément autorisés.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.76 — 13 novembre 2026
- Ajout de la décision Identity Q56 : A — aucune notification individuelle au client après chaque consultation ou téléchargement PDF par le support ; les opérations restent intégralement journalisées en interne.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.75 — 13 novembre 2026
- Ajout de la décision Identity Q55 : B — les agents du support expressément autorisés peuvent télécharger uniquement les PDF communicables nécessaires au traitement du dossier, sous contrôle d'accès et journalisation ; les pièces sensibles/non communicables et l'archive ZIP restent exclues.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.74 — 13 novembre 2026
- Ajout de la décision Identity Q54 : A — les agents du support autorisés à consulter l'historique ZIP ne peuvent pas télécharger l'archive ; le téléchargement reste réservé au client propriétaire authentifié.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.73 — 13 novembre 2026
- Ajout de la décision Identity Q53 : B — l'historique des générations ZIP est consultable uniquement dans l'espace client authentifié ; aucun export PDF/CSV autonome n'est proposé au premier release. Les exports du dossier lui-même restent inchangés.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Version 2.72 — 13 novembre 2026
- Enregistrement de la Question 52 : accès à l'historique ZIP limité aux agents du support expressément autorisés sur le dossier, avec journalisation des consultations.

### Version 2.71 — 13 novembre 2026
- Enregistrement de la Question 51 : le client ne peut pas supprimer l'historique des générations ZIP ; conservation selon les règles du dossier et effacement/anonymisation à échéance.

### Version 2.70 — 13 novembre 2026
- Enregistrement de la Question 50 : historique client des générations ZIP avec dates, statuts et expiration, sans divulgation des détails techniques internes.

### Version 2.69 — 13 novembre 2026
- Enregistrement de la Question 49 : après échec définitif de l'email, maintien de la notification habituelle « ZIP prêt » dans l'espace client, sans message distinct d'échec ; l'erreur reste journalisée.

### Version 2.68 — 13 novembre 2026
- Enregistrement de la Question 48 : maximum de 3 tentatives d'envoi de l'email au total, premier envoi compris, sans impact sur la disponibilité du ZIP.

### Version 2.67 — 13 novembre 2026
- Enregistrement de la Question 47 : en cas d'échec email, notification maintenue dans l'espace client et nouvelles tentatives contrôlées, sans bloquer la disponibilité du ZIP.

### Version 2.66 — 13 novembre 2026
- Enregistrement de la Question 46 : notification dans l'espace client et par email lorsque le ZIP est prêt, avec lien vers l'espace authentifié, sans pièce jointe, et indication de l'expiration.

### Version 2.65 — 13 novembre 2026
- Enregistrement de la Question 45 : jusqu'à 3 tentatives automatiques au total pour les erreurs temporaires de génération du ZIP, puis notification et possibilité de relance manuelle.

### Version 2.64 — 13 novembre 2026
- Enregistrement de la Question 44 : téléchargements du ZIP sans quota fixe pendant les 24 heures, avec authentification, contrôle de propriété, URL temporaire de 5 minutes, journalisation et protections anti-abus.

### Version 2.63 — 13 novembre 2026
- Enregistrement de la Question 43 : URL temporaire valable 5 minutes, renouvelable depuis l'espace client authentifié sans prolonger les 24 heures de disponibilité du ZIP.

### Version 2.62 — 13 novembre 2026
- Enregistrement de la Question 42 : accès au ZIP réservé au client authentifié, avec vérification serveur de la propriété du dossier, URL temporaire sécurisée et journalisation des accès.

### Version 2.61 — 13 novembre 2026
- Enregistrement de la Question 41 : disponibilité du fichier ZIP généré pendant 24 heures après sa génération, puis indisponibilité et suppression automatiques.

### Version 2.60 — 13 novembre 2026
- Enregistrement de la Question 40 : choix entre PDF récapitulatif et ZIP contenant le PDF et les pièces communicables du dossier de contestation.

### Version 2.59 — 13 novembre 2026
- Enregistrement de la Question 39 : téléchargement par le client authentifié d'une copie complète des éléments communicables de son dossier, à l'exclusion des notes internes, données de sécurité non communicables et données de tiers.

### Version 2.58 — 13 novembre 2026
- Enregistrement de la Question 38 : information sur les durées de conservation dans la politique de confidentialité et rappel dans l'espace client authentifié à la clôture du dossier.

### Version 2.57 — 13 novembre 2026
- Enregistrement de la Question 37 : aucune prolongation discrétionnaire au-delà de 5 ans ; conservation prolongée uniquement en présence d'une obligation légale applicable, d'une décision judiciaire ou d'un gel légalement justifié, limité au strict nécessaire.

### Version 2.56 — 13 novembre 2026
- Enregistrement de la Question 36 : à l'expiration des 5 ans, suppression des données identifiantes et justificatifs, et conservation éventuelle de statistiques uniquement sous forme anonymisée irréversible, sous réserve des obligations légales.

### Version 2.55 — 13 novembre 2026
- Enregistrement de la Question 35 : conservation de l'historique des contestations et exceptions pendant 5 ans après clôture définitive, sous réserve de validation juridique et avec traitement distinct des justificatifs personnels/sensibles.

### Version 2.54 — 13 novembre 2026
- Enregistrement de la Question 34 : accès complet de `super_admin_root` et accès limité aux dossiers nécessaires pour les responsables du support expressément habilités, avec journalisation des consultations sensibles.

### Version 2.53 — 13 novembre 2026
- Enregistrement de la Question 33 : le même responsable habilité peut examiner une demande d'exception tardive si aucun autre responsable habilité n'est disponible, avec justification et traçabilité.

### Version 2.52 — 13 novembre 2026
- Enregistrement de la Question 32 : une seule exception de délai peut être accordée par niveau de contestation, avec historisation complète.

### Version 2.51 — 13 novembre 2026
- Enregistrement de la Question 31 : une contestation tardive peut être exceptionnellement admise sur demande motivée, après décision d'un responsable du support habilité ; décision historisée et absence de déblocage automatique du compte.

### Version 2.50 — 13 novembre 2026
- Enregistrement de la Question 30 : délai de 7 jours calendaires après notification du refus pour déposer chaque niveau de contestation.

### Version 2.49 — 13 novembre 2026
- Enregistrement de la Question 29 : deux niveaux maximum de contestation ; premier examen par un responsable du support habilité, second et dernier recours auprès de `super_admin_root`.

### Version 2.48 — 13 novembre 2026
- Enregistrement de la Question 28 : notification minimale automatique au client de toute décision sur sa contestation, acceptée ou refusée, avec accès sécurisé aux détails.

### Version 2.47 — 13 novembre 2026
- Enregistrement de la Question 27 : après acceptation d'une contestation, réouverture autorisée par un responsable du support explicitement habilité ou par `super_admin_root`, avec traçabilité et séparation des tâches.

### Version 2.46 — 13 novembre 2026
- Enregistrement de la Question 26 : contestation possible d'un refus de réouverture, avec examen par un responsable du support habilité ou `super_admin_root`, sans réactivation ni déblocage automatiques.

### Version 2.45 — 13 novembre 2026
- Enregistrement de la Question 25 : notification minimale automatique au client lors de la réouverture acceptée et actualisation du suivi du dossier initial dans l'espace client.

### Version 2.44 — 13 novembre 2026
- Enregistrement de la Question 24 : en cas d'acceptation, réactivation du dossier initial avec conservation intégrale de son historique ; aucun déblocage automatique du compte.

### Version 2.43 — 13 novembre 2026
- Enregistrement de la Question 23 : le client peut demander la réouverture motivée d'un dossier clôturé automatiquement ; décision réservée au support autorisé, sans réactivation ni déblocage automatiques.

### Version 2.42 — 13 novembre 2026
- Enregistrement de la Question 22 : clôture automatique du dossier de récupération si le client ne répond pas à une demande de complément avant l'échéance communiquée ; délai exact et exceptions à préciser techniquement.

### Version 2.41 — 13 novembre 2026
- Enregistrement de la Question 21 : complément du dossier par le client uniquement sur demande explicite du support, avec délai défini et historisation des éléments transmis.

### Version 2.40 — 13 novembre 2026
- Enregistrement de la Question 20 : suivi détaillé du dossier par le client, avec étapes, dates, statuts et informations non sensibles ; notes internes et données sensibles masquées.

### Version 2.39 — 13 novembre 2026
- Enregistrement de la Question 19 : notifications e-mail minimales avec référence et lien sécurisé ; les détails du dossier restent accessibles uniquement après authentification dans l'espace client.

### Version 2.38 — 13 novembre 2026
- Enregistrement de la Question 18 : notification du client à l'ouverture et à la clôture du dossier de récupération ; pas de notification à chaque consultation ou action interne, avec conservation de la traçabilité complète.

### Version 2.37 — 13 novembre 2026
- Enregistrement de la Question 17 : accès complet à l'historique réservé à `super_admin_root` ; accès support limité aux agents explicitement autorisés et aux dossiers nécessaires à leur traitement.

### Version 2.36 — 13 novembre 2026
- Enregistrement de la Question 16 : historique de sécurité détaillé des demandes de récupération, sans conserver les documents sensibles ni les secrets d'authentification.

### Version 2.35 — 13 novembre 2026
- Enregistrement de la Question 15 : récupération renforcée avec vérification d'identité par le support même sans accès à l'ancienne et à la nouvelle adresse e-mail ; preuves et contrôles détaillés restent à définir.

### Version 2.34 — 13 novembre 2026
- Enregistrement de la Question 14 : déblocage uniquement après vérification sécurisée de l'identité, par procédure automatisée ou avec assistance contrôlée du support ; aucun déblocage fondé seulement sur un délai.

### Version 2.33 — 13 novembre 2026
- Enregistrement de la Question 13 : signalement d'un changement d'e-mail non autorisé entraînant un blocage temporaire, la révocation des sessions et tokens, puis une récupération sécurisée ; durée et contrôles détaillés restent à définir.

### Version 2.32 — 13 novembre 2026
- Enregistrement de la Question 12 : lien sécurisé dans la notification à l'ancienne adresse pour signaler un changement non autorisé et démarrer une procédure de sécurisation, sans restauration automatique du compte.

### Version 2.31 — 13 novembre 2026
- Enregistrement de la Question 11 : notification de sécurité envoyée à l'ancienne adresse après validation du changement, sans divulguer la nouvelle adresse.

### Version 2.30 — 13 novembre 2026
- Enregistrement de la Question 10 : après validation du nouvel e-mail, révocation de toutes les sessions web et de tous les tokens API ; reconnexion obligatoire.

### Version 2.29 — 13 novembre 2026
- Enregistrement de la Question 9 : changement d'adresse e-mail possible depuis l'espace client ; la nouvelle adresse ne remplace l'ancienne qu'après vérification.

### Version 2.28 — 13 novembre 2026
- Enregistrement de la Question 8 : maximum 5 demandes de renvoi par heure, puis blocage temporaire pendant une heure.

### Version 2.27 — 13 novembre 2026
- Enregistrement de la Question 7 : chaque nouveau lien de vérification invalide immédiatement les liens précédents ; un seul lien actif par compte.

### Version 2.26 — 13 novembre 2026
- Enregistrement de la Question 6 : vérification par lien sécurisé envoyé par e-mail, expiration après 60 minutes et possibilité de renvoi.

### Version 2.25 — 13 novembre 2026
- Enregistrement de la Question 5 : navigation et ajout au panier autorisés avant vérification de l'e-mail ; confirmation de commande bloquée jusqu'à vérification.

### Version 2.24 — 13 novembre 2026
- Enregistrement de la Question 4 : auto-inscription du client marketplace avec vérification obligatoire de l'adresse e-mail avant toute commande.

### Version 2.23 — 13 novembre 2026
- Décision validée : séparation du profil client entre Identity (identité et sécurité), Sales (profil commercial, paniers et commandes) et Localization (adresses structurées utilisées par Sales).

### Version 2.22 — 13 novembre 2026\n- Décision d'architecture validée : le client marketplace reçoit le rôle distinct `customer` géré par Identity ; le rôle interne `user` n'est pas réutilisé et aucune authentification parallèle Customer n'est créée.\n\n### Version 2.21 — 13 novembre 2026
- Ajout de la décision Media Q96 : limite de 50 PDF par téléchargement groupé, livrés dans une archive ZIP.
- Aucune modification du code applicatif ; aucune fusion de branche.

### Décision Q270 — Autorisations de gestion des promotions (VALIDÉE)
- **Question :** Quels rôles peuvent créer, modifier et supprimer les promotions des produits ?
- **Réponse :** A — seul le `cooperative_admin` peut gérer les promotions de sa propre coopérative.
- **Décision :** la gestion commerciale des promotions (création, modification, suppression) est réservée au `cooperative_admin`, strictement dans le périmètre de sa coopérative. Le `super_admin` affecté et le `super_admin_root` peuvent superviser selon leurs droits, mais ne modifient pas directement les promotions commerciales au premier release. Toute action est contrôlée côté serveur et respecte l'isolation des coopératives.
- **Frontières :** Products possède les paramètres et l'historique des promotions ; Catalog expose uniquement les informations publiables ; Sales revalide et fige le prix appliqué. Les droits de supervision ne confèrent pas un droit d'écriture commerciale.


### Décision Q311 — Suppression du compte client à l'initiative du client (VALIDÉE)
- **Question :** Quelle procédure pour « Supprimer mon compte » ?
- **Réponse :** A — demande du client, désactivation immédiate, puis suppression ou anonymisation selon les obligations de conservation.
- **Décision :** le client authentifié peut initier une demande de suppression de son compte. La demande déclenche immédiatement la désactivation de l'accès au compte. Le traitement définitif supprime ou anonymise les données personnelles qui peuvent l'être, tout en préservant les commandes, factures, preuves et traces qui doivent être conservées pour des raisons légales, comptables, de sécurité ou de preuve.
- **Conséquences :** vérifier l'identité et sécuriser la demande ; révoquer les sessions web et jetons API ; empêcher toute nouvelle connexion et commande ; conserver uniquement les données nécessaires avec accès restreint et durée de conservation définie ; tracer la demande et son traitement sans conserver inutilement de données personnelles. Les règles de traitement des commandes en cours, du délai de grâce/annulation éventuel, des sauvegardes et de la réversibilité doivent être précisées avant codage. Aucune suppression physique aveugle des relations métier.
- **Statut :** VALIDÉ — premier release, sous réserve de cadrage détaillé de la conservation.

### Décision Q312 — Champs modifiables dans le profil client (VALIDÉE)
- **Question :** Quels champs le client peut-il modifier lui-même dans son profil ?
- **Réponse :** A — nom, prénom, téléphone, adresse e-mail et photo de profil ; les adresses de livraison sont gérées séparément.
- **Décision :** depuis son espace personnel, le client peut modifier directement son nom, son prénom, son numéro de téléphone, son adresse e-mail et sa photo de profil. Le carnet d'adresses de livraison reste une section distincte, régie par les décisions Q288-Q298 et les règles de snapshot des commandes.
- **Conséquences :** les champs d'identité sont enregistrés sous la responsabilité d'Identity ; la photo suit les exigences Media déjà définies (limite stricte de 2 Mo et contrôles de contenu/stockage). Un changement d'e-mail suit obligatoirement le processus déjà validé Q9-Q12 : l'ancienne adresse reste principale jusqu'à vérification de la nouvelle ; après validation effective, toutes les sessions web et tous les tokens API sont révoqués et une notification de sécurité est envoyée à l'ancienne adresse. Les contrôles d'accès, validations serveur, audit et tests d'isolation restent obligatoires.
- **Statut :** VALIDÉ — premier release.

### Décision Q313 — Vérification du numéro de téléphone client (VALIDÉE)
- **Question :** La vérification du numéro de téléphone est-elle obligatoire avant qu'un client puisse commander ?
- **Réponse :** B — non ; le numéro est enregistré sans vérification dédiée. Seule l'adresse e-mail doit être vérifiée.
- **Décision :** le client peut renseigner et modifier son numéro de téléphone sans devoir confirmer sa possession par SMS ou code à usage unique. La vérification de l'adresse e-mail demeure obligatoire selon Q286-Q287 et constitue le contrôle de vérification requis avant activation et commande.
- **Conséquences :** aucun OTP SMS ni service d'envoi SMS n'est requis pour le parcours V1 d'inscription ou de modification du téléphone. Le numéro reste soumis à validation de format et aux contrôles serveur ; il ne doit pas être présenté comme vérifié. Cette décision ne modifie pas les coordonnées du destinataire propres à chaque adresse de livraison (Q289).
- **Statut :** VALIDÉ — premier release.

### Décision Q314 — Identifiant de connexion client (VALIDÉE)
- **Question :** Quel identifiant le client utilise-t-il pour se connecter ?
- **Réponse :** A — adresse e-mail uniquement.
- **Décision :** l'authentification du compte client utilise exclusivement l'adresse e-mail. Le numéro de téléphone enregistré dans le profil ou dans une adresse de livraison ne constitue pas un identifiant de connexion.
- **Conséquences :** le flux de connexion client doit rechercher le compte par e-mail et appliquer les contrôles Identity existants : compte actif, adresse e-mail vérifiée et protections anti-abus. Aucun parcours de connexion par téléphone/SMS n'est inclus dans le premier release. Toute évolution future nécessitera une décision dédiée et ne doit pas être activée implicitement.
- **Statut :** VALIDÉ — premier release.

### Décision Q315 — Récupération du mot de passe client (VALIDÉE)
- **Question :** Comment le client récupère-t-il l'accès à son compte en cas d'oubli du mot de passe ?
- **Réponse :** A — lien sécurisé envoyé par e-mail, valable 30 minutes et utilisable une seule fois.
- **Décision :** le client demande une réinitialisation depuis le parcours « mot de passe oublié » et reçoit un lien de réinitialisation à son adresse e-mail. Le lien expire après 30 minutes et ne peut être utilisé qu'une seule fois.
- **Conséquences :** le serveur doit vérifier l'expiration et l'usage unique avant toute modification du mot de passe. Le nouveau mot de passe passe par les règles de complexité Identity en vigueur. Aucun code OTP par e-mail ou SMS n'est requis pour ce parcours. Les secrets de réinitialisation et mots de passe ne doivent jamais apparaître dans les journaux.
- **Statut :** VALIDÉ — premier release.

### Décision Q316 — Révocation après réinitialisation du mot de passe client (VALIDÉE)
- **Question :** Que deviennent les sessions web et tokens API après une réinitialisation réussie du mot de passe ?
- **Réponse :** A — toutes les sessions web et tous les tokens API actifs sont révoqués.
- **Décision :** après confirmation effective de la réinitialisation du mot de passe, toutes les sessions web du client et tous ses tokens API sont invalidés, y compris la session ayant éventuellement servi à finaliser l'opération.
- **Conséquences :** le client doit se reconnecter avec son nouveau mot de passe. La révocation doit être exécutée côté serveur comme partie intégrante du processus de réinitialisation, sans laisser de session ou token actif. L'événement est audité sans enregistrer le mot de passe, le lien ni le secret de réinitialisation.
- **Statut :** VALIDÉ — premier release.

### Décision Q317 — Limitation des tentatives de connexion client (VALIDÉE)
- **Question :** Quelle limite appliquer aux tentatives de connexion échouées du client ?
- **Réponse :** C — reprendre intégralement la politique anti-abus Identity MAHLINE.
- **Décision :** le parcours de connexion client applique les trois limites cumulatives déjà définies : 5 tentatives par compte, 30 tentatives par adresse IP et 5 tentatives par combinaison adresse IP + adresse e-mail, sur une fenêtre de 15 minutes.
- **Conséquences :** les limites sont appliquées côté serveur et de manière cohérente avec les autres espaces MAHLINE. Le parcours client ne crée pas de seuil plus permissif ni de mécanisme de contournement. Les compteurs et réponses doivent éviter l'énumération des comptes et ne doivent jamais journaliser de mot de passe ou de secret.
- **Statut :** VALIDÉ — premier release.

### Décision Q318 — Option « Se souvenir de moi » pour le client (VALIDÉE)
- **Question :** Le client peut-il choisir de rester connecté sur son appareil ?
- **Réponse :** A — oui, option « Se souvenir de moi », avec session persistante sécurisée et révocable.
- **Décision :** le formulaire de connexion client propose une option explicite et facultative « Se souvenir de moi ». Elle n'est jamais activée implicitement : le client choisit de l'utiliser pour l'appareil concerné.
- **Conséquences :** l'option repose sur un mécanisme de persistance sécurisé côté serveur, distinct d'une simple conservation du mot de passe dans le navigateur. Chaque session reste individuellement révocable selon les règles multi-sessions MAHLINE. La révocation globale prévue par Q316 doit également invalider toute persistance associée. La durée de persistance, les modalités de renouvellement/rotation et le comportement précis à expiration restent à définir avant codage ; ne pas inventer de durée.
- **Statut :** VALIDÉ — premier release, paramètres techniques à préciser avant implémentation.

### Décision Q319 — Gestion des appareils et sessions client (VALIDÉE)
- **Question :** Quelles possibilités le client doit-il avoir pour gérer ses appareils connectés ?
- **Réponse :** A — consulter les appareils/sessions actifs et révoquer un appareil ou tous les autres appareils.
- **Décision :** l'espace sécurité du client affiche les sessions/appareils actifs avec, lorsque disponibles, la date de connexion, le navigateur, le système d'exploitation et la dernière activité. Le client peut révoquer individuellement une session/appareil ou demander la déconnexion de toutes les autres sessions en conservant celle qu'il utilise pour effectuer l'action.
- **Conséquences :** les contrôles sont limités aux sessions du client authentifié et vérifiés côté serveur ; aucun accès à la session d'un autre compte n'est possible. La révocation d'une session doit invalider sa persistance « Se souvenir de moi » associée. L'opération est auditée sans exposer d'identifiants de session ou de secrets. La révocation globale imposée par Q316 continue de révoquer toutes les sessions, y compris la session courante, ainsi que tous les tokens API.
- **Statut :** VALIDÉ — premier release.

### Exigence espace client — espace personnel isolé
- L'espace client constitue un espace fonctionnel distinct des espaces `super_admin_root`, `super_admin`, `cooperative_admin` et des utilisateurs internes.
- Routes, contrôleurs, vues, navigation, permissions et données doivent appliquer cette séparation ; masquer les liens dans l'interface ne suffit pas. Chaque accès et chaque identifiant de ressource sont contrôlés côté serveur afin d'empêcher les accès inter-comptes et IDOR.
- Le compte client ne reçoit aucun privilège interne par le seul fait de son inscription ou de son authentification. Le modèle d'identité et le rôle client doivent être explicitement établis sans réutilisation implicite du rôle interne `user`.

### Exigence espace client — photo de profil
- Le client peut ajouter et remplacer une photo de profil depuis son espace personnel.
- Taille maximale stricte : **2 Mo** par fichier ; contrôle serveur obligatoire, même si le formulaire applique aussi une limite côté navigateur.
- Vérifier le type réel du fichier, les extensions/MIME autorisés, les dimensions et les contenus ; générer un nom de stockage sûr, empêcher l'exécution, utiliser un stockage non public par défaut et contrôler l'accès à la photo.
- Le stockage, le traitement et la suppression des fichiers relèvent du domaine Media ; Identity conserve uniquement la référence nécessaire. Auditer Media et les mécanismes de stockage existants avant de choisir l'implémentation.
- Le remplacement et la suppression de la photo doivent éviter les références orphelines et respecter les règles de conservation applicables au compte.

### Décision Q310 — Rôles autorisés à intervenir sur une sous-commande en retard (VALIDÉE)
- **Question :** Quels administrateurs peuvent relancer une coopérative ou décider à sa place après dépassement du délai ?
- **Réponse :** B — `super_admin_root` et `super_admin` affecté à la coopérative concernée.
- **Décision :** `super_admin_root` peut intervenir sur toutes les coopératives. Un `super_admin` ne peut intervenir que sur les coopératives qui lui sont affectées. Le `cooperative_admin` n'est pas autorisé à agir à la place de l'administration ; il conserve la responsabilité de répondre pour sa propre coopérative.
- **Conséquences :** les actions de relance, d'octroi d'un nouveau délai et de décision administrative définies par Q309 sont soumises à cette matrice d'autorisation. Le contrôle d'affectation est effectué côté serveur à chaque action ; aucune élévation de privilège ni contournement de l'isolation coopérative n'est admis. L'acteur réel, l'horodatage, l'action et les états avant/après restent audités.
- **Statut :** VALIDÉ — premier release.

### Décision Q309 — Actions administrateur sur une sous-commande en retard (VALIDÉE)
- **Question :** Quelles actions l'administrateur peut-il effectuer lorsqu'une sous-commande reste sans réponse après l'échéance ?
- **Réponse :** C — relancer la coopérative avec un nouveau délai ou décider à sa place.
- **Décision :** après expiration du délai initial, un administrateur autorisé peut soit relancer la coopérative en lui accordant un nouveau délai, soit prendre la décision d'accepter ou de refuser la sous-commande à sa place.
- **Conséquences :**
  - La relance et le nouveau délai sont enregistrés ; la durée du nouveau délai est définie par l'administration centrale et ne peut pas dépasser 24 heures.
  - La décision administrative d'acceptation ou de refus est explicitement identifiée comme prise par un administrateur au nom de la coopérative ; elle ne doit jamais être présentée comme une action effectuée par celle-ci.
  - Toute intervention exige les permissions appropriées et conserve l'identité de l'administrateur, l'horodatage, l'action, l'ancien et le nouvel état, ainsi que le motif/commentaire lorsqu'il est fourni.
  - La coopérative concernée et les acteurs concernés sont informés de l'intervention selon les règles de Communication.
  - Les autres sous-commandes poursuivent leur cycle indépendamment ; les règles de clôture globale Q306 restent applicables uniquement lorsque toutes les sous-commandes sont effectivement refusées.
- **Périmètre concerné :** Sales (états et décisions), administration (autorisations et interventions), Communication (relances et notifications), Coopératives (réponse et suivi).
- **Statut :** VALIDÉ — premier release.

### Décision Q308 — Sous-commande sans réponse à l'expiration du délai (VALIDÉE)
- **Question :** Que faire si une coopérative ne répond pas dans le délai commun de 12 à 24 heures ?
- **Réponse :** B — la sous-commande reste en attente et un administrateur est alerté pour intervention.
- **Décision :** l'expiration du délai de réponse ne provoque ni refus automatique ni annulation automatique. La sous-commande conserve son statut d'attente jusqu'à une intervention manuelle autorisée d'un administrateur.
- **Conséquences :**
  - Le dépassement de l'échéance est enregistré et visible ; la sous-commande est signalée comme en retard/sans réponse sans être considérée comme refusée.
  - Un administrateur reçoit une alerte afin d'intervenir et de faire traiter la situation.
  - Les autres sous-commandes de la commande globale poursuivent leur propre cycle et ne sont pas bloquées par cette attente.
  - Toute décision prise par l'administrateur doit respecter les permissions, être horodatée et auditée ; l'identité de l'intervenant et le motif éventuel sont conservés.
  - La clôture globale « Refusée » prévue par Q306 ne s'applique que lorsque toutes les sous-commandes ont effectivement été refusées ; une sous-commande simplement en retard reste en attente.
- **Périmètre concerné :** Sales (échéance et statut d'attente), Communication (alerte administrateur), administration (intervention autorisée), Coopératives (réponse attendue).
- **Statut :** VALIDÉ — premier release.

### Décision Q307 — Délai de réponse des coopératives à une sous-commande (VALIDÉE)
- **Question :** Quel délai est accordé à chaque coopérative pour accepter ou refuser sa sous-commande ?
- **Réponse :** A — délai commun configurable par MAHLINE, avec une durée cible de 12 heures et un plafond absolu de 24 heures.
- **Décision :** le délai de réponse est défini centralement par MAHLINE et s'applique de manière identique à toutes les coopératives. La durée de référence est de 12 heures ; le paramètre peut être ajusté, sans jamais dépasser 24 heures.
- **Conséquences :**
  - Le délai commence à la mise à disposition/notif​ication de la sous-commande à la coopérative ; le point technique exact de départ devra être aligné avec l'événement de transmission Sales/Communication.
  - La coopérative doit accepter ou refuser la sous-commande dans le délai applicable.
  - La durée configurée est globale et non personnalisable par coopérative.
  - La règle à appliquer à l'expiration sans réponse (refus automatique, annulation, relance ou autre traitement) reste à décider séparément ; aucune conséquence automatique n'est présumée par Q307.
  - Les heures de début/fin, la durée applicable et les relances éventuelles devront être traçables.
- **Périmètre concerné :** Sales (état et échéance de réponse), Communication (transmission et rappels), Coopératives (traitement de la sous-commande).
- **Statut :** VALIDÉ — premier release.

### Décision Q306 — Refus de toutes les sous-commandes d'une commande globale (VALIDÉE)
- **Question :** Que devient la commande globale si toutes les coopératives refusent leurs sous-commandes ?
- **Réponse :** A — la commande globale passe au statut « Refusée », les frais de livraison deviennent nuls et le client est informé.
- **Décision :** lorsque toutes les sous-commandes coopératives d'une commande globale sont définitivement refusées, Sales clôt automatiquement la commande globale avec le statut « Refusée ». Aucune expédition n'étant maintenue, le total des frais de livraison est égal à zéro.
- **Conséquences :**
  - La clôture intervient lorsque le serveur a enregistré le refus de toutes les sous-commandes ; elle ne dépend pas d'une intervention manuelle d'un administrateur.
  - Les frais de livraison de toutes les expéditions refusées sont retirés ; le montant de livraison restant dû est nul.
  - Communication informe le client de la clôture et du refus de l'ensemble des sous-commandes.
  - Sales conserve les décisions de chaque coopérative, les dates, les statuts et les montants recalculés ; les événements sont audités et le traitement doit être idempotent.
  - Cette règle ne définit pas à elle seule le remboursement ou la libération d'un paiement en ligne ; le premier release conserve le mode de paiement déjà cadré.
- **Périmètre concerné :** Sales (agrégation des refus et clôture globale), Delivery (absence d'expéditions et frais nuls), Communication (notification client), Finance (montants dus).
- **Statut :** VALIDÉ — premier release.

### Décision Q305 — Frais d'une sous-commande entièrement refusée avant expédition (VALIDÉE)
- **Question :** Que deviennent les frais de livraison attribués à une coopérative qui refuse entièrement sa sous-commande avant toute expédition ?
- **Réponse :** A — retirer les frais de cette expédition du total ; le client ne paie que les expéditions restantes.
- **Décision :** lorsqu'une sous-commande est entièrement refusée avant expédition et qu'aucun colis n'est remis au transporteur, l'expédition correspondante n'a pas lieu et ses frais sont retirés du total de livraison de la commande globale. Le montant dû est recalculé sur les seules expéditions effectivement maintenues.
- **Conséquences :**
  - Les frais de chaque expédition restent individualisés conformément à Q303.
  - Le refus total d'une sous-commande supprime les frais de l'expédition annulée du total client ; les frais des autres expéditions restent dus.
  - Si une sous-commande est partiellement refusée mais poursuit avec les lignes restantes, son expédition unique demeure (Q304) ; le traitement tarifaire de cette expédition suit les règles Delivery applicables et ne doit pas être assimilé automatiquement à un refus total.
  - Cette règle concerne le refus complet avant expédition ; les frais après préparation, remise au transporteur ou échec de livraison nécessitent des règles distinctes.
- **Périmètre concerné :** Sales (refus et recalcul du total), Delivery (annulation de l'expédition et frais), Finance (montants dus).
- **Statut :** VALIDÉ — premier release.

### Décision Q304 — Nombre de colis et d'expéditions par sous-commande coopérative (VALIDÉE)
- **Question :** Une coopérative peut-elle répartir une sous-commande confirmée entre plusieurs colis/expéditions en V1 ?
- **Réponse :** A — une seule expédition et un seul colis par sous-commande coopérative en V1.
- **Décision :** chaque sous-commande coopérative confirmée correspond à un colis unique et à une seule expédition Delivery. Le colis dispose de son propre suivi transporteur ; aucune division d'une même sous-commande en plusieurs colis ou expéditions n'est proposée dans le premier release.
- **Conséquences :**
  - Une commande globale multi-coopératives peut produire plusieurs expéditions, à raison d'une expédition/colis par sous-commande coopérative confirmée.
  - Les frais de livraison restent calculés par expédition, puis additionnés au niveau de la commande globale conformément à Q303.
  - Le suivi et les statuts Delivery sont rattachés à l'expédition unique de chaque sous-commande.
  - La gestion multi-colis pour une même sous-commande est réservée à une évolution ultérieure ; le modèle doit rester extensible sans l'activer en V1.
- **Périmètre concerné :** Sales (sous-commandes), Delivery (colis, expéditions et suivi), Communication (notifications de suivi).
- **Statut :** VALIDÉ — premier release.

### Décision Q303 — Calcul des frais de livraison multi-coopératives (VALIDÉE)
- **Question :** Comment calculer les frais de livraison d'une commande contenant des produits de plusieurs coopératives ?
- **Réponse :** A — calculer les frais séparément pour chaque expédition, puis additionner les montants.
- **Décision :** chaque coopérative expédie depuis son propre lieu de départ vers l'adresse commune du client. Les frais sont calculés individuellement pour chaque expédition selon la grille et les règles tarifaires applicables, puis additionnés pour former le total des frais de livraison de la commande globale.
- **Conséquences :**
  - Chaque sous-commande/expédition conserve ses propres frais de livraison et les informations nécessaires à leur calcul.
  - Le total de livraison affiché au client correspond à la somme des frais des expéditions composant la commande.
  - Le refus ou l'annulation d'une sous-commande entraîne le recalcul du total à partir des seules expéditions restantes, selon les règles Sales/Delivery applicables ; la destination commune définie par Q300 et Q302 ne change pas.
  - Le calcul doit rester configurable et évolutif ; aucune grille tarifaire réelle ni aucun tarif manquant ne doit être inventé ou codé en dur sans validation.
- **Périmètre concerné :** Delivery (tarification et expéditions), Sales (total de commande et sous-commandes), Localization (villes de départ et destination).
- **Statut :** VALIDÉ — premier release.

### Décision Q302 — Adresse des sous-commandes restantes après refus d'une coopérative (VALIDÉE)
- **Question :** Si une coopérative refuse sa sous-commande dans une commande multi-coopératives, l'adresse des sous-commandes restantes peut-elle changer ?
- **Réponse :** A — l'adresse de livraison reste identique pour toutes les sous-commandes restantes.
- **Décision :** le refus d'une sous-commande par une coopérative n'altère pas la destination de la commande globale. Les autres sous-commandes poursuivent leur traitement avec la même adresse de livraison commune et le même snapshot de destination.
- **Conséquences :** aucune nouvelle adresse n'est demandée ou sélectionnée automatiquement à la suite du refus ; les frais et expéditions peuvent être recalculés selon les règles Delivery, sans changement de destination. Toute demande ultérieure de changement reste soumise à l'interdiction Q301 après confirmation.
- **Périmètre concerné :** Sales (commande et sous-commandes), Delivery (expéditions), Localization (snapshot de destination).
- **Statut :** VALIDÉ — premier release.

### Décision Q301 — Changement d'adresse après confirmation de commande (VALIDÉE)
- **Question :** Le client peut-il modifier l'adresse après confirmation de sa commande, avant remise au transporteur ?
- **Réponse :** B — aucun changement d'adresse après confirmation ; le client doit annuler et repasser commande si les règles d'annulation l'autorisent.
- **Décision :** dès la confirmation de la commande, son adresse de livraison et son snapshot deviennent immuables dans le parcours client. Le client ne dispose d'aucune action de modification de destination, même si le colis n'a pas encore été remis au transporteur.
- **Conséquences :**
  - Si l'annulation est encore autorisée par le statut et les règles métier applicables, le client peut annuler selon le processus prévu puis créer une nouvelle commande avec la bonne adresse.
  - Si l'annulation n'est plus autorisée, l'adresse ne peut pas être changée par le client ; aucune correction implicite ou unilatérale n'est permise.
  - Les sous-commandes et expéditions associées conservent la destination figée ; les commandes déjà créées ne sont jamais réécrites par une modification du carnet d'adresses.
  - Cette règle précise et restreint la possibilité générale de correction postérieure mentionnée à Q300 : aucun changement de destination après confirmation à la demande du client en V1.
- **Périmètre concerné :** Sales (état de confirmation et annulation), Localization (snapshot), Delivery (destination d'expédition), Identity (client propriétaire).
- **Statut :** VALIDÉ — premier release.
- **Exigences :** contrôle serveur de l'état de commande, immutabilité du snapshot, cohérence multi-coopératives, audit et tests des transitions/annulations.

### Décision Q300 — Adresse de livraison d'une commande multi-coopératives (VALIDÉE)
- **Question :** Une commande unique contenant des produits de plusieurs coopératives peut-elle utiliser plusieurs adresses de livraison ?
- **Réponse :** A — une seule adresse de livraison commune à toute la commande.
- **Décision :** le client choisit une seule adresse de livraison pour la commande commerciale globale. Toutes les sous-commandes créées pour les coopératives concernées héritent de cette même destination ; elles ne disposent pas d'une adresse de livraison indépendante en V1.
- **Conséquences :**
  - Le checkout vérifie que l'adresse appartient au client et enregistre un snapshot unique au niveau de la commande globale.
  - Chaque sous-commande et chaque expédition associée se réfèrent à la destination issue de ce snapshot, sans permettre à une coopérative de la remplacer unilatéralement.
  - Les frais et opérations de livraison peuvent rester calculés par expédition/cooperative selon les règles Delivery, mais la destination client reste commune.
  - Toute correction après création de commande relève d'un processus Sales/Delivery autorisé, contrôlé et tracé ; elle ne modifie pas rétroactivement le carnet d'adresses.
  - La possibilité de destinations distinctes par coopérative est exclue du premier release et pourra être étudiée dans une évolution dédiée.
- **Périmètre concerné :** Sales (commande globale et sous-commandes), Localization (adresse et snapshot), Delivery (expéditions et destination), Identity (propriété du compte client).
- **Statut :** VALIDÉ — premier release.
- **Exigences :** contrôle serveur de propriété, cohérence de destination entre sous-commandes, intégrité du snapshot, isolation des données, audit et tests multi-coopératives.

### Décision Q299 — Mode de traitement des commandes V1 (VALIDÉE)
- **Question :** Pour MAHLINE V1, faut-il imposer une adresse de livraison à toutes les commandes, même si un futur produit ou service est proposé en retrait à la coopérative ?
- **Réponse :** A — oui. Toutes les commandes V1 nécessitent une adresse de livraison.
- **Décision :** le parcours de commande V1 est exclusivement fondé sur la livraison à une adresse. Aucun mode de retrait à la coopérative n'est proposé ni accepté dans cette version.
- **Conséquences :** toute commande doit être associée à une adresse de livraison valide appartenant au client, sélectionnée au checkout et figée dans le snapshot de livraison de la commande. Le serveur bloque la confirmation si cette adresse est absente, invalide ou non détenue par le client.
- **Évolution future :** le retrait à la coopérative pourra être étudié dans une évolution dédiée, avec ses propres règles opérationnelles et son propre parcours ; il ne doit pas être anticipé comme mode actif en V1.

### Décision Q298 — Suppression de la dernière adresse de livraison (VALIDÉE)
- **Question :** Un client supprime sa dernière adresse de livraison enregistrée. Quelle règle appliquer ?
- **Réponse :** A — autoriser la suppression ; il pourra créer une nouvelle adresse lors de sa prochaine commande, mais ne pourra pas finaliser le checkout sans adresse valide.
- **Décision :** aucune adresse minimale n'est imposée dans le carnet. Le client peut supprimer sa dernière adresse et se retrouver avec un carnet vide, sans adresse par défaut.
- **Conséquences au checkout :** le client doit créer et sélectionner une adresse de livraison valide avant de pouvoir confirmer une commande. Le serveur bloque la finalisation si aucune adresse valide appartenant au client n'est sélectionnée. Le catalogue et la navigation publique restent accessibles.
- **Cohérence :** les snapshots des commandes déjà créées restent inchangés. La suppression de la dernière adresse ne crée ni ne modifie automatiquement une adresse par défaut.
- **Sécurité :** validation de propriété et de validité côté serveur ; aucune commande ne peut être confirmée avec une adresse absente ou appartenant à un autre client.

### Décision Q297 — Suppression d'une adresse utilisée par une commande en cours (VALIDÉE)
- **Question :** Un client souhaite supprimer une adresse déjà enregistrée dans une commande en cours de préparation ou de livraison. Quelle règle appliquer ?
- **Réponse :** A — autoriser la suppression du carnet ; la commande conserve son snapshot complet et reste inchangée.
- **Décision :** le client peut supprimer une adresse de son carnet même si elle a été utilisée pour une commande en cours. La suppression affecte uniquement le carnet d'adresses ; elle ne modifie, n'annule et ne bloque pas la commande.
- **Conséquences :** chaque commande conserve son snapshot de livraison autonome, comprenant les coordonnées et informations de destinataire enregistrées au moment de la commande. Le snapshot reste accessible aux acteurs autorisés du parcours de commande et de livraison selon leurs permissions, même après suppression de l'adresse source.
- **Sécurité et cohérence :** suppression contrôlée côté serveur dans le périmètre du client propriétaire ; les commandes historiques et en cours ne dépendent pas de la présence de l'adresse dans le carnet.

### Décision Q296 — Adresse choisie au checkout et adresse par défaut (VALIDÉE)
- **Question :** Si le client choisit une adresse différente au moment de passer commande, doit-elle automatiquement remplacer son adresse par défaut enregistrée ?
- **Réponse :** A — non. Le choix au checkout vaut uniquement pour cette commande.
- **Décision :** l'adresse sélectionnée au checkout est utilisée pour la commande en cours uniquement. Elle ne modifie pas le statut d'adresse par défaut du carnet du client.
- **Conséquences :** le snapshot de livraison de la commande est créé à partir de l'adresse explicitement sélectionnée et de ses coordonnées de destinataire ; l'adresse par défaut enregistrée reste inchangée. Le client peut modifier séparément son adresse par défaut depuis la gestion de son carnet.
- **Sécurité et cohérence :** le serveur vérifie que l'adresse sélectionnée appartient au client authentifié et enregistre le snapshot de commande ; aucune valeur fournie par le navigateur ne peut modifier le carnet ou les données historiques sans autorisation.

### Décision Q295 — Changement manuel de l'adresse par défaut (VALIDÉE)
- **Question :** Quand un client choisit une autre adresse comme adresse par défaut, que doit faire MAHLINE ?
- **Réponse :** A — remplacer immédiatement l'ancienne adresse par la nouvelle ; une seule adresse par défaut au maximum.
- **Décision :** le choix explicite d'une nouvelle adresse par défaut retire immédiatement ce statut à l'ancienne et l'attribue à la nouvelle, dans une opération atomique côté serveur. Le carnet d'adresses d'un client peut avoir zéro ou une adresse par défaut, jamais plusieurs.
- **Cohérence :** le changement ne modifie pas les snapshots de livraison des commandes existantes. La sélection d'une adresse au checkout et la désignation d'une adresse par défaut restent deux actions distinctes, sauf si une décision fonctionnelle ultérieure les lie explicitement.
- **Sécurité :** le serveur vérifie que les deux adresses concernées appartiennent au client authentifié ; aucune modification inter-client n'est possible.

### Décision Q294 — Modification d'une adresse définie par défaut (VALIDÉE)
- **Question :** Si le client modifie une adresse qui est déjà définie comme adresse par défaut, que doit faire MAHLINE ?
- **Réponse :** A — conserver automatiquement cette adresse comme adresse par défaut après modification.
- **Décision :** la modification des coordonnées ou informations d'une adresse ne change pas son statut d'adresse par défaut. Si elle était l'adresse par défaut avant modification, elle le reste après validation.
- **Cohérence :** cette règle ne s'applique qu'au carnet d'adresses du client et ne modifie aucun snapshot de livraison déjà enregistré sur une commande. La modification n'entraîne pas de propagation vers les commandes existantes.
- **Sécurité :** la modification et la conservation du statut par défaut sont contrôlées côté serveur, dans le périmètre du client propriétaire.

### Décision Q293 — Première adresse automatiquement définie par défaut (VALIDÉE)
- **Question :** Lorsqu'un client crée sa toute première adresse de livraison, doit-elle devenir automatiquement son adresse par défaut ?
- **Réponse :** A — oui, la première adresse devient automatiquement l'adresse par défaut. Les suivantes ne le deviennent pas automatiquement.
- **Décision :** à la création de la toute première adresse de livraison active d'un client, le serveur la marque automatiquement comme adresse par défaut. La création d'adresses ultérieures ne change jamais automatiquement le choix existant.
- **Cohérence :** si le client a supprimé son adresse par défaut et qu'il possède encore d'autres adresses, la création d'une nouvelle adresse ne la rend pas automatiquement par défaut. Il devra choisir explicitement une adresse au prochain checkout ou définir lui-même une nouvelle adresse par défaut.
- **Sécurité :** cette règle est appliquée côté serveur, dans le périmètre du client authentifié ; un client ne peut ni définir ni modifier l'adresse par défaut d'un autre client.

### Décision Q292 — Suppression de l'adresse de livraison par défaut (VALIDÉE)
- **Question :** Si le client supprime l'adresse actuellement définie comme adresse par défaut, que doit faire MAHLINE ?
- **Réponse :** B — ne définir aucune adresse par défaut ; le client devra en choisir une lors de sa prochaine commande.
- **Décision :** lorsqu'un client supprime son adresse de livraison par défaut, aucune autre adresse n'est désignée automatiquement. Le carnet peut donc temporairement ne contenir aucune adresse par défaut, même s'il reste d'autres adresses enregistrées.
- **Conséquences :** au prochain checkout, si aucune adresse par défaut n'existe, le client doit sélectionner explicitement l'une de ses adresses enregistrées ou en créer une nouvelle avant de poursuivre. Le système ne choisit jamais arbitrairement une adresse.
- **Cohérence et sécurité :** la suppression ne modifie aucun snapshot de livraison des commandes existantes. Le statut d'adresse par défaut est géré côté serveur et une seule adresse au maximum peut être marquée par défaut pour un client ; zéro est autorisé.

### Décision Q291 — Nombre maximal d'adresses de livraison par client (VALIDÉE)
- **Question :** Combien d'adresses de livraison un client peut-il enregistrer dans son compte ?
- **Réponse :** A — 5 adresses maximum.
- **Décision :** chaque compte client peut conserver au maximum cinq adresses de livraison enregistrées simultanément dans son carnet.
- **Règles de gestion :** le serveur contrôle cette limite lors de toute création d'adresse ; une sixième adresse est refusée tant qu'une adresse existante n'a pas été supprimée. La modification d'une adresse existante ne consomme pas une nouvelle place. La limite s'applique au carnet du client, indépendamment des snapshots conservés dans les commandes historiques.
- **Cohérence et sécurité :** la suppression d'une adresse du carnet ne supprime ni ne modifie les snapshots des commandes déjà créées. Le contrôle de limite et la propriété du carnet sont vérifiés côté serveur, y compris en cas de requêtes concurrentes.

### Décision Q290 — Modification d'une adresse utilisée dans une commande (VALIDÉE)
- **Question :** Si le client modifie une adresse de son carnet après avoir passé une commande, que doit-il se passer ?
- **Réponse :** A — la modification concerne uniquement les prochaines commandes ; les anciennes conservent l'adresse et les coordonnées initiales.
- **Décision :** toute modification du carnet d'adresses du client s'applique uniquement aux commandes créées après cette modification. Les commandes déjà passées conservent leur snapshot de livraison tel qu'il a été enregistré au checkout, qu'elles soient encore en préparation, non expédiées ou déjà expédiées.
- **Conséquences :** le client peut modifier une adresse enregistrée même si elle a été utilisée dans une commande en cours ; cette modification ne change ni l'adresse, ni le destinataire, ni le téléphone enregistrés dans la commande existante. Toute correction de livraison liée à une commande en cours doit suivre un processus métier distinct, autorisé et tracé dans Sales/Delivery, et ne doit pas modifier rétroactivement le carnet ou le snapshot historique.
- **Sécurité et intégrité :** le serveur vérifie toujours la propriété de l'adresse lors de sa modification. Le snapshot de chaque commande reste immuable par rapport aux changements ultérieurs du carnet d'adresses. Les modifications sont auditées ; aucune mise à jour en cascade vers les commandes n'est autorisée.

### Décision Q289 — Coordonnées propres au destinataire de chaque adresse (VALIDÉE)
- **Question :** Chaque adresse de livraison doit-elle contenir ses propres coordonnées de réception ?
- **Réponse :** B — oui, chaque adresse possède un nom de destinataire et un numéro de téléphone propres, indépendants du profil client.
- **Décision :** chaque entrée du carnet d'adresses de livraison conserve le nom du destinataire et son numéro de téléphone. Ces coordonnées sont propres à l'adresse et peuvent être différentes du nom et du téléphone du compte client. Le client peut ainsi faire livrer une commande à un proche ou à une autre personne.
- **Cohérence métier :** Sales associe chaque adresse et ses coordonnées au client propriétaire ; Localization conserve les données structurées de localisation. Lors du checkout, le système copie dans le snapshot de livraison de la commande l'adresse complète, le nom du destinataire et son numéro de téléphone tels qu'ils étaient sélectionnés au moment de la commande. Les modifications ultérieures du carnet d'adresses ou du profil client ne réécrivent jamais ce snapshot.
- **Sécurité :** le serveur vérifie que l'adresse sélectionnée appartient au client authentifié. Les coordonnées du destinataire ne donnent aucun droit d'accès au compte client ; elles servent uniquement à l'exécution de la livraison et ne doivent pas être exposées à d'autres clients.
- **À préciser avant implémentation :** format/validation du téléphone, indicatif pays, caractères et longueur du nom du destinataire, ainsi que les règles de conservation et d'accès aux snapshots de livraison.

### Décision Q288 — Adresses de livraison enregistrées par le client (VALIDÉE)
- **Question :** Combien d'adresses de livraison un client peut-il enregistrer dans son espace personnel ?
- **Réponse :** B — plusieurs adresses, avec une adresse par défaut sélectionnée.
- **Décision :** un client peut enregistrer plusieurs adresses de livraison dans son espace personnel. Il désigne une adresse par défaut, proposée automatiquement lors d'une prochaine commande. Il peut choisir une autre adresse enregistrée au moment du checkout.
- **Responsabilités et cohérence :** Localization reste propriétaire des données structurées d'adresse ; Sales gère leur association au profil commercial du client, le choix de l'adresse par défaut et l'adresse retenue pour chaque commande. L'adresse choisie doit être figée dans les données de commande afin qu'une modification ultérieure du carnet d'adresses ne réécrive pas une commande passée. Identity ne duplique pas les adresses postales dans les données de sécurité du compte.
- **Sécurité et validation :** un client ne peut consulter, modifier, supprimer, définir par défaut ou sélectionner que ses propres adresses. Le serveur vérifie la propriété de l'adresse au checkout et empêche toute référence à l'adresse d'un autre client. La suppression ou modification d'une adresse déjà utilisée dans une commande ne doit pas altérer le snapshot de cette commande.
- **À préciser avant implémentation :** le nombre maximal d'adresses enregistrées, les champs obligatoires et les règles de validation des coordonnées. Aucune limite numérique n'est fixée par Q288.

### Décision Q287 — Durée de validité du lien de vérification e-mail client (VALIDÉE)
- **Question :** Combien de temps le lien envoyé au client doit-il rester valide ?
- **Réponse :** B — 24 heures.
- **Décision :** chaque lien de vérification e-mail émis pour l'inscription d'un client marketplace est valable pendant 24 heures à compter de son émission. À l'expiration, il ne peut plus vérifier l'adresse ni activer le compte.
- **Renouvellement :** le client peut demander un nouveau lien selon les limites anti-abus déjà définies. L'émission d'un nouveau lien invalide immédiatement tous les liens précédemment émis (Q7) ; un seul lien reste utilisable par compte. La limite de renvoi reste de 5 demandes par heure, suivies d'un blocage temporaire d'une heure (Q8).
- **Sécurité et cohérence :** expiration et validation contrôlées côté serveur, jetons sécurisés et à usage unique, invalidation après succès, réponses qui ne révèlent pas l'existence d'un compte, aucune journalisation de jeton ou de secret. La durée de 24 heures remplace explicitement l'ancienne durée de 60 minutes indiquée dans Q6 et Q7. L'activation automatique après vérification demeure limitée au parcours d'inscription client (Q286) et ne modifie pas les règles des comptes internes.

### Décision Q286 — Activation du compte client après vérification e-mail (VALIDÉE)
- **Question :** Quel parcours d'activation après inscription client ?
- **Réponse :** A — compte en attente, vérification e-mail, puis activation automatique.
- **Décision :** l'inscription crée un compte client avec le statut `pending`. Un lien de vérification sécurisé est envoyé à l'adresse déclarée. Après validation réussie du lien, le serveur marque l'adresse comme vérifiée et active automatiquement le compte client, sans intervention manuelle d'un administrateur.
- **Accès :** tant que l'adresse n'est pas vérifiée et que le compte n'est pas actif, aucune connexion à l'espace client ni action de commande n'est autorisée. La consultation publique de la marketplace reste possible sans compte, selon les règles d'accès du catalogue. Une fois actif et vérifié, le client peut se connecter et accéder à son espace.
- **Sécurité et cohérence :** jeton de vérification sécurisé, expirant et à usage unique ou invalidé après succès ; aucune donnée sensible dans les journaux ; protection contre abus/réémission et énumération de comptes ; activation strictement limitée au parcours d'inscription client, sans élargir les pouvoirs de réactivation des comptes internes. Les transitions sont contrôlées côté serveur et auditées.

### Décision Q285 — Application d'un nouveau seuil de stock (VALIDÉE)
- **Question :** Quand un seuil personnalisé est-il appliqué après modification ?
- **Réponse :** A — immédiatement après validation serveur.
- **Décision :** toute création ou modification du seuil prend effet immédiatement après validation côté serveur. Pas de programmation différée pour ce paramètre en V1.
- **Cohérence :** Products conserve le paramètre ; Catalog recalcule l'indicateur à partir de la disponibilité nette courante fournie par Inventory. Sales continue de revalider et réserver côté serveur. La modification n'altère ni le stock, ni les réservations, ni les commandes existantes. Autorisation cooperative_admin propriétaire, isolation et audit obligatoires.

### Parcours opérationnel directeur — Client → Coopérative → Transporteur (VALIDÉ)
1. **Client — Identity :** inscription avec compte en attente, vérification de l'e-mail puis activation automatique ; connexion, profil et adresses après activation.
2. **Marketplace — Catalog / Products :** consultation des coopératives, découverte des produits, prix et disponibilités.
3. **Panier et commande — Sales :** ajout de produits, modification des quantités, validation du panier et passage de commande ; panier multi-coopératives possible.
4. **Réception — Espace coopérative :** chaque coopérative reçoit sa sous-commande et consulte ses lignes, quantités et informations client autorisées.
5. **Confirmation — Sales + Inventory :** la coopérative accepte ou refuse les lignes, ajuste les quantités disponibles, puis confirme sa sous-commande ; le stock est réservé selon les règles métier validées.
6. **Préparation et remise — Delivery :** la coopérative prépare le colis, le déclare prêt et le remet au transporteur.
7. **Suivi — Delivery + Communication :** client et coopérative suivent les statuts d'expédition et reçoivent les notifications utiles.
- **Méthode de développement :** implémenter progressivement les capacités nécessaires à chaque étape dans cet ordre de parcours, avec audit préalable des fichiers et dépendances, tests ciblés puis tests de régression ; ne pas chercher à achever un domaine entier avant de livrer le parcours transversal.
- **Frontières :** Identity gère le compte client ; Catalog / Products les données commerciales exposées ; Sales le panier, la commande, les sous-commandes et leurs transitions ; Inventory le stock et les réservations ; Delivery la préparation, remise et expédition ; Communication les notifications. Chaque domaine reste propriétaire de ses données et expose des contrats explicites.

### Décision Q284 — Seuil personnalisé égal à zéro (VALIDÉE)
- **Question :** Le seuil personnalisé d'un produit peut-il être égal à zéro ?
- **Réponse :** A — oui ; zéro désactive l'indicateur « Presque épuisé » pour ce produit.
- **Décision :** le seuil personnalisé accepte zéro. Pour ce produit, tant que la disponibilité nette est strictement positive, Catalog affiche « Disponible » ; à zéro ou moins, il affiche « Indisponible ». Il n'affiche jamais « Presque épuisé » lorsque le seuil effectif est zéro.
- **Distinction importante :** l'absence de configuration utilise le seuil par défaut de 5 unités ; une valeur explicitement configurée à zéro est une désactivation volontaire et ne doit pas être confondue avec une valeur absente. Les valeurs négatives et décimales sont refusées.
- **Cohérence :** Products conserve le paramètre et distingue null (défaut 5) de 0 (désactivation) ; Inventory reste source de vérité de la disponibilité nette ; Catalog expose le statut sans quantité ; Sales revalide et réserve côté serveur. Modification réservée au cooperative_admin propriétaire, avec isolation et audit.

### Décision Q282 — Seuil « Presque épuisé » configurable par produit (VALIDÉE)
- **Question :** Comment définir le seuil de l'indicateur « Presque épuisé » ?
- **Réponse :** B — seuil configurable par produit par le `cooperative_admin`.
- **Décision :** chaque `cooperative_admin` peut définir et modifier, pour chaque produit de sa propre coopérative, un seuil bas entier positif ou nul exprimé en unités disponibles. Le statut « Presque épuisé » s'applique lorsque la quantité disponible nette est strictement positive et inférieure ou égale au seuil configuré ; « Indisponible » s'applique à zéro ou moins ; « Disponible » s'applique au-delà du seuil. Le seuil ne change jamais la quantité réelle, les réservations ou la possibilité de commander.
- **Valeur par défaut et validation :** si aucun seuil n'est configuré (valeur absente/null), appliquer 5 unités. Une valeur explicitement configurée à 0 désactive « Presque épuisé » pour ce produit. Le serveur accepte uniquement des entiers positifs ou zéro ; pas de seuil négatif, décimal ou transmis comme vérité depuis le navigateur. Le serveur valide le type, les bornes et la cohérence de la valeur ; pas de seuil négatif, décimal ou transmis comme vérité depuis le navigateur.
- **Cohérence métier et sécurité :** Products possède le paramètre commercial par produit ; Inventory reste source de vérité de la quantité disponible ; Catalog calcule/expose uniquement l'indicateur public ; Sales revalide la disponibilité réelle au panier/checkout. Le paramétrage est réservé au `cooperative_admin` de la coopérative propriétaire, avec isolation stricte et journalisation ; les superviseurs ne modifient pas directement ce paramètre commercial dans V1.

### Décision Q281 — Indicateur public de disponibilité du stock (VALIDÉE)
- **Question :** Quelle information de stock afficher sur la marketplace publique ?
- **Réponse :** C — afficher un indicateur commercial « Disponible », « Presque épuisé » ou « Indisponible », sans quantité exacte.
- **Décision :** la marketplace publique n'affiche jamais la quantité exacte du stock physique ni la quantité exacte disponible. Elle présente un état commercial calculé à partir des données fiables d'Inventory : « Indisponible » lorsque la quantité disponible nette est nulle ou négative ; « Presque épuisé » lorsque la disponibilité positive atteint le seuil bas défini ; « Disponible » au-delà de ce seuil. La définition du seuil bas doit être centralisée et configurable/validée avant implémentation, sans être inventée par l'interface.
- **Cohérence métier :** Inventory reste propriétaire du stock physique, des réservations et de la quantité disponible ; Catalog expose uniquement l'indicateur commercial ; Sales revalide la disponibilité réelle et réserve côté serveur au panier/checkout. Les espaces internes autorisés peuvent disposer d'informations de stock plus détaillées selon leurs permissions, sans les exposer au client public.
- **Contrôles :** calcul serveur, seuil cohérent et centralisé, actualisation fiable, absence de fuite de quantités internes, isolation par coopérative, audit des mouvements et validation serveur obligatoires.

### Décision Q280 — Retour automatique de disponibilité après réapprovisionnement (VALIDÉE)
- **Question :** Lorsqu'une variante redevient disponible, comment rétablir son affichage dans la marketplace ?
- **Réponse :** A — automatiquement dès qu'Inventory confirme une quantité disponible après prise en compte des réservations.
- **Décision :** la disponibilité commerciale est rétablie automatiquement dès qu'Inventory confirme côté serveur que la quantité disponible de la variante est strictement positive, après déduction des réservations et autres engagements applicables. Aucun contrôle ou clic manuel du `cooperative_admin`, du `super_admin` ou du `super_admin_root` n'est requis pour republier la disponibilité.
- **Cohérence métier :** Inventory reste l'unique source de vérité du stock physique, des réservations et de la quantité disponible ; Catalog reflète l'état commercial calculé sans exposer le stock physique interne ; Sales revalide la disponibilité côté serveur au panier et au checkout et réserve le stock selon ses contrats avec Inventory. Le retour de disponibilité ne modifie ni le prix, ni la promotion, ni ses versions.
- **Contrôles :** mise à jour fiable et cohérente après mouvements de stock/libération de réservation, traitement idempotent des événements, prévention des états obsolètes, isolation par coopérative, audit des opérations de stock et validation serveur obligatoires.

### Décision Q279 — Promotion lorsque toutes les variantes sont indisponibles (VALIDÉE)
- **Question :** Que faire lorsqu'un produit en promotion n'a plus aucune variante disponible ?
- **Réponse :** B — conserver la promotion visible, afficher clairement « Indisponible » et empêcher toute commande.
- **Décision :** la promotion et ses informations commerciales restent visibles pendant leur période de validité, même si toutes les variantes sont temporairement indisponibles. La fiche et la carte indiquent explicitement l'indisponibilité ; aucun bouton ou parcours ne doit permettre de commander une variante indisponible. Ne pas afficher une disponibilité ou un prix d'appel laissant croire qu'une variante peut être achetée.
- **Cohérence métier :** Inventory est la source de vérité de disponibilité ; Catalog expose l'état d'indisponibilité sans révéler le stock physique interne ; Sales revérifie côté serveur la disponibilité de la variante sélectionnée au panier et au checkout et refuse l'opération si elle est indisponible. Le retour du stock rétablit l'affichage de disponibilité selon les règles Inventory, sans modifier la promotion ni ses versions.
- **Contrôles :** absence de confiance dans l'état transmis par le navigateur, contrôle de concurrence/réservation dans Sales/Inventory, isolation coopérative et journalisation des opérations pertinentes. Products reste propriétaire des promotions ; Catalog de leur présentation ; Inventory de la disponibilité ; Sales de l'acceptation de commande.

### Décision Q278 — Réactivation d'une promotion annulée (VALIDÉE)
- **Question :** Une promotion annulée peut-elle être réactivée ?
- **Réponse :** A — oui, en créant une nouvelle version, avec une nouvelle période et un historique distinct.
- **Décision :** une promotion annulée ne reprend jamais vie en réactivant une ancienne version. Le `cooperative_admin` peut relancer l'opération commerciale en créant une nouvelle version après annulation, avec une nouvelle période de validité et de nouveaux paramètres explicitement validés. La version annulée demeure clôturée et inchangée dans l'historique ; la nouvelle version est reliée à la même promotion logique et porte sa propre identité/version, son auteur, ses dates et ses paramètres.
- **Cohérence métier :** Catalog expose la nouvelle version uniquement pendant sa période applicable. Sales revalide et fige les prix applicables aux nouvelles opérations ; aucun panier/commande déjà enregistré n'est recalculé. Les anciennes commandes et snapshots restent liés à la version qui était applicable lors de leur enregistrement.
- **Contrôles :** autorisation serveur du `cooperative_admin` sur sa coopérative, validation des dates et paramètres, absence de chevauchement incohérent des versions, journalisation et conservation de l'historique. Products possède les versions ; Catalog expose la version courante ; Sales conserve les snapshots commerciaux.

### Décision Q277 — Modification d'une annulation planifiée (VALIDÉE)
- **Question :** Une annulation planifiée peut-elle être modifiée avant son effet ?
- **Réponse :** A — oui, librement, en remplaçant la date prévue et en conservant l'ancienne programmation dans l'historique.
- **Décision :** le `cooperative_admin` peut modifier la date et l'heure d'une annulation planifiée tant qu'elle n'a pas pris effet. La nouvelle programmation remplace la programmation courante ; la promotion reste applicable jusqu'au nouvel instant d'effet. Chaque programmation antérieure est conservée avec auteur, horodatages, ancienne et nouvelle échéances et motif éventuel. Une demande déjà exécutée ne peut pas être modifiée rétroactivement.
- **Contrôles :** validation serveur des dates futures et de la cohérence chronologique, isolation coopérative, gestion fiable des échéances et des courses entre modification et exécution, opérations atomiques/idempotentes et journalisation. Catalog suit uniquement l'échéance courante applicable ; Sales ne réécrit jamais les snapshots historiques.
- **Responsabilités :** Products gère les programmations et leur historique ; Catalog applique l'état selon l'échéance courante ; Sales conserve les prix des paniers/commandes enregistrés.

### Décision Q276 — Prise d'effet de l'annulation d'une promotion active (VALIDÉE)
- **Question :** Quand l'annulation d'une promotion déjà active prend-elle effet ?
- **Réponse :** C — la coopérative choisit entre annulation immédiate et annulation planifiée.
- **Décision :** le `cooperative_admin` peut annuler sa promotion immédiatement après validation serveur ou programmer une date/heure future. Tant que l'annulation planifiée n'a pas pris effet, la version promotionnelle actuellement applicable continue de s'appliquer et d'être exposée par Catalog. À l'instant d'effet, Catalog cesse de l'exposer ; aucune nouvelle application promotionnelle n'est autorisée ensuite.
- **Historique et cohérence :** Products conserve la demande d'annulation, le mode choisi, l'auteur, les dates de création et d'effet, ainsi que les événements associés. Une annulation planifiée modifiée avant son effet doit conserver la trace de la programmation remplacée. Sales ne modifie jamais rétroactivement les prix figés dans les paniers/commandes déjà enregistrés. Contrôles serveur, isolation par coopérative, validation chronologique, traitement fiable à l'heure prévue et journalisation obligatoires.
- **Responsabilités :** Products gère l'état, la programmation et l'historique ; Catalog détermine l'exposition selon l'instant courant ; Sales conserve les snapshots commerciaux existants.

### Décision Q275 — Suppression d'une promotion (VALIDÉE)
- **Question :** Une promotion créée peut-elle être supprimée physiquement ?
- **Réponse :** B — aucune suppression physique ; utiliser une annulation logique qui conserve l'historique.
- **Décision :** une promotion ne peut pas être effacée physiquement du système, qu'elle soit planifiée, active ou terminée. La coopérative peut demander son annulation/désactivation selon ses droits ; l'opération clôt son applicabilité à partir de son instant d'effet, sans effacer les versions, paramètres, dates, auteurs, événements ni références déjà consommées. Les versions remplacées, annulées et terminées restent consultables dans l'historique d'audit. Les commandes et snapshots Sales restent inchangés.
- **Frontières et contrôles :** Products possède l'état d'annulation et l'historique ; Catalog cesse d'exposer la promotion à compter de son annulation effective ; Sales conserve les prix et promotions figés dans les paniers/commandes déjà enregistrés. L'opération est autorisée côté serveur, isolée par coopérative et journalisée ; aucune suppression physique en cascade des références métier.

### Décision Q274 — Nouvelle modification d'une version promotionnelle planifiée (VALIDÉE)
- **Question :** Que faire si une version promotionnelle est déjà planifiée et que la coopérative souhaite encore la modifier avant son entrée en vigueur ?
- **Réponse :** B — remplacer la version planifiée, tout en conservant sa trace dans l'historique.
- **Décision :** tant qu'une version future n'est pas entrée en vigueur, une nouvelle modification remplace la version planifiée courante au lieu de créer une succession de versions futures actives. La version planifiée remplacée reste conservée dans l'historique comme version remplacée/annulée, avec ses paramètres, son auteur et ses dates de création/remplacement. La nouvelle version planifiée reprend une entrée d'effet future validée côté serveur. La version actuellement en vigueur reste applicable jusqu'à la date d'effet de la nouvelle version retenue.
- **Frontières et contrôles :** Products possède les versions et leur historique ; Catalog expose uniquement la version applicable à l'instant considéré ; Sales revalide et fige le prix au panier/checkout et ne réécrit aucune commande déjà enregistrée. Vérifier les droits du cooperative_admin, l'isolation de sa coopérative, la cohérence chronologique et journaliser le remplacement.

### Décision Q273 — Entrée en vigueur d'une nouvelle version de promotion (VALIDÉE)
- **Question :** Quand une nouvelle version d'une promotion active devient-elle applicable ?
- **Réponse :** C — la coopérative choisit entre application immédiate et application planifiée.
- **Décision :** lors de la modification d'une promotion active, le `cooperative_admin` peut choisir une entrée en vigueur immédiate après validation serveur ou programmer une date/heure future. La nouvelle version est conservée et traçable dès sa création, mais Catalog n'expose comme applicable que la version dont la période d'effet est atteinte. Jusqu'à cette échéance, l'ancienne version reste applicable. Les versions et leurs bornes d'effet doivent permettre de reconstituer sans ambiguïté les paramètres applicables à un instant donné. Les paniers/commandes déjà enregistrés conservent leurs montants et snapshots conformément aux règles de Sales ; aucune réécriture rétroactive.
- **Contrôles :** validation serveur des droits, de l'isolation coopérative, de la cohérence chronologique des versions et des périodes de promotion ; journalisation de l'auteur, de la création et de la date d'effet. Une date planifiée ne doit pas provoquer d'activation anticipée.
- **Frontières :** Products gère les versions et leur planification ; Catalog expose la version applicable à l'instant courant ; Sales revalide et fige le prix effectivement appliqué.

### Décision Q272 — Versionnement des modifications d'une promotion active (VALIDÉE)
- **Question :** Que se passe-t-il lorsqu'une coopérative modifie le taux ou les dates d'une promotion déjà active ?
- **Réponse :** B — toute modification crée une nouvelle version ; l'ancienne version est conservée dans l'historique.
- **Décision :** une modification du taux, des dates ou d'un autre paramètre commercial d'une promotion active ne remplace jamais silencieusement les données historiques. Products crée une nouvelle version traçable et conserve la version précédente, avec les informations d'auteur et de date de modification. L'historique doit permettre de reconstituer les paramètres de chaque version. Sales conserve les prix et paramètres effectivement figés dans les paniers/commandes selon ses règles de snapshot ; une modification ne réécrit pas les commandes déjà enregistrées.
- **Frontières :** Products possède les versions et leur historique ; Catalog expose la version applicable ; Sales revalide et fige le prix effectivement appliqué. Les contrôles serveur, l'isolation coopérative et la journalisation restent obligatoires.

### Décision Q271 — Publication d'une promotion (VALIDÉE)
- **Question :** Une promotion créée par une coopérative doit-elle être publiée immédiatement ou soumise à validation ?
- **Réponse :** A — publication immédiate dès l'enregistrement si toutes les validations sont respectées.
- **Décision :** aucune approbation préalable de `super_admin` ou `super_admin_root` n'est requise. Après validation serveur des paramètres (taux, dates, prix calculé, produit et variantes concernés, droits du `cooperative_admin` et périmètre coopératif), la promotion est automatiquement active/publicable lorsque sa période commence. Une promotion planifiée reste inactive jusqu'à sa date de début. Les actions sont journalisées ; les rôles de supervision conservent leurs droits de contrôle et peuvent appliquer les mécanismes de suspension autorisés, sans modifier les paramètres commerciaux à la place de la coopérative.
- **Frontières :** Products valide et conserve la promotion ; Catalog ne l'expose comme active que pendant sa période de validité ; Sales revalide le prix au panier/checkout et le fige dans la commande.


### Décision Q320 — Notifications de sécurité du compte client (VALIDÉE)
- **Question :** Quels événements sensibles du compte client doivent déclencher une notification ?
- **Réponse :** A — envoyer un e-mail pour chaque événement sensible, avec la date et les informations utiles sur l'appareil lorsqu'elles sont disponibles.
- **Décision :** envoyer une notification de sécurité par e-mail lors de chaque événement sensible couvert par le parcours client, notamment une nouvelle connexion, un changement de mot de passe, la révocation d'un appareil/session et un changement d'adresse e-mail.
- **Conséquences :** les messages indiquent l'événement et ses éléments contextuels utiles (date/heure et informations d'appareil disponibles), sans exposer de mot de passe, token, identifiant de session, code, lien secret ou autre secret d'authentification. Les notifications sont déclenchées à partir d'événements serveur fiables, ne remplacent pas les contrôles de sécurité et doivent être auditées sans journaliser de contenu sensible. Le canal validé pour V1 est l'e-mail.
- **Statut :** VALIDÉ — premier release.


### Décision Q321 — Notification des tentatives de connexion échouées (VALIDÉE)
- **Question :** Le client doit-il être averti par e-mail lorsqu'une tentative de connexion échoue sur son compte ?
- **Réponse :** C — aucune notification e-mail pour les tentatives échouées ; celles-ci sont enregistrées dans les journaux de sécurité.
- **Décision :** les échecs d'authentification du compte client sont consignés côté serveur dans les journaux de sécurité, sans envoyer d'e-mail au client pour chaque échec ni pour les échecs répétés dans le périmètre V1.
- **Conséquences :** la journalisation doit permettre l'analyse de sécurité et respecter les règles anti-abus Identity. Elle ne doit jamais enregistrer de mot de passe, token, code, secret d'authentification ou contenu sensible. Les contrôles de limitation et de blocage restent actifs indépendamment de l'envoi de notifications. Cette décision concerne les tentatives échouées et ne supprime pas les notifications des événements sensibles réussis prévues par Q320.
- **Statut :** VALIDÉ — premier release.


### Décision Q322 — Blocage temporaire après échecs de connexion (VALIDÉE)
- **Question :** Faut-il appliquer un blocage temporaire du compte client après plusieurs tentatives de connexion échouées ?
- **Réponse :** A — oui, appliquer le blocage temporaire selon les règles Identity existantes.
- **Décision :** le compte client est soumis au mécanisme de blocage temporaire et aux seuils de sécurité déjà définis et gérés par Identity. Le parcours client ne crée ni seuil parallèle, ni durée supplémentaire, ni mécanisme de contournement.
- **Conséquences :** les limitations cumulatives du login client définies en Q317 et les protections Identity restent appliquées côté serveur. Le blocage temporaire doit être cohérent avec l'état de sécurité du compte, expirer selon la politique Identity existante et être levé uniquement selon les règles prévues par cette politique. Les échecs restent journalisés sans mot de passe, token, code ou secret ; aucune notification e-mail n'est envoyée pour les échecs (Q321). Les notifications Q320 des événements sensibles réussis restent applicables. Aucune durée ou seuil nouveau n'est inventé par Q322.
- **Statut :** VALIDÉ — premier release, paramètres hérités d'Identity.


### Décision Q323 — Sessions client simultanées sur plusieurs appareils (VALIDÉE)
- **Question :** Le client peut-il rester connecté simultanément sur plusieurs appareils ?
- **Réponse :** A — oui, plusieurs sessions simultanées, chacune gérable et révocable individuellement.
- **Décision :** autoriser plusieurs sessions web actives pour un même compte client, notamment depuis un ordinateur, un téléphone ou une tablette. Une nouvelle connexion ne révoque pas les sessions déjà ouvertes.
- **Conséquences :** chaque session doit être identifiable et révocable individuellement depuis l'espace sécurité du client, conformément au modèle déjà validé en Q319. Le client peut également révoquer les autres sessions selon les capacités prévues par Q319. Les contrôles de propriété sont vérifiés côté serveur ; la révocation invalide réellement la session concernée et sa persistance éventuelle. Les révocations globales déclenchées par les événements de sécurité, notamment la réinitialisation du mot de passe Q316, continuent de s'appliquer à toutes les sessions et aux tokens API. Aucun plafond arbitraire de sessions n'est ajouté par Q323.
- **Statut :** VALIDÉ — premier release.


### Décision Q324 — Durée d'inactivité des sessions client (VALIDÉE)
- **Question :** Quelle règle appliquer à la déconnexion automatique d'une session client inactive ?
- **Réponse :** D — utiliser la politique de durée d'inactivité déjà définie par Identity.
- **Décision :** le parcours client réutilise la politique centralisée d'Identity pour déterminer la durée d'inactivité et la déconnexion automatique. Il ne définit aucune durée propre à la marketplace.
- **Conséquences :** les sessions simultanées multi-appareils autorisées par Q323 restent chacune soumises à cette politique centralisée. La durée maximale de session, le renouvellement éventuel et les mécanismes de persistance restent ceux d'Identity ; aucune valeur n'est inventée ici. La révocation individuelle et globale conserve les effets définis en Q319 et Q316.
- **Statut :** VALIDÉ — premier release, politique héritée d'Identity.


### Décision Q325 — Déconnexion manuelle du client (VALIDÉE)
- **Question :** Comment le client doit-il pouvoir se déconnecter de ses sessions ?
- **Réponse :** B — déconnexion de l'appareil actuel avec une option supplémentaire « Déconnecter tous les appareils ».
- **Décision :** l'action standard « Se déconnecter » invalide uniquement la session de l'appareil courant. Une action distincte, explicite et protégée permet au client de révoquer toutes ses autres sessions/appareils.
- **Conséquences :** respecter le modèle multi-sessions Q323 et la révocation individuelle Q319. La révocation globale doit invalider les sessions web actives des autres appareils et leur persistance éventuelle ; le comportement de la session courante lors de cette action suit la règle déjà validée en Q319 (elle est conservée). Les tokens API ne sont pas inclus dans cette action, sauf si une règle Identity existante les associe explicitement ; les révocations globales de sécurité Q316 continuent, elles, à révoquer toutes les sessions et tous les tokens API. Vérifier la propriété côté serveur et journaliser l'action sans secrets.
- **Statut :** VALIDÉ — premier release.


### Décision Q326 — Confirmation avant révocation des autres sessions (VALIDÉE)
- **Question :** Faut-il demander une confirmation avant de déconnecter les autres appareils du client ?
- **Réponse :** A — oui, afficher une confirmation explicite avant la révocation.
- **Décision :** l'action « Déconnecter tous les appareils » présente une étape de confirmation claire avant son exécution. La révocation ne démarre qu'après confirmation explicite du client.
- **Conséquences :** annuler ou fermer la confirmation ne modifie aucune session. Après confirmation, appliquer exactement la portée Q325 : révoquer les autres sessions et conserver la session courante. Ne pas demander le mot de passe à nouveau dans le périmètre de cette décision ; les contrôles serveur et la journalisation sans secrets restent obligatoires.
- **Statut :** VALIDÉ — premier release.


### Décision Q327 — Informations affichées pour les sessions client (VALIDÉE)
- **Question :** Quelles informations afficher pour chaque session active dans l'espace sécurité du client ?
- **Réponse :** B — date de connexion, dernière activité, navigateur, système d'exploitation et indication de l'appareil actuellement utilisé.
- **Décision :** chaque session active présente les informations techniques utiles disponibles : date de connexion, dernière activité, navigateur et système d'exploitation. La session correspondant à l'appareil utilisé pour consulter l'espace est clairement identifiée.
- **Conséquences :** afficher uniquement les données réellement disponibles et fiables ; ne pas inventer une identification matérielle ou un modèle d'appareil si les informations techniques ne le permettent pas. L'adresse IP et la localisation approximative ne sont pas affichées dans V1. Ces informations servent à reconnaître les sessions et à exercer les actions de révocation Q319/Q325/Q326, sans exposer de données de connexion supplémentaires.
- **Statut :** VALIDÉ — premier release.


### Décision Q328 — Nouvelle connexion depuis un appareil non reconnu (VALIDÉE — modalités complémentaires à préciser)
- **Question :** Quelle notification déclencher lors d'une connexion depuis un appareil non reconnu ?
- **Réponse :** règle personnalisée précisée par l'utilisateur.
- **Décision :** autoriser la nouvelle session à se connecter immédiatement, sans blocage préventif. En parallèle, envoyer au client un e-mail de sécurité lui permettant de confirmer que la connexion est bien la sienne. Si le client ne reconnaît pas cette connexion, l'e-mail doit proposer un lien/action sécurisé permettant de révoquer précisément la nouvelle session concernée et de bloquer son accès.
- **Notification dans l'espace client :** si le client dispose déjà d'une session active sur Mahline, afficher également une notification de sécurité dans son espace. Si aucune session client n'est active, l'e-mail constitue le canal de notification disponible. L'e-mail de vérification de nouvel appareil est envoyé en parallèle selon cette règle, sans remplacer les notifications des événements sensibles prévues par Q320.
- **Sécurité :** les actions de confirmation et de révocation doivent être liées à la session nouvellement créée, protégées par des preuves sécurisées, à usage unique et à durée limitée à définir. La révocation ne doit pas déconnecter les autres appareils du client. La session suspecte doit perdre son accès dès la révocation. Ne jamais exposer ni journaliser le lien secret, token ou autre secret d'authentification.
- **À préciser avant développement :** durée de validité du lien, traitement d'une absence de réponse, éventuelle confirmation automatique de confiance de l'appareil et comportement exact après confirmation. Aucun délai n'est fixé par Q328 à ce stade.
- **Statut :** principe validé — détails de cycle de vie à compléter avant implémentation.


### Décision Q329 — Délai de confirmation d'un nouvel appareil (VALIDÉE)
- **Question :** Combien de temps le client dispose-t-il pour confirmer ou signaler une connexion depuis un appareil non reconnu ?
- **Réponse :** C — 1 heure.
- **Décision :** le lien sécurisé envoyé par e-mail pour confirmer la légitimité de la nouvelle connexion ou révoquer la session suspecte est valable pendant une heure à compter de son émission.
- **Conséquences :**
  - Le client dispose d'une heure pour confirmer qu'il est à l'origine de la connexion ou signaler qu'il ne la reconnaît pas et révoquer la nouvelle session concernée.
  - Le lien est individuel, à usage unique, lié à la session nouvellement créée et protégé contre la divulgation ou la réutilisation ; seule sa preuve sécurisée est conservée côté serveur, jamais le secret en clair dans les journaux.
  - À l'expiration, le lien ne permet plus aucune action. Le comportement de la session en l'absence de réponse, ainsi que le traitement d'une demande de nouveau lien, restent à définir séparément avant développement ; aucune révocation ou validation automatique n'est supposée par Q329.
  - La notification dans l'espace client, si une autre session est déjà active, reste applicable. La révocation initiée par le client ne vise que la nouvelle session suspecte et ne déconnecte pas ses autres appareils.
- **Statut :** VALIDÉ — premier release ; traitement de l'absence de réponse à préciser.


### Décision Q330 — Absence de réponse à l'alerte de nouvel appareil (VALIDÉE)
- **Question :** Que faire si le client ne confirme ni ne signale la nouvelle connexion dans l'heure ?
- **Réponse :** A — la nouvelle session reste active ; seul le lien expire.
- **Décision :** si le client ne répond pas dans l'heure définie par Q329, la session du nouvel appareil reste active. Seul le lien de confirmation/révocation devient inutilisable.
- **Conséquences :**
  - Aucune révocation ni suspension automatique de la nouvelle session n'est déclenchée par l'absence de réponse.
  - Les autres sessions du client restent inchangées.
  - Si le client ne reconnaît finalement pas la connexion, il peut ouvrir son espace sécurité, sélectionner l'appareil/session concerné(e), choisir « Signaler comme non reconnu », puis confirmer explicitement la révocation de cette seule session. Cette action reste soumise à une vérification serveur stricte de propriété et de correspondance de la session.
  - Les événements de connexion, expiration du lien et éventuelle révocation ultérieure sont audités sans enregistrer le lien, token ou secret d'authentification.
- **Statut :** VALIDÉ — premier release.



### Décision Q332 — Confirmation avant révocation manuelle d'un appareil (VALIDÉE)
- **Question :** Quel niveau de confirmation exiger lorsque le client signale un appareil non reconnu depuis son espace sécurité ?
- **Réponse :** A — confirmation simple.
- **Décision :** après sélection de l'appareil/session et clic sur « Signaler comme non reconnu », afficher une confirmation explicite de type « Voulez-vous vraiment révoquer cet appareil ? ». La révocation n'est exécutée qu'après confirmation.
- **Conséquences :**
  - L'annulation ou la fermeture de la confirmation ne modifie aucune session.
  - Aucun nouveau mot de passe n'est demandé pour cette action en V1.
  - La révocation reste limitée à la session sélectionnée ; les autres sessions du client restent actives.
  - Le serveur vérifie la propriété du compte et l'identifiant exact de session avant l'action. L'événement est audité sans secret d'authentification.
- **Statut :** VALIDÉ — premier release.



### Décision Q333 — Notification après révocation d'un appareil suspect (VALIDÉE)
- **Question :** Comment informer le client après la révocation manuelle d'un appareil non reconnu ?
- **Réponse :** C — e-mail et notification dans l'espace client.
- **Décision :** après confirmation et révocation effective de la session suspecte, MAHLINE envoie un e-mail de sécurité au client et affiche une notification dans son espace client.
- **Conséquences :**
  - Les deux notifications sont déclenchées uniquement après confirmation de la révocation côté serveur ; une annulation ou un échec de révocation ne doit pas annoncer une action réussie.
  - Le message indique qu'une session/appareil a été révoqué, avec date/heure et informations d'appareil fiables disponibles.
  - Ne jamais inclure de mot de passe, token, identifiant de session exploitable, lien secret ou autre secret d'authentification.
  - L'événement et l'envoi des notifications sont traçables sans enregistrer de secret ; les autres sessions restent actives.
- **Statut :** VALIDÉ — premier release.



### Décision Q334 — Mémorisation d'un appareil reconnu (VALIDÉE)
- **Question :** Pendant combien de temps MAHLINE doit-il mémoriser qu'un appareil a été reconnu par le client ?
- **Réponse :** C — jusqu'à révocation par le client.
- **Décision :** après reconnaissance/confirmation explicite d'un nouvel appareil, MAHLINE conserve son statut d'appareil reconnu sans échéance calendaire fixe, jusqu'à ce que le client le révoque depuis son espace sécurité.
- **Conséquences :**
  - Le client peut consulter les appareils reconnus et révoquer individuellement cette confiance depuis son espace sécurité.
  - Après révocation, l'appareil ne bénéficie plus de la reconnaissance persistante ; une connexion ultérieure repasse par le contrôle d'un nouvel appareil non reconnu.
  - La reconnaissance doit reposer sur un mécanisme serveur sécurisé et révocable, pas uniquement sur une adresse IP, un navigateur déclaré ou un User-Agent facilement falsifiable.
  - La révocation de la confiance d'un appareil doit être distinguée de la révocation de sa session active : le parcours doit permettre de retirer la confiance et de révoquer la session associée, sans affecter les autres appareils.
  - Les preuves de reconnaissance sont protégées, non exposées dans l'interface et jamais journalisées en clair ; les événements de reconnaissance/révocation sont audités sans secret.
- **Statut :** VALIDÉ — premier release ; mécanisme technique exact de reconnaissance à auditer et définir avant développement.



### Décision Q335 — Sessions actives lors de la révocation d'un appareil reconnu (VALIDÉE)
- **Question :** Que faire des sessions actives lorsqu'un client révoque la reconnaissance d'un appareil ?
- **Réponse :** B — révoquer immédiatement la confiance et toutes les sessions actives de cet appareil.
- **Décision :** la révocation d'un appareil reconnu invalide immédiatement sa preuve de confiance et révoque toutes les sessions web actives qui lui sont effectivement associées.
- **Conséquences :**
  - Toutes les sessions actives liées à cet appareil perdent immédiatement leur accès authentifié ; la révocation de la confiance seule ne laisse pas une session de cet appareil utilisable.
  - Les sessions des autres appareils du client restent actives.
  - La révocation est ciblée sur l'appareil et ses sessions associées ; elle ne révoque pas les API tokens ni les sessions d'autres appareils, sauf règle de sécurité globale déjà définie séparément.
  - Le lien fiable entre preuve d'appareil et sessions doit être vérifié côté serveur ; ne pas déduire cette association d'une simple IP ou d'un User-Agent.
  - Auditer l'acteur, l'appareil concerné, les sessions révoquées, l'horodatage et le motif sans enregistrer de preuve secrète ni de token.
- **Statut :** VALIDÉ — premier release ; association technique appareil/session à auditer avant développement.



### Décision Q336 — Nombre maximal d'appareils reconnus (VALIDÉE)
- **Question :** Quel nombre maximal d'appareils un client peut-il mémoriser comme reconnus ?
- **Réponse :** B — maximum 5 appareils reconnus.
- **Décision :** chaque compte client peut avoir au maximum cinq appareils bénéficiant simultanément d'une reconnaissance persistante.
- **Conséquences :**
  - Le serveur applique cette limite lors de toute nouvelle reconnaissance ; le contrôle ne doit pas reposer uniquement sur l'interface.
  - Les appareils déjà reconnus restent soumis à la révocation individuelle décidée par le client (Q334-Q335).
  - Le comportement lorsqu'un client tente de reconnaître un sixième appareil — notamment le choix de l'appareil à remplacer ou la demande de révocation préalable — reste à définir avant développement ; aucun appareil existant ne doit être supprimé silencieusement.
  - La limite concerne les appareils reconnus, pas le nombre de sessions actives ; les règles multi-session restent distinctes.
- **Statut :** VALIDÉ — premier release ; parcours de dépassement de limite à préciser avant développement.



### Décision Q337 — Reconnaissance d'un sixième appareil (VALIDÉE)
- **Question :** Que faire lorsqu'un client veut reconnaître un sixième appareil alors que cinq appareils sont déjà reconnus ?
- **Réponse :** B — afficher les cinq appareils et laisser le client choisir celui à remplacer ; confiance et sessions révoquées après confirmation.
- **Décision :** présenter au client la liste des cinq appareils actuellement reconnus et lui permettre d'en sélectionner un à remplacer avant de reconnaître le nouvel appareil.
- **Conséquences :**
  - Le remplacement exige une sélection explicite et une confirmation claire du client.
  - Après confirmation, révoquer la confiance de l'appareil choisi et toutes ses sessions actives associées conformément à Q335, puis enregistrer le nouvel appareil comme reconnu.
  - Les quatre autres appareils reconnus et leurs sessions restent inchangés.
  - En cas d'annulation, aucune confiance ni session existante n'est modifiée et le nouvel appareil n'est pas reconnu.
  - Le serveur vérifie la limite et exécute le remplacement de façon atomique/idempotente ; aucune suppression silencieuse ni dépassement temporaire durable de la limite.
- **Statut :** VALIDÉ — premier release ; interface et transaction serveur à concevoir lors de l'implémentation.



## 6. Historique des mises à jour

### Version 4.24 — 14 novembre 2026
- Renforcement de l'isolation client : middleware dédié refuse l'accès à l'espace customer aux comptes portant aussi un rôle interne.
- Test feature ajouté pour l'exclusion des comptes multi-rôles ; exécution des tests toujours à effectuer.

### Version 4.23 — 14 novembre 2026
- Première tranche du profil client : consultation et modification sécurisées du prénom, nom et téléphone dans l'espace client isolé.
- Routes dédiées au rôle customer, validation serveur, mise à jour transactionnelle et audit des anciennes/nouvelles valeurs ; tests feature ajoutés mais non exécutés.
- E-mail non modifiable depuis ce formulaire (parcours de changement sécurisé à développer) ; photo de profil reportée à l'intégration Media (limite validée : 2 Mo). Aucun merge.


### Version 4.22 — 14 novembre 2026
- Enregistrement de Q337 : le client choisit explicitement lequel de ses cinq appareils reconnus remplacer pour en reconnaître un sixième.
- Après confirmation, l'appareil choisi et ses sessions web associées sont révoqués selon Q335 ; les autres appareils restent inchangés. Annulation sans effet. Aucun code modifié dans cette mise à jour documentaire ; aucune fusion.

### Version 4.21 — 14 novembre 2026
- Première tranche Client — Identity ajoutée : rôle `customer` distinct, inscription pending, vérification e-mail à usage unique 24 h, renvoi limité à 5 demandes/heure puis blocage d'une heure, routes et espace client séparés.
- Session client par défaut limitée à la durée du navigateur et expiration d'inactivité alignée sur Identity (60 min). Le « Se souvenir de moi » individuel reste à implémenter.
- Tests feature ajoutés mais non exécutés dans cet environnement. Le mécanisme de confiance des appareils clients (Q328-Q337) reste à auditer/implémenter ; aucun merge.

### Version 4.10 — 14 novembre 2026
- Enregistrement de Q329 : délai d'une heure pour confirmer ou signaler une connexion depuis un nouvel appareil non reconnu ; lien sécurisé à usage unique et lié à la nouvelle session.
- À expiration, le lien devient inutilisable ; le comportement de la session sans réponse reste à définir, sans révocation/validation automatique supposée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.09 — 14 novembre 2026
- Enregistrement de Q328 : connexion immédiate d'un nouvel appareil non reconnu, avec e-mail simultané de confirmation ou de révocation ciblée ; notification dans l'espace si une session client existe déjà.
- Révocation limitée à la nouvelle session suspecte ; durée du lien, absence de réponse et confiance durable de l'appareil restent à préciser.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.08 — 14 novembre 2026
- Enregistrement de Q327 : affichage de la date de connexion, dernière activité, navigateur, système d'exploitation et repérage de la session courante.
- Adresse IP et localisation approximative exclues de l'interface client V1 ; aucune caractéristique d'appareil non disponible n'est inventée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.07 — 14 novembre 2026
- Enregistrement de Q326 : confirmation explicite obligatoire avant de révoquer les autres sessions client.
- Annulation sans effet ; après confirmation, portée Q325 conservée (autres sessions révoquées, session courante conservée).
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.06 — 14 novembre 2026
- Enregistrement de Q325 : déconnexion courante limitée à l'appareil actuel, avec option distincte de révocation de toutes les autres sessions.
- Conservation de la session courante pour l'action « Déconnecter tous les appareils », conformément à Q319 ; révocation globale de sécurité Q316 inchangée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.05 — 14 novembre 2026
- Enregistrement de Q324 : durée d'inactivité et déconnexion automatique des sessions client héritées de la politique centralisée Identity.
- Aucune durée propre à la marketplace ; sessions multi-appareils et révocations Q316/Q319 inchangées.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.04 — 14 novembre 2026
- Enregistrement de Q323 : sessions web simultanées autorisées sur plusieurs appareils, sans déconnexion automatique des sessions existantes.
- Gestion et révocation individuelle des sessions dans l'espace sécurité client, en cohérence avec Q319 et les révocations globales Q316.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.03 — 14 novembre 2026
- Enregistrement de Q322 : blocage temporaire du compte client après les échecs selon les seuils, durées et règles Identity existants.
- Aucun seuil ni délai parallèle créé ; limites Q317, journalisation sans secrets et absence d'e-mail pour les échecs Q321 conservées.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.02 — 14 novembre 2026
- Enregistrement de Q321 : aucune notification e-mail pour les tentatives de connexion échouées, même répétées ; enregistrement dans les journaux de sécurité côté serveur.
- Conservation des protections anti-abus et interdiction de journaliser mots de passe, tokens, codes ou secrets ; Q320 reste applicable aux événements sensibles réussis.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 4.01 — 14 novembre 2026
- Enregistrement de Q320 : e-mail de sécurité pour chaque événement sensible du compte client, notamment nouvelle connexion, changement de mot de passe, révocation d'appareil/session et changement d'adresse e-mail.
- Messages limités aux informations contextuelles utiles (date/heure et appareil disponible), sans mot de passe, token, session, code ni lien secret ; aucun code applicatif modifié, aucune fusion.

### Version 4.00 — 14 novembre 2026
- Enregistrement de Q319 : écran sécurité listant les sessions/appareils actifs (date, navigateur, système, dernière activité lorsque disponibles), avec révocation individuelle ou déconnexion de toutes les autres sessions.
- Actions limitées aux sessions du client ; révocation de la persistance associée et audit sans secrets. Q316 conserve sa règle de révocation totale, session courante incluse.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.99 — 14 novembre 2026
- Enregistrement de Q318 : option facultative « Se souvenir de moi », choisie explicitement par le client, avec persistance sécurisée et révocable par appareil.
- La révocation globale après réinitialisation doit invalider cette persistance ; durée et rotation à préciser avant codage.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.98 — 14 novembre 2026
- Enregistrement de Q317 : application au login client des limites anti-abus Identity cumulatives : 5 tentatives/compte, 30/IP et 5/IP + e-mail sur 15 minutes.
- Contrôles serveur cohérents avec les espaces internes, sans affaiblissement ni énumération de comptes ; aucun secret dans les journaux.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.97 — 14 novembre 2026
- Enregistrement de Q316 : après réinitialisation réussie, révocation de toutes les sessions web et de tous les tokens API du client, sans exception pour la session courante.
- Reconnexion obligatoire avec le nouveau mot de passe ; révocation auditée sans journaliser de secret.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.96 — 14 novembre 2026
- Enregistrement de Q315 : réinitialisation du mot de passe client par lien sécurisé envoyé par e-mail, valable 30 minutes et à usage unique.
- Vérification serveur de l'expiration et de l'usage unique ; application des règles de complexité Identity ; aucun OTP et aucun secret dans les journaux.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.95 — 14 novembre 2026
- Enregistrement de Q314 : connexion du client par adresse e-mail uniquement ; le téléphone n'est pas un identifiant d'authentification en V1.
- Maintien des contrôles Identity : compte actif, e-mail vérifié et protections anti-abus ; aucune connexion SMS/téléphone.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.94 — 14 novembre 2026
- Enregistrement de Q313 : aucune vérification SMS du téléphone en V1 ; seule la vérification e-mail est obligatoire avant activation et commande.
- Le téléphone reste soumis à validation de format côté serveur et n'est pas marqué comme vérifié.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.93 — 14 novembre 2026
- Enregistrement de Q312 : modification directe du nom, prénom, téléphone, e-mail et photo par le client ; carnet d'adresses séparé.
- Alignement du changement d'e-mail sur Q9-Q12 : vérification de la nouvelle adresse avant remplacement, révocation de toutes les sessions et tokens API, notification de sécurité à l'ancienne adresse.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.92 — 14 novembre 2026
- Enregistrement de Q311 : demande de suppression client, désactivation immédiate puis suppression/anonymisation encadrée par les obligations de conservation.
- Ajout des exigences Espace client : espace personnel isolé de tous les rôles internes et photo de profil avec limite stricte de 2 Mo ; audit Media et contrôles de sécurité avant codage.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.91 — 14 novembre 2026
- Enregistrement de Q310 : intervention sur une sous-commande en retard réservée à `super_admin_root` (toutes coopératives) et `super_admin` affecté (coopératives affectées) ; `cooperative_admin` exclu des décisions administratives de substitution.
- Début du cadrage de l'espace client : CRUD du compte/profil client, coordonnées (téléphone et e-mail) et adresses, en réutilisant les décisions Q286-Q298 ; les règles de suppression/désactivation du compte restent à définir.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.90 — 14 novembre 2026
- Enregistrement de Q309 : l'administrateur peut relancer avec un nouveau délai (maximum 24 h) ou accepter/refuser au nom de la coopérative ; intervention identifiée, autorisée et auditée.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.89 — 14 novembre 2026
- Enregistrement de Q308 : à l'expiration sans réponse, la sous-commande reste en attente ; un administrateur est alerté pour intervention manuelle, sans refus/annulation automatique.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.88 — 14 novembre 2026
- Enregistrement de Q307 : délai centralisé de réponse des coopératives, référence de 12 heures et plafond absolu de 24 heures ; conséquence de l'expiration à cadrer séparément.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.87 — 14 novembre 2026
- Enregistrement de Q306 : clôture automatique de la commande globale au statut « Refusée » si toutes les sous-commandes sont refusées ; frais de livraison nuls et notification client.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.86 — 14 novembre 2026
- Enregistrement de Q305 : frais retirés pour une sous-commande entièrement refusée avant expédition ; total recalculé sur les expéditions restantes.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.85 — 14 novembre 2026
- Enregistrement de Q304 : une seule expédition et un seul colis par sous-commande coopérative en V1.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.84 — 14 novembre 2026
- Enregistrement de Q303 : frais calculés par expédition/cooperative puis additionnés au total de la commande ; recalcul selon les expéditions restantes en cas de refus/annulation.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.83 — 14 novembre 2026
- Enregistrement de Q301 : adresse de livraison immuable après confirmation ; annulation puis nouvelle commande uniquement si les règles d'annulation le permettent.
- Enregistrement de Q302 : le refus d'une sous-commande ne modifie pas l'adresse commune des sous-commandes restantes.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.81 — 14 novembre 2026
- Enregistrement de Q300 : une commande multi-coopératives possède une seule adresse de livraison commune ; les sous-commandes et expéditions associées utilisent cette destination issue du snapshot global.
- Aucun code applicatif modifié ; aucune fusion de branche.

### Version 3.80 — 14 novembre 2026
- Enregistrement de Q299 : toutes les commandes V1 exigent une adresse de livraison ; aucun retrait coopérative en V1.

### Version 3.79 — 14 novembre 2026
- Enregistrement de Q298 : le carnet peut être vide ; une adresse valide appartenant au client est obligatoire pour confirmer une commande.

### Version 3.78 — 14 novembre 2026
- Enregistrement de Q297 : suppression autorisée d'une adresse même utilisée par une commande en cours ; le snapshot de commande demeure autonome et inchangé.

### Version 3.77 — 14 novembre 2026
- Enregistrement de Q296 : l'adresse choisie au checkout ne change pas l'adresse par défaut enregistrée ; elle ne vaut que pour la commande concernée.

### Version 3.76 — 14 novembre 2026
- Enregistrement de Q295 : le choix manuel d'une nouvelle adresse par défaut remplace immédiatement l'ancienne, avec une seule adresse par défaut au maximum.

### Version 3.75 — 14 novembre 2026
- Enregistrement de Q294 : la modification d'une adresse conserve son statut d'adresse par défaut ; aucun effet sur les snapshots des commandes existantes.

### Version 3.74 — 14 novembre 2026
- Enregistrement de Q293 : la première adresse de livraison active devient automatiquement l'adresse par défaut ; les créations suivantes ne changent pas le choix existant.

### Version 3.73 — 14 novembre 2026
- Enregistrement de Q292 : après suppression de l'adresse par défaut, aucune autre adresse n'est choisie automatiquement ; le client sélectionne explicitement une adresse au prochain checkout.

### Version 3.72 — 14 novembre 2026
- Enregistrement de Q291 : cinq adresses de livraison enregistrées au maximum par compte client ; contrôle serveur, sans limite sur les snapshots historiques.

### Version 3.71 — 14 novembre 2026
- Enregistrement de Q290 : la modification d'une adresse enregistrée ne concerne que les commandes futures ; les commandes déjà créées conservent leur snapshot de livraison immuable, même avant expédition.
- Le client peut modifier son carnet sans altérer une commande en cours ; toute correction de livraison en cours relève d'un processus distinct, autorisé et tracé.

### Version 3.70 — 14 novembre 2026
- Enregistrement de Q289 : chaque adresse de livraison possède son propre nom de destinataire et numéro de téléphone, indépendants du profil client.
- Ajout de ces coordonnées au snapshot de livraison de la commande ; validation détaillée des champs et règles de conservation à préciser.

### Version 3.69 — 14 novembre 2026
- Enregistrement de Q288 : carnet de plusieurs adresses de livraison par client, avec adresse par défaut et possibilité d'en choisir une autre au checkout.
- Précision des responsabilités Localization/Sales, de l'isolation par client et du snapshot de l'adresse dans chaque commande ; le plafond d'adresses reste à cadrer.

### Version 3.68 — 14 novembre 2026
- Enregistrement de Q287 : lien de vérification e-mail client valable 24 heures ; expiration serveur, renouvellement sous limites anti-abus et invalidation immédiate des liens précédents.
- Harmonisation des décisions Q6 et Q7 : la durée antérieure de 60 minutes est remplacée par 24 heures ; les limites de renvoi Q8 restent inchangées.

### Version 3.67 — 14 novembre 2026
- Enregistrement de Q286 : activation automatique du compte client après vérification réussie de l'e-mail ; inscription initiale en attente.

### Version 3.66 — 14 novembre 2026
- Enregistrement de Q285 : modification du seuil appliquée immédiatement après validation serveur.
- Formalisation du parcours directeur Client → Coopérative → Transporteur en sept étapes et de la méthode de développement progressive transversale.

### Version 3.65 — 14 novembre 2026
- Enregistrement de Q284 : un seuil personnalisé égal à zéro désactive « Presque épuisé » ; l'absence de valeur conserve le défaut de 5 unités.

### Version 3.64 — 14 novembre 2026
- Enregistrement de Q283 : seuil par défaut de 5 unités, remplaçable par un seuil configuré pour chaque produit.

### Version 3.63 — 14 novembre 2026
- Enregistrement de Q282 : seuil « Presque épuisé » configurable par produit par le cooperative_admin ; valeur par défaut de 5 unités, modifiable par produit par le cooperative_admin.

### Version 3.62 — 14 novembre 2026
- Enregistrement de Q281 : indicateur public « Disponible / Presque épuisé / Indisponible » sans révéler la quantité exacte ; seuil bas centralisé à définir avant codage.

### Version 3.61 — 14 novembre 2026
- Enregistrement de Q280 : rétablissement automatique de la disponibilité dès qu'Inventory confirme une quantité disponible nette des réservations ; aucun contrôle manuel de publication.

### Version 3.60 — 13 novembre 2026
- Enregistrement de Q279 : conserver la promotion visible lorsque toutes les variantes sont indisponibles, avec indication explicite et blocage des commandes.

### Version 3.59 — 13 novembre 2026
- Enregistrement de Q278 : relance d'une promotion annulée uniquement par création d'une nouvelle version et d'une nouvelle période, sans réactivation ni altération de l'ancienne version.

### Version 3.58 — 13 novembre 2026
- Enregistrement de Q277 : modification libre d'une annulation planifiée avant son effet, avec archivage de chaque programmation remplacée.

### Version 3.57 — 13 novembre 2026
- Enregistrement de Q276 : annulation immédiate ou planifiée d'une promotion active, avec conservation de la promotion jusqu'à l'instant d'effet programmé.

### Version 3.56 — 13 novembre 2026
- Enregistrement de Q275 : interdiction de supprimer physiquement une promotion ; annulation logique avec conservation de l'historique et des références commerciales.

### Version 3.55 — 13 novembre 2026
- Enregistrement de Q274 : une modification avant échéance remplace la version planifiée ; la version remplacée reste archivée et traçable.

### Version 3.54 — 13 novembre 2026
- Enregistrement de Q273 : la coopérative choisit entre application immédiate et planifiée d'une nouvelle version de promotion ; l'ancienne version reste applicable jusqu'à l'entrée en vigueur planifiée.

### Version 3.53 — 13 novembre 2026
- Enregistrement de Q272 : toute modification d'une promotion active crée une nouvelle version et conserve l'ancienne dans l'historique ; la date/heure d'entrée en vigueur de la nouvelle version reste à préciser.

### Version 3.52 — 13 novembre 2026
- Enregistrement de Q271 : publication sans approbation préalable, après validation serveur ; respect des dates de début/fin, journalisation et supervision.

### Version 3.51 — 13 novembre 2026
- Enregistrement de Q270 : gestion des promotions réservée au `cooperative_admin` de sa coopérative ; les rôles de supervision consultent sans modifier directement les paramètres commerciaux.
- Confirmation des contrôles serveur et de l'isolation des coopératives.

### Version 3.50 — 13 novembre 2026
- Enregistrement de Q269 : affichage du prix normal barré, du prix promotionnel et du pourcentage de réduction lorsque la promotion est active.
- Cohérence obligatoire entre variante disponible, prix « À partir de » et taux annoncé ; aucun affichage promotionnel après expiration.

### Version 3.49 — 13 novembre 2026
- Enregistrement de Q268 : arrondi commercial du prix promotionnel au centime le plus proche, égalité au demi-centime arrondie vers le haut.
- Calcul intermédiaire décimal exact et règle cohérente à centraliser entre Products, Catalog, Sales et Finance ; aucune correction automatique des valeurs saisies invalides.

### Version 3.48 — 13 novembre 2026
- Enregistrement de Q267 : taux promotionnel avec deux décimales maximum, borné entre plus de 0 % et 90 % inclus.
- Validation serveur sans arrondi/troncature et représentation décimale exacte ; règle d'arrondi du prix final toujours ouverte.

### Version 3.47 — 13 novembre 2026
- Enregistrement de Q266 : taux de réduction strictement positif, plafonné à 90 %, avec rejet serveur des valeurs supérieures.
- Précision du taux et règle d'arrondi du prix remisé conservées comme décisions préalables au codage.

### Version 3.46 — 13 novembre 2026
- Enregistrement de Q265 : taux de réduction unique au niveau du produit, appliqué au prix normal propre à chaque variante.
- Règle d'arrondi des prix remisés, bornes/précision du taux et éventuel cumul laissés ouverts avant codage.

### Version 3.45 — 13 novembre 2026
- Enregistrement de Q264 : promotion définie au niveau du produit et applicable à toutes ses variantes ; aucune promotion individuelle par variante en V1.
- Le mode de calcul du prix promotionnel appliqué aux variantes reste à arbitrer avant codage.

### Version 3.44 — 13 novembre 2026
- Enregistrement de Q263 : promotions simples avec prix normal, prix promotionnel et dates de début/fin.
- Prix promotionnel inférieur au prix normal ; application uniquement pendant la période active ; Catalog expose le prix applicable et Sales le fige dans la vente.
- Coupons, campagnes complexes et cumul de promotions exclus de cette décision ; détails temporels et chevauchements restent à préciser.

### Version 3.43 — 13 novembre 2026
- Enregistrement de Q262 : refuser tout prix saisi avec plus de deux décimales ; aucune correction automatique par arrondi ou troncature.
- Validation serveur pour les prix de base, prix de variantes et toutes les interfaces/API de modification.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.42 — 13 novembre 2026
- Enregistrement de Q261 : prix en MAD limités à deux décimales, avec représentation décimale exacte et cohérence entre les domaines.
- Les règles d'arrondi fiscales et les répartitions restent à cadrer avant leur implémentation.
- Aucun code applicatif modifié ; aucune fusion.

### Version 3.41 — 13 novembre 2026
- Enregistrement de Q260 : MAD comme devise du premier release, avec une architecture permettant une extension multi-devises ultérieure ; aucune conversion implicite.
- Consolidation dans le registre de découverte des décisions Marketplace Q257 à Q259 déjà consignées dans la feuille de route.
- Aucun code applicatif modifié ; aucune fusion.

### Version 2.15 — 13 novembre 2026
- Ajout de la matrice Media Q1 à Q90 : questions, réponses validées, règles d'accès, sécurité, traitements, associations, images, vidéos et PDF.
- Aucune modification du code applicatif ; aucune fusion de branche.

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