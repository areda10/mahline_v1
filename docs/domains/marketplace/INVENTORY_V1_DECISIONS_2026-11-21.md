# MAHLINE — Décisions validées Inventory V1

- **Date de consolidation :** 21 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** décisions métier V1 consolidées ; conception et implémentation à réaliser.
- **Portée :** ce document complète et prévaut sur les règles contradictoires du cadrage initial de `INVENTORY_V1_SPECIFICATION.md` pour les décisions explicitement validées ci-dessous.
- **Important :** aucune décision non exprimée par l'utilisateur n'est considérée comme validée.

## 1. Stock et périmètre

1. Inventory est l'unique propriétaire des soldes, réservations, mouvements, quarantaines et disponibilités.
2. Un stock V1 par coopérative et par produit simple ou variante ; un produit avec variantes porte son stock sur chaque variante, jamais simultanément sur le parent.
3. Aucun entrepôt/emplacement multiple en V1 ; évolution multi-emplacements reportée à V2.
4. Aucun transfert de stock entre coopératives en V1 ; évolution reportée à V2.
5. Unités canoniques : `kg`, `g`, `l`, `ml`, `u`. Poids/volumes : trois décimales maximum ; unité : entier. Aucune conversion automatique d'unités incompatibles.
6. Aucun stock négatif. Les mutations sont atomiques et verrouillent le solde concerné.
7. Les mouvements sont permanents, append-only et non supprimables. Toute correction passe par un mouvement compensatoire.
8. Les ajustements manuels sont immédiats, motivés et historisés avec quantités avant/après, auteur, coopérative et horodatage.
9. V1 autorise les entrées manuelles (production, réception, retour validé, etc.) avec motif et traçabilité. Achats fournisseurs avancés reportés à V2.
10. Seuil de stock faible configurable par coopérative et produit/variante ; changement historisé. Alerte réarmée lorsque le stock repasse au-dessus du seuil.
11. Alertes stock faible et rupture séparées, historisées et sans doublons pendant un même épisode.
12. Notifications V1 : in-app MAHLINE uniquement. Destinataires des alertes stock : `cooperative_admin`, `super_admin` affecté et `user` de la coopérative ; pas d'alerte automatique au root.

## 2. Checkout, réservation et cycle Orders

1. Le checkout ne crée aucune réservation et ne modifie aucun solde Inventory.
2. L'aperçu de disponibilité au checkout est informatif ; il ne garantit pas que le stock sera encore disponible à la confirmation.
3. Aucune réservation n'est créée pendant l'attente de réponse de la coopérative (12 heures par défaut, plafond de 24 heures).
4. La réservation est créée uniquement lorsque la coopérative confirme sa sous-commande.
5. À la confirmation, Inventory relit et verrouille tous les soldes concernés, vérifie la quantité complète de chaque ligne active, puis réserve toutes les lignes de la sous-commande dans une transaction atomique.
6. Si une seule ligne est insuffisante ou verrouillée par un inventaire physique, la confirmation entière échoue : la sous-commande reste en attente et aucune réservation partielle ne subsiste.
7. Pour une commande multi-coopératives, chaque sous-commande est confirmée et réservée indépendamment ; la disponibilité d'une coopérative n'empêche pas la décision d'une autre.
8. Une réservation créée après confirmation reste active sans expiration automatique jusqu'à sa consommation lors de l'exécution/expédition ou sa libération explicite.
9. Le décrément physique du stock intervient à la consommation de la réservation, pas au checkout ni à la confirmation seule.
10. Un refus avant confirmation ne crée aucune réservation. Une annulation de sécurité autorisée avant expédition libère la réservation active.
11. Une fois la sous-commande confirmée par la coopérative, l'annulation commerciale ordinaire est interdite.
12. Chaque opération de réservation, consommation ou libération est idempotente, avec une clé unique par sous-commande et opération. Un retry ne duplique ni mouvement ni mutation de stock.
13. Les règles d'indicateurs validées restent : multi-coopératives = un indicateur par sous-commande ; mono-coopérative = un indicateur global et un par ligne. Vert signifie que le disponible couvre la quantité complète ; rouge sinon.

## 3. Disponibilité et visibilité

1. Tous les rôles sauf `customer` peuvent consulter l'état de disponibilité, sans quantité détaillée dans cette vue.
2. L'affichage est limité à `En stock` / `Rupture de stock`.
3. Périmètre : root toutes les coopératives ; super_admin coopératives affectées ; cooperative_admin sa coopérative ; user l'état de disponibilité autorisé.
4. Les quantités physiques, réservées et en quarantaine ne sont pas exposées par l'indicateur de disponibilité.

## 4. Quarantaine, inspection et vente conditionnelle

