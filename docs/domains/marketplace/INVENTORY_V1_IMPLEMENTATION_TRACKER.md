# MAHLINE — Suivi d'implémentation Inventory V1

- **Branche de travail :** `feature/Marketplace`
- **Référence métier :** `INVENTORY_V1_DECISIONS_2026-11-21.md`
- **Conception :** `INVENTORY_V1_TECHNICAL_DESIGN.md`
- **Dernière mise à jour :** 21 novembre 2026 (revue transactionnelle Orders/Inventory)
- **Statut global :** implémentation du socle et des intégrations en cours ; aucune clôture avant exécution complète des contrôles.

> Les décisions métier consolidées prévalent sur le cadrage initial de `INVENTORY_V1_SPECIFICATION.md`. La présence de code ne vaut pas validation : tests MySQL, suite complète et Pint doivent être exécutés et verts.

## Fonctionnalités dont le code est présent

| Domaine fonctionnel | Implémentation présente | Vérification restante |
|---|---|---|
| Solde par coopérative + produit/variante | Modèle, migration, unités et quantités à trois décimales | Tests d'invariants et de concurrence sur MySQL |
| Mouvements | Grand livre avant/après, acteurs, motifs, références, modèle protégé contre UPDATE/DELETE | Tests d'immutabilité et de cohérence |
| Entrées et ajustements | Initialisation, réception, ajustement motivé, verrou de ligne | Tests rôles, unités, idempotence et refus de stock négatif |
| Checkout / Orders | Preview par coopérative, fingerprint d'acceptation, réservation à la finalisation, consommation à confirmation, libération et expiration | Tests end-to-end Orders + Inventory, concurrence, rollback multi-coop |
| Quarantaine | Réception de retour, inspection, autorisation quantitative, révocation et allocation par autorisation | Tests complets des états, allocations et opérations concurrentes |
| Inventaire physique | Sessions, participants, comptages, corrections historisées, validation partielle et annulation logique | Tests de sessions simultanées, verrous, reprise et immutabilité |
| Seuils et alertes | Seuil par stock, historique, épisodes distincts low/out, réarmement et réconciliation périodique | Tests de concurrence et de notification |
| Notifications de stock | Outbox vers notifications Laravel database pour cooperative_admin, super_admin affecté et user de la coopérative | Vérifier destinataires réels et absence de doublons |
| Signalements de sécurité | Création, consultation scoped, fermeture/réouverture motivées, échéance urgente de 2 h, escalade sans levée automatique | Tests d'accès, délais, réouverture et intégration hold |
| Preuves privées | Upload via contrat Media pendant la soumission ; type/antivirus/limites par Media ; consultation root-only | Tests de rollback fichier/DB et audit des consultations |
| Blocage Orders | Contrat de safety hold, contrôle de périmètre coopératif, résolution humaine resume/cancel ; `assertCanShip` exige désormais une transaction appelante et conserve les verrous jusqu'à sa fin | Brancher le contrôle au flux d'expédition Delivery et tester la course blocage/expédition sur MySQL |
| Rapports PDF | Rapport complet managers et simplifié user, stocks/mouvements/alertes/comptages/quarantaines | Tests PDF, confidentialité et gros volumes |
| Outbox / commandes planifiées | Expiration réservations, publication outbox, réconciliation alertes, escalade signalements | Vérifier ordonnanceur en environnement déployé |

## Interfaces HTTP présentes

- `GET /cooperatives/{cooperativeId}/inventory/stocks` : état uniquement `En stock` / `Rupture de stock`.
- Routes de mutation stock, seuils, inventaire physique et quarantaine sous `/cooperatives/{cooperativeId}/inventory`.
- Soumission, liste, consultation, clôture et réouverture des signalements de sécurité.
- Export PDF par coopérative.

Les routes renvoient actuellement des réponses JSON/PDF ; les écrans Blade dédiés Inventory ne sont pas encore réalisés.

## Points à terminer avant clôture

1. Exécuter les migrations sur MySQL 8 et corriger tout écart réel entre schéma et code.
2. Obtenir les résultats verts des tests ciblés Inventory, tests Orders, suite complète et Pint.
3. Ajouter/compléter les tests de concurrence, idempotence, rollback et autorisations, notamment sur la quarantaine et les alertes.
4. Vérifier que la création d'un signalement urgent ne peut viser qu'une sous-commande de la même coopérative et que le hold reste actif jusqu'à une décision humaine.
5. Brancher `OrderSafetyHoldManager::assertCanShip` dans la transaction qui effectue réellement la transition d'expédition Delivery. Le garde-fou refuse maintenant tout appel hors transaction, afin d'éviter un verrou libéré avant l'expédition. Le domaine Delivery n'exposant pas encore de service d'expédition identifié dans cette branche, le branchement effectif reste bloqué par cette dépendance.
6. Compléter les écrans Inventory et l'intégration du libellé produit/variante via un contrat public Products (les rapports affichent pour l'instant les identifiants logiques).
7. Vérifier les journaux d'accès aux pièces jointes privées et la gestion de nettoyage des fichiers en cas de rollback global.
8. Synchroniser la spécification fonctionnelle et les ADR si les tests révèlent un invariant nécessitant un ajustement.

## CI

Les exécutions GitHub Actions déclenchées pendant cette séquence étaient encore en file d'attente au moment de cette mise à jour. Aucun résultat vert n'est revendiqué ici.

## Discipline de livraison

- Pas de merge vers `main` sans accord explicite.
- Pas de modification des migrations déjà exécutées.
- Toute correction se fait par migration additionnelle.
- Aucune fonctionnalité n'est déclarée terminée sur la seule base de la présence du code.


## Revue de concurrence — 21 novembre 2026

- Correction appliquée dans `OrderSafetyHoldService::assertCanShip` : suppression de la transaction interne qui libérait les verrous avant la transition d'expédition.
- Le contrôle exige maintenant une transaction appelante active ; il verrouille la sous-commande et le blocage actif dans cette transaction.
- Cette correction sécurise le contrat, mais ne constitue pas l'intégration finale : Delivery doit appeler le contrôle dans la même transaction que son changement d'état.
- Tests MySQL de concurrence et suite complète non exécutés depuis cette correction ; aucun résultat vert n'est revendiqué.
