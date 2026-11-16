# MAHLINE V1 — Règles fonctionnelles Commandes, Facturation, Paiements et Livraison

> Statut : décisions fonctionnelles validées par l'utilisateur le 16 novembre 2026.
>
> Périmètre : règles métier de référence. Ce document ne constitue pas une autorisation d'implémenter les migrations ou le code avant audit technique et validation des écarts.

## 1. Notifications client

Les événements suivants déclenchent automatiquement une notification au client :
1. Commande créée.
2. Commande validée.
3. Commande annulée, avec son motif.
4. Commande en préparation.
5. Commande expédiée.

Canaux validés : notification dans l'espace MAHLINE et email. Les SMS sont hors périmètre V1.

## 2. Facturation

### 2.1 Relation commande / facture
- Chaque commande validée génère une facture unique.
- Une facture est liée à une seule commande.
- La facture est émise après validation de la commande et réservation effective du stock.
- Une facture émise/validée est immuable.
- Une annulation conserve la facture dans l'historique avec son statut approprié.
- Toute correction d'une commande déjà facturée passe par l'annulation motivée de l'ancienne commande puis la création d'une nouvelle commande et d'une nouvelle facture. Aucune modification directe de l'ancienne commande ou facture.
- Un numéro de facture déjà attribué n'est jamais réutilisé, même après annulation.

### 2.2 Numérotation
- Numérotation indépendante par coopérative.
- Format : `FAC-AAAA-NNNNNN` (exemple : `FAC-2026-000001`).
- Le compteur recommence à 000001 chaque année pour chaque coopérative.
- L'historique doit garantir qu'aucun numéro attribué ne soit réutilisé.

### 2.3 Contenu minimal
- Identité et coordonnées de la coopérative.
- Identité et coordonnées du client.
- Numéro et date de facture.
- Référence de la commande.
- Désignation des produits et variantes.
- Quantités et unités de mesure.
- Prix unitaires HT, TVA et total TTC, selon le régime fiscal applicable.
- Frais de livraison.
- Total général, devise et conditions de paiement.

### 2.4 PDF et accès
- Génération automatique du PDF à l'émission.
- Conservation du PDF dans MAHLINE.
- Le PDF d'une facture émise est conservé et ne peut pas être remplacé par une version modifiée.
- Le client consulte et télécharge ses propres factures.
- Le `cooperative_admin` consulte les factures de sa coopérative.
- Le `super_admin` consulte les factures des coopératives qui lui sont assignées.
- Le `super_admin_root` dispose d'un accès global.
- Le PDF est envoyé automatiquement au client par email à l'émission.
- Tracer la date, le destinataire et le résultat de l'envoi. En cas d'échec, la facture reste disponible dans MAHLINE et l'envoi peut être retenté.

## 3. Paiements et contrats

### 3.1 Acteurs et circuit d'encaissement
Les acteurs du circuit sont le client, la société de livraison, MAHLINE et la coopérative.
1. Le client remet le montant total (produits + livraison) à la société de livraison à la remise de la commande.
2. La société de livraison conserve les frais de livraison qui lui reviennent selon son contrat.
3. La société de livraison transfère à MAHLINE le solde encaissé qui lui est dû.
4. À réception des fonds, MAHLINE applique la rémunération/commission prévue au contrat avec la coopérative.
5. MAHLINE reverse à la coopérative le solde qui lui revient.

**Précision de priorité :** cette règle contractuelle, confirmée ensuite, remplace l'hypothèse antérieure selon laquelle MAHLINE réglerait séparément les frais de livraison à la société. La société conserve directement sa part de livraison selon son contrat avant le transfert du solde à MAHLINE.

### 3.2 Modalités contractuelles configurables
Les modalités financières ne sont pas universelles : elles dépendent des contrats conclus avec chaque coopérative et chaque société de livraison. Le taux de 5 % évoqué pendant la découverte est un exemple validé pour le scénario discuté, pas un taux global imposé à tous les partenaires.