1. Tout retour reçu par une coopérative entre d'abord en quarantaine.
2. La coopérative inspecte individuellement chaque produit retourné.
3. Un produit conforme et revendable peut être réintégré au stock normal ; un produit non conforme reste exclu du disponible, avec état et motif.
4. Exception V1 : un produit en quarantaine peut être explicitement autorisé à la vente sous la responsabilité de la coopérative.
5. Autorisateurs : `cooperative_admin`, `super_admin` affecté et `super_admin_root`, chacun dans son périmètre.
6. Autorisation explicite, quantité déterminée, motif obligatoire, auteur/date et historique permanent ; aucune autorisation automatique.
7. L'autorisation reste valable jusqu'à épuisement de la quantité autorisée ou révocation.
8. Les quantités autorisées restent comptabilisées séparément de la quarantaine non vendable ; elles ne sont pas fusionnées dans le stock normal.
9. En cas de commande mixte, le stock normal est prélevé en priorité ; la quarantaine autorisée complète uniquement si nécessaire.
10. La révocation interdit immédiatement toute nouvelle réservation sur le reliquat, mais protège les réservations déjà effectuées.
11. Si une commande déjà confirmée contient des quantités de quarantaine autorisée et que l'autorisation est révoquée avant expédition, l'expédition est bloquée pour examen.
12. La reprise de l'expédition exige une décision explicite de `cooperative_admin`, `super_admin` affecté ou `super_admin_root`, dans son périmètre.
13. Si la reprise est refusée, la commande est annulée et les quantités concernées retournent en quarantaine, avec mouvements compensatoires et historique.
14. Une annulation exceptionnelle pour raison de sécurité documentée peut s'appliquer à d'autres commandes confirmées ; elle ne rétablit pas l'annulation commerciale ordinaire.
15. Le rôle `user` peut signaler un risque, mais ne peut ni décider ni lever un blocage.
16. Un signalement urgent après confirmation déclenche une mise en attente temporaire de sécurité et bloque l'expédition jusqu'à décision humaine.
17. Délai de traitement attendu : deux heures. À l'expiration sans décision, le blocage reste actif et le signalement est escaladé. Aucune levée automatique, y compris la nuit, les week-ends et jours fériés.
18. Les signalements sont conservés de façon permanente, y compris ceux non fondés ou classés sans suite.
19. Détails consultables par root, super_admin affecté et cooperative_admin de la coopérative concernée. Le déclarant peut consulter le suivi de ses propres signalements.
20. Les signalements peuvent contenir des pièces jointes privées ; contrôles antivirus, type réel, extension, taille, nombre et journalisation des consultations/téléchargements obligatoires.
21. En V1, le signalement est figé après soumission : pas d'ajout ultérieur par son auteur. Ajouts successifs par le déclarant reportés à V2.
22. Clôture par cooperative_admin, super_admin affecté ou root dans son périmètre, avec motif obligatoire. Réouverture possible par un responsable autorisé, avec motif et historique.
23. La réouverture d'un signalement lié à une commande déjà expédiée et la nouvelle alerte sur cette commande sont reportées à V2.

## 5. Inventaires physiques

1. V1 inclut les sessions d'inventaire par coopérative et les comptages par produit/variante.
2. Comparaison quantité théorique/comptée ; écart positif ou négatif et motif obligatoire pour chaque différence.
3. La validation applique immédiatement les ajustements et crée des mouvements immuables.
4. Blocage sélectif : seul le produit/variante en cours de comptage est bloqué ; ventes, réservations, entrées et sorties sur les autres produits continuent.
5. Sur un élément verrouillé, toute réservation, entrée ou sortie est interdite jusqu'à validation ou annulation.
6. Les comptages peuvent être corrigés avant validation ; ancienne/nouvelle valeur, auteur, date/heure et motif sont historisés.
7. Après validation, résultat et quantités sont immuables ; correction ultérieure par nouvel inventaire ou ajustement distinct.
8. Validation partielle permise : seuls les éléments comptés sont validés et déverrouillés ; les éléments non comptés restent bloqués tant que la session reste active.
9. Plusieurs sessions simultanées sont permises dans une coopérative si elles portent sur des produits/variantes disjoints. Un même élément ne peut appartenir à deux sessions actives.
10. Session abandonnée : annulation logique, jamais de suppression physique ; motif, comptages, participants et historique conservés ; tous les blocages associés sont libérés.
11. Root : créer, compter et valider toutes les coopératives ; super_admin affecté : même périmètre ; cooperative_admin : sa coopérative ; user : compter uniquement, sans créer ni valider ; customer : aucun accès.

## 6. Rapports

1. Export PDF uniquement en V1 ; XLSX/CSV reportés à V2.
2. Rapports : stocks, états en stock/rupture, mouvements, ajustements manuels, alertes stock faible/rupture, sessions et écarts d'inventaire, quarantaines et résultats d'inspection.
3. Root : toutes les coopératives ; super_admin : coopératives affectées ; cooperative_admin : sa coopérative ; user : rapport simplifié de sa coopérative, sans données sensibles de gestion ; customer : aucun accès.

## 7. Décisions reportées à V2

- Multi-emplacements de stockage.
- Transferts inter-coopératives.
- Gestion avancée des fournisseurs, commandes d'achat et réceptions partielles.
- Ajouts d'informations/pièces jointes par le déclarant après soumission d'un signalement.
- Réouverture d'un signalement lié à une commande expédiée avec alerte de sécurité sur la commande.
- Export XLSX/CSV.

## 8. Point non validé — ne pas inventer

La question du rôle autorisé à effectuer/valider une mise au rebut définitive d'un produit non conforme n'a pas reçu de réponse avant la clôture de la phase Q/R. L'opération et sa traçabilité peuvent être conçues, mais sa permission ne doit pas être attribuée silencieusement. Elle doit rester non exposée tant que la règle d'autorisation n'est pas formellement arrêtée.

## 9. Principes d'implémentation obligatoires

- Inventory ne dépend pas directement des modèles Eloquent Products, Orders, Identity, Cooperatives, Delivery ou Media.
- Les références vers les autres domaines sont des identifiants logiques et des contrats/DTO publics.
- Orders demeure propriétaire des statuts commerciaux ; Inventory demeure propriétaire des soldes et réservations.
- Une seule frontière transactionnelle cohérente pour la finalisation checkout et la réservation ; rollback complet en cas d'échec.
- Outbox/événements fiables pour les notifications et intégrations asynchrones ; les événements ne remplacent pas les invariants transactionnels.
- Les migrations déjà exécutées ne sont jamais modifiées ; les nouvelles migrations sont ajoutées dans le domaine propriétaire et explicitement chargées par son provider.
- Aucun statut de test n'est déclaré réussi sans exécution réelle.
