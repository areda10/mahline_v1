# MAHLINE V1 — Audit des dépendances du domaine Sales

- Date : 19 novembre 2026
- Dépôt : `areda10/mahline_v1`
- Branche : `feature/Marketplace`
- Référentiel : ADR-021 — architecture hybride du monolithe modulaire
- Décisions fonctionnelles : SALES-001 à SALES-037 validées
- Statut : audit statique initial des frontières ; audit d'exécution et tests à faire

## 1. Périmètre confirmé

Sales possède le parcours commercial pré-commande : panier invité, panier client, fusion, lignes, validation commerciale, préparation et orchestration du checkout.

Sales ne possède pas :
- les produits, variantes, unités ou prix de référence (Products) ;
- les catégories/catalogues (Categories/Catalog) ;
- les comptes, authentification et sessions (Identity) ;
- les coopératives et leurs statuts (Cooperatives) ;
- les adresses et règles géographiques (Localization) ;
- les commandes, sous-commandes, statuts et snapshots après création (Orders) ;
- le stock, la disponibilité et les réservations (Inventory) ;
- les tarifs, expéditions et opérations de transport (Delivery) ;
- les factures et opérations financières (Finance) ;
- les notifications et leur journal d'envoi (Communication).

## 2. Inventaire constaté

À la référence de code examinée, aucun élément Sales n'a été trouvé dans :
- `app/Domains/Sales` : domaine absent ;
- `bootstrap/providers.php` : aucun SalesServiceProvider ;
- `routes/web.php` et `routes/api.php` : aucune route Sales identifiée lors de l'inventaire initial ;
- migrations : aucune migration Sales ;
- tests : aucun test Sales confirmé.

Éléments fournisseurs repérés :
- Products : `CheckoutCatalog`, `CheckoutProductData`, `ProductService`, `Product`, `ProductVariant`.
- Orders : `OrderCreationService::createFromCheckout(User $customer, array $checkout)`, modèles Order/CooperativeOrder/OrderItem.
- Cooperatives : `CooperativeService`, `Cooperative`, enum de statut.
- Localization : `AddressService`, `Address`, `CooperativeAddressManager`.
- Identity : modèle User et mécanismes d'authentification.
- Inventory : absent du code ; spécification Inventory V1 présente, contrat non intégré.
- Delivery : absent du code ; contrat non intégré.

## 3. Constats vérifiés et écarts

### 3.1 Products — priorité P0

Le contrat `App\Domains\Products\Contracts\CheckoutCatalog` et son DTO `CheckoutProductData` existent ; `ProductService` l'implémente. Le service recharge le produit et la variante et verrouille les lignes lors de la résolution checkout.

Écarts :
- Le résultat checkout ne constitue pas un contrat suffisant de lecture d'une ligne historique de panier : il ne représente pas de façon exhaustive produit/variante supprimés, désactivés, caractéristiques commerciales modifiées, historique et version commerciale.
- Une résolution nulle ne fournit pas de diagnostic métier riche pour distinguer les états ; le modèle soft-deleted est absent des requêtes ordinaires.
- `ProductService` importe directement `Catalog` et `Categories` Models ainsi que `Identity\Users\Models\User`. Ces imports internes doivent être remplacés ou justifiés par des contrats publics conformément à ADR-021.
- La preuve d'acceptation d'une hausse de prix ou d'un changement de configuration doit être liée à une version/fingerprint stable, pas à un simple prix envoyé par le client.

Traitement requis :
1. Conserver `CheckoutCatalog` pour son cas d'usage actuel, éviter une modification cassante.
2. Définir un contrat public de lecture commerciale pour panier, retournant état explicite, identité, nom, variante, prix, unité, caractéristiques commerciales et version/fingerprint.
3. Définir une représentation historique minimale pour les ressources supprimées, sans autoriser leur achat.
4. Ajouter tests de contrat pour chaque état de SALES-006 à SALES-013 et SALES-027 à SALES-037.
5. Découpler les accès Catalog/Categories/Identity de ProductService par contrats et tests, dans un lot séparé.

### 3.2 Cooperatives — priorité P0