Les contrats peuvent définir notamment :
1. Un pourcentage appliqué au montant total payé par le client (produits + livraison).
2. Un pourcentage appliqué au montant des produits uniquement (hors livraison).
3. Un montant fixe déterminé selon une tranche/échelle du montant de la commande.

Les règles doivent être configurables et traçables par contrat. Chaque calcul doit pouvoir être expliqué à partir du contrat applicable et de sa version/date d'effet.

### 3.3 Deux contrats indépendants
- Contrat MAHLINE ↔ Coopérative : commission/rémunération de MAHLINE, base de calcul et conditions/calendrier de reversement.
- Contrat MAHLINE ↔ Société de livraison : tarifs et frais de livraison, modalités de collecte, conservation de la part livraison et transfert des fonds à MAHLINE.
- Les conditions des deux contrats sont indépendantes, datées et historisées.

### 3.4 Calendrier de transfert société de livraison → MAHLINE
- Transfert périodique tous les 7 jours, selon un calendrier ancré au début de chaque mois.
- Exemple de dates de transfert : 7, 14, 21 et 28 du mois.
- Chaque transfert est rapproché des commandes et encaissements concernés.
- Les montants non encore transférés restent identifiés comme sommes dues.

### 3.5 Reversement MAHLINE → coopérative
- Reversement prévu chaque semaine, selon les modalités du contrat MAHLINE ↔ Coopérative.
- Le reversement est effectué après réception des fonds transférés par la société de livraison et calcul de la part contractuelle de MAHLINE.
- Les paiements, commissions, retenues, transferts et reversements doivent être traçables et rapprochables par commande, contrat, société de livraison et coopérative.

## 4. Sociétés de livraison et attribution des missions

### 4.1 Relations coopératives / sociétés
- Une coopérative peut travailler simultanément avec plusieurs sociétés de livraison.
- Chaque commande expédiée est associée à la société qui accepte ou reçoit la mission.
- Les contrats, tarifs, transferts et historiques sont suivis par société de livraison.

### 4.2 Flux de traitement
1. La coopérative valide et prépare la commande.
2. Lorsque la commande est prête, la coopérative la marque comme prête pour expédition. Les frais de livraison sont déjà affichés dans la commande.
3. MAHLINE notifie dans l'application les sociétés de livraison concernées/éligibles.
4. Une société accepte la mission dans MAHLINE.
5. La première société qui accepte obtient la mission ; les autres ne peuvent plus accepter cette commande.
6. La société qui a accepté récupère physiquement la commande auprès de la coopérative et la remet au client.
7. Le client paie le montant total à la société de livraison.

### 4.3 Délai d'acceptation et attribution automatique
- Les sociétés concernées disposent de 12 heures pour accepter librement la mission.
- La première acceptation valide emporte attribution exclusive de la mission.
- Si aucune société n'accepte dans les 12 heures, MAHLINE attribue automatiquement la mission à la société éligible proposant le tarif de livraison le moins cher.
- La règle de départage en cas d'égalité de tarif reste à définir.
- L'éligibilité, les notifications, les acceptations, l'attribution automatique et son motif doivent être historisés.

## 5. Points restant à préciser avant implémentation
- Critère de départage si plusieurs sociétés éligibles proposent exactement le même tarif.
- Règles en cas d'échec de collecte, de livraison, de refus du colis ou d'encaissement incomplet.
- Définition précise des pièces justificatives et du rapprochement des transferts financiers.
- Gestion des écarts, litiges, remboursements et retours dans le circuit de paiement.
- Paramètres contractuels détaillés : devise, assiette, tranches, arrondis, taxes, plafonds/planchers, dates d'effet, échéances et traitement des commandes en cours lors d'un changement de contrat.

## 6. Consigne de mise en œuvre
Ce document consigne les décisions fonctionnelles validées. Avant toute migration ou implémentation :
- auditer l'architecture, les modèles, relations, routes, policies, services, interfaces et tests existants ;
- identifier les écarts et les dépendances avec Identity, Cooperatives, Products, Orders, Inventory, Media, Invoicing et les futurs domaines Payments/Delivery ;
- faire valider les règles encore ouvertes ;
- proposer ensuite une implémentation incrémentale avec tests.
