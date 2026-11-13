# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 2.77
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
- **Points restant à préciser :** champs exacts du profil commercial, nombre d'adresses autorisées, adresse par défaut, validation des coordonnées, consentements et politique de conservation/anonymisation.

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
- **Réponse :** A — lien sécurisé envoyé par e-mail ; le client clique sur ce lien pour vérifier son adresse. Le lien expire après 60 minutes et un nouvel envoi est possible.
- **Statut :** VALIDÉ — premier release.
- **Périmètre concerné :** Identity ; génération, envoi, validation, expiration et renvoi du lien de vérification.
- **Conséquences :**
  - Le mécanisme de vérification repose sur un lien individuel sécurisé envoyé à l'adresse e-mail déclarée.
  - Le lien est à usage contrôlé et expire 60 minutes après son émission.
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
  - La création d'un nouveau lien invalide immédiatement le précédent, même si sa période de validité de 60 minutes n'est pas écoulée.
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
## 6. Historique des mises à jour

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