`CooperativeService` et `Cooperative` existent. Sales doit connaître le statut actuel de la coopérative propriétaire pour SALES-030 à SALES-032.

Traitement requis :
1. Exposer un contrat de lecture public par identifiant coopérative.
2. Retourner un statut explicite, y compris coopérative supprimée/introuvable si le domaine peut la distinguer sans fuite de données.
3. Ne pas exposer le modèle Eloquent Cooperative à Sales.
4. Tester active, inactive, suspended, archived, pending et soft-deleted selon les statuts réellement autorisés.

### 3.3 Localization — priorité P0

`AddressService` gère les adresses client et coopérative ; `Address` appartient à Localization. L'API actuelle de gestion utilise le modèle User comme acteur/propriétaire.

Traitement requis :
1. Définir un contrat public de consultation d'adresse client pour checkout, recevant des identifiants logiques et un contexte d'autorisation minimal.
2. Vérifier que l'adresse appartient au client, qu'elle est active et utilisable pour la livraison.
3. Retourner un DTO immuable de snapshot, indépendant du modèle Address.
4. Préserver les snapshots de commandes même si l'adresse est ensuite modifiée ou supprimée.
5. Découpler les dépendances directes User/Cooperative des couches internes de Localization selon ADR-021, sans réécrire les contraintes d'ownership/XOR.

### 3.4 Identity — priorité P0

Identity est propriétaire des comptes et de l'authentification. Sales doit identifier un visiteur ou un client sans importer le modèle User.

Traitement requis :
1. Le panier invité est identifié par un identifiant opaque de panier signé/lié à une session sécurisée, sans considérer un identifiant client comme preuve d'autorisation.
2. Le panier authentifié est rattaché à un identifiant logique de compte fourni par la couche HTTP/composition root.
3. Les contrats Sales reçoivent un contexte d'acteur minimal/ID et non `User` Eloquent.
4. Le merge à la connexion doit être déclenché après authentification confirmée et être idempotent.

### 3.5 Orders — priorité P0 pour checkout

`OrderCreationService::createFromCheckout` existe et construit la commande globale, les sous-commandes par coopérative et les snapshots dans une transaction. Il dépend du contrat Products `CheckoutCatalog`.

Écarts :
- Aucun contrat public Orders dédié à Sales n'a été repéré.
- La méthode reçoit `User` Eloquent Identity et un tableau libre ; cette signature traverse la frontière de domaine.
- Aucune clé d'idempotence de checkout n'est visible dans la signature examinée.
- Le service relit le prix courant via Products ; il n'expose pas dans la signature un mécanisme de preuve/version d'acceptation d'un prix ou d'une configuration commerciale.
- Le commentaire indique que le caller doit fournir un snapshot d'adresse déjà validé ; ce prérequis doit être garanti par un contrat Localization avant l'appel.
- Le service initialise les frais de livraison à zéro dans le code examiné ; cela ne doit pas être présenté comme un tarif de livraison réel.

Traitement requis :
1. Créer un contrat public Orders de création de commande depuis un DTO versionné.
2. Inclure customerId logique, clé d'idempotence, snapshot d'adresse validé, lignes avec identifiants et données commerciales acceptées.
3. Orders demeure responsable de revalider les références et d'écrire uniquement ses tables.
4. Retourner un résultat explicite : créée, déjà créée pour cette clé, rejetée pour données invalides.
5. Garantir qu'un retry après timeout ne crée pas une seconde commande.
6. Tester rollback complet, doublons, appels concurrents et réponse perdue.
7. Remplacer progressivement le type User par identifiant logique/contexte d'acteur, après tests de caractérisation.

### 3.6 Inventory — priorité P0 avant checkout complet

Inventory n'existe pas dans le code à cette référence ; sa spécification V1 existe. Elle confirme que checkout ne réserve pas de stock et que la réservation a lieu à la confirmation de la sous-commande.

Traitement requis :
1. Exposer à terme un contrat de lecture de disponibilité pour les lignes de checkout.
2. Sales peut bloquer le checkout si les quantités ne sont pas disponibles selon la règle validée, mais ne réserve rien.
3. Inventory reste seul propriétaire des soldes et réservations.
4. Contrat de confirmation et réservation atomique côté Inventory/Orders à concevoir séparément.
5. Le checkout ne peut pas être déclaré complet avant intégration et tests du contrat de disponibilité.

