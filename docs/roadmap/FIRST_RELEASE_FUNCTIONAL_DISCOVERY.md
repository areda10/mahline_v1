# MAHLINE — Cadrage fonctionnel du premier release SaaS + marketplace

- **Version :** 0.2
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

## 3. Questions ouvertes

Les questions seront ajoutées au fur et à mesure, dans l'ordre du parcours métier. Une question n'est considérée validée que lorsque la réponse de l'utilisateur est explicite.

| ID | Sujet | Statut |
|---|---|---|
| FR-004 | Moyens de paiement du premier release | VALIDÉ — paiement à la livraison ; paiement en ligne après le release |
| FR-005 | Frais de livraison pour un panier multi-coopératives | À poser |
| FR-006 | Gestion des stocks et réservation lors du panier/paiement | À poser |
| FR-007 | Traitement et transitions de statut des commandes coopératives | À poser |
| FR-008 | Annulation, retour et remboursement | À poser |
| FR-009 | Factures, reçus et documents client/coopérative | À poser |
| FR-010 | Commissions et reversements aux coopératives | À poser |
| FR-011 | Inscription et espace personnel client | À poser |
| FR-012 | Publication et visibilité des coopératives/produits | À poser |
| FR-013 | Livraison nationale et internationale au premier release | À poser |
| FR-014 | Abonnements SaaS, plans, quotas et renouvellement | À poser |
| FR-015 | Notifications et communications indispensables au lancement | À poser |

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

### Version 0.2 — 12 novembre 2026
- Enregistrement de FR-004 : paiement à la livraison uniquement au premier release ; ajout du paiement en ligne ensuite, en conservant le paiement à la livraison.

### Version 0.1 — 12 novembre 2026
- Création du registre de cadrage fonctionnel du premier release.
- Enregistrement de FR-001 : SaaS de gestion + marketplace publique.
- Enregistrement de FR-002 : compte client obligatoire.
- Enregistrement de FR-003 : panier multi-coopératives, paiement unique, commande et facturation séparées par coopérative.
- Aucune modification du code applicatif.
