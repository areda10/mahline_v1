# ADR-011 — Répartition de la responsabilité des autorisations

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** 11
- **Périmètre :** architecture cible MAHLINE V7.0
- **Portée :** décision d'architecture ; ne vaut pas implémentation

## 1. Contexte

MAHLINE doit disposer d'une gestion cohérente des comptes, rôles et permissions tout en préservant l'autonomie des domaines métier. Centraliser toutes les décisions d'accès dans Identity ferait dépendre ce domaine de l'ensemble des règles métier. À l'inverse, disperser sans coordination les rôles, affectations et permissions générales créerait des incohérences et des risques de sécurité.

Il faut distinguer l'identité et les habilitations générales des règles d'autorisation qui dépendent des ressources, du contexte métier et du périmètre détenu par chaque domaine.

## 2. Décision

**L'option C — approche hybride avec séparation des responsabilités — est adoptée.**

### 2.1 Responsabilités du domaine Identity

Identity est propriétaire de :
- la gestion des comptes et de leur cycle de vie ;
- l'authentification et les mécanismes de session ;
- les rôles et leurs affectations ;
- les permissions générales attribuées aux identités ;
- les mécanismes transversaux de vérification des habilitations et les contrats nécessaires aux autres domaines.

Identity ne devient pas propriétaire des règles métier internes des autres domaines.

### 2.2 Responsabilités de chaque domaine métier

Chaque domaine est propriétaire des règles d'autorisation qui nécessitent sa connaissance métier, notamment :
- l'accès à ses propres ressources ;
- les règles de propriété et de périmètre ;
- les restrictions liées à une coopérative ou à une affectation métier ;
- les transitions de statut et opérations permises ;
- les conditions particulières de consultation, création, modification, suppression ou validation.

Le domaine propriétaire définit ses Policies, règles, capacités ou contrats publics selon les besoins. Il ne doit pas exposer ses modèles ou détails internes pour permettre à un autre domaine de décider à sa place.

### 2.3 Coordination des décisions

- Les contrôleurs et points d'entrée demandent une autorisation via les mécanismes Laravel adaptés (Policies, Gates ou services d'application).
- Une décision peut combiner une permission générale issue d'Identity et une règle métier fournie par le domaine propriétaire de la ressource.
- La coordination ne transfère pas la propriété de la règle métier à Identity.
- Les refus doivent être appliqués par défaut en cas d'identité absente, de permission manquante, de contexte non vérifiable ou de contrat indisponible.
- Les vérifications doivent être effectuées côté serveur ; masquer un élément d'interface ne constitue jamais une autorisation.

### 2.4 Dépendances et contrats

- Les domaines consultent les permissions et informations d'identité via les contrats publics d'Identity.
- Identity ne doit pas importer les modèles, repositories, policies internes ou services métier privés des domaines consommateurs.
- Les domaines ne doivent pas accéder directement aux tables ou composants internes d'Identity.
- Les dépendances suivent les règles de l'ADR-009 : surfaces publiques explicites, dépendances orientées, documentées et sans cycle.
- Les règles d'autorisation ne doivent pas être dupliquées dans plusieurs domaines ; une règle possède un propriétaire clairement identifié.

## 3. Principes de sécurité

1. Refus par défaut lorsque les conditions d'autorisation ne sont pas établies.
2. Vérification côté serveur à chaque opération protégée, indépendamment de l'interface.
3. Séparation entre permission générale et autorisation contextuelle métier.
4. Respect strict du périmètre des ressources et de l'isolation entre coopératives.
5. Vérification des autorisations sur les opérations sensibles et les transitions de statut, pas uniquement sur l'accès à une page.
6. Ne pas faire confiance à un identifiant, rôle ou périmètre fourni par le client sans validation serveur.
7. Toute exception inter-domaines doit être justifiée, documentée et testée.
8. Les journaux d'autorisation ne doivent contenir ni mot de passe, ni jeton, ni secret, ni donnée sensible inutile.

## 4. Conséquences

### Positives

- Identity reste responsable de l'identité et des habilitations générales sans absorber toutes les règles métier.
- Les domaines conservent la maîtrise de la sécurité de leurs propres ressources.
- Les règles sont plus proches des données et opérations qu'elles protègent.
- Les contrats publics permettent de coordonner les décisions sans couplage aux détails internes.

### Contraintes

- Une action peut nécessiter la combinaison de plusieurs vérifications.
- Les contrats d'identité et les capacités métier doivent être stables, explicites et testés.
- Les audits doivent vérifier à la fois les permissions générales, les règles métier et les frontières coopératives.

## 5. Plan d'application

1. Inventorier les rôles, permissions, Gates, Policies, middleware et contrôles d'accès existants.
2. Classer chaque règle : identité/habilitation générale ou autorisation métier appartenant à un domaine.
3. Attribuer chaque règle à un propriétaire unique.
4. Définir les contrats publics nécessaires entre Identity et les domaines.
5. Vérifier les refus par défaut, les périmètres coopératifs et les opérations sensibles.
6. Ajouter des tests d'architecture et des tests fonctionnels de sécurité couvrant les accès autorisés et refusés.
7. Corriger les écarts uniquement après audit et approbation, sans déplacer prématurément les règles existantes.

## 6. Liens

- ADR-001 — Architecture modulaire évolutive.
- ADR-005 — Communication inter-domaines.
- ADR-009 — Contrôle des dépendances entre domaines.
- ADR-010 — Propriété et gestion des configurations des domaines.

## 7. État d'implémentation

La répartition des responsabilités est acceptée sur le plan architectural. L'inventaire des règles existantes, leur classification et la vérification de leur conformité restent à réaliser. Cet ADR ne déclare aucune Policy, Gate, permission ou route existante conforme. Aucun code, middleware, rôle, permission, table ou migration n'est modifié implicitement par cette décision.