### 3.7 Delivery — priorité P0 si frais dans le total

Delivery n'existe pas encore dans le code. Le service Orders observé initialise les frais à 0.00.

Traitement requis :
1. Définir avec Delivery le contrat de devis de livraison avant de présenter un total final incluant ces frais.
2. Le devis doit préciser validité, devise, périmètre de commande et règles multi-coopératives.
3. Sales ne calcule pas lui-même les tarifs transporteur.
4. Si le tarif n'est pas disponible, ne pas afficher un total présenté comme final.

### 3.8 Finance et Communication — après création Orders

- Finance reçoit le fait de commande créée et demeure propriétaire des factures et opérations financières ; Sales ne crée pas de facture.
- Communication consomme des événements métier confirmés ; Sales ne doit pas envoyer directement les emails de confirmation de commande.
- COD reste le mode de paiement V1 validé ; toute évolution de paiement devra passer par Finance/contrat dédié.

## 4. Composition et providers

Fichiers inspectés :
- `bootstrap/providers.php` enregistre les providers Audit, Catalog, Categories, Cooperatives, Localization, Media, Products et Identity.
- Aucun provider Orders ou Sales n'est déclaré.
- `AppServiceProvider` contient des bindings de contrats appartenant à plusieurs domaines, dont `AuditWriter`, `CheckoutCatalog` et `CooperativeAddressManager`.

Écart ADR-021 :
- Les providers de domaine doivent enregistrer les bindings, policies, observers et listeners qui leur appartiennent.
- AppServiceProvider doit rester global et ne pas devenir le registre de tous les contrats métier.
- Le déplacement des bindings doit être fait avec tests de résolution du conteneur et tests de boot, sans double binding ni changement d'implémentation.

## 5. Matrice des dépendances et séquence de traitement

| Priorité | Domaine fournisseur | Contrat attendu | Blocage |
|---|---|---|---|
| P0 | Products | Lecture commerciale versionnée et diagnostic d'état | Panier fidèle aux règles d'indisponibilité/changement |
| P0 | Cooperatives | Lecture du statut propriétaire | Blocage/réactivation des lignes |
| P0 | Localization | Adresse client autorisée + snapshot DTO | Checkout avec adresse correcte |
| P0 | Identity | Contexte d'acteur/ID logique | Ownership sécurisé du panier |
| P0 | Orders | Création de commande DTO + idempotence | Checkout sans couplage |
| P0 | Inventory | Lecture disponibilité sans réservation | Validation disponibilité checkout |
| P0 | Delivery | Devis de frais et validité | Total final incluant livraison |
| P1 | Finance | Événement de commande créée / contrat financier si nécessaire | Facturation et opérations financières |
| P1 | Communication | Événements de commande consommés | Emails et notifications |

## 6. Ordre de correction recommandé

1. Établir les tests de caractérisation sur les services existants Products et Orders.
2. Créer et tester les contrats Products, Cooperatives et Localization.
3. Définir le contexte d'acteur Sales sans modèle User.
4. Créer le contrat Orders et son idempotence ; adapter le service derrière ce contrat.
5. Stabiliser Inventory et Delivery contracts avant le checkout complet.
6. Créer le domaine Sales, provider, DTO, modèles, migrations, services et routes par lots verticaux.
7. Ajouter tests d'architecture empêchant l'import des modèles privés d'autres domaines.
8. Exécuter tests ciblés, tests de contrats, tests de frontières, Pint et suite complète ; aucune clôture sans résultats réels.

## 7. Constats non exécutés / limites

- Audit statique des fichiers cités uniquement ; aucune exécution locale PHP ou MySQL n'a été possible dans cette session.
- Aucun PHPUnit, Pint, test de migration, test MySQL ou pipeline CI exécuté.
- Les migrations historiques n'ont pas été modifiées.
- Aucun code métier n'a été modifié dans le cadre de cet audit.
- Les points non lus ou non vérifiés ne sont pas déclarés conformes.
