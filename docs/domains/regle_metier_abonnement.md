# MAHLINE — Règle métier

## Abonnements, quotas et tarifs configurables

**Statut : FIGÉ — Architecture métier**
**Version : 1.0**

---

## 1. Principe général

MAHLINE fonctionne avec des abonnements attribués aux coopératives.

Chaque abonnement définit :

* un prix d'abonnement ;
* un nombre d'Admins inclus ;
* un nombre de Users inclus.

Les comptes dépassant les quotas inclus sont facturés séparément selon leur type.

Les **prix et quotas sont configurables**.

Ils ne doivent donc pas être codés en dur dans les services, contrôleurs ou modèles.

---

## 2. Plans actuellement proposés

La configuration commerciale actuelle est :

| Plan     | Prix mensuel | Admins inclus | Users inclus |
| -------- | -----------: | ------------: | -----------: |
| START    |       899 DH |             1 |            1 |
| PRO      |     1 599 DH |             1 |            2 |
| BUSINESS |     2 199 DH |             3 |            5 |

Ces valeurs constituent la **configuration commerciale actuelle de référence**.

Elles ne constituent pas des constantes immuables du système.

---

## 3. Tarification des comptes supplémentaires

Les comptes dépassant les quotas inclus sont facturés séparément.

Configuration actuelle :

| Type de compte       | Tarif supplémentaire actuel |
| -------------------- | --------------------------: |
| Admin supplémentaire |               199 DH / mois |
| User supplémentaire  |               139 DH / mois |

Ces tarifs sont également **configurables**.

Ils pourront être modifiés ultérieurement sans modification de l'architecture applicative.

Exemple :

```text
Admin supplémentaire
199 DH → 249 DH
```

ou :

```text
User supplémentaire
139 DH → 159 DH
```

Le changement doit être effectué au niveau de la configuration tarifaire et non dans le code métier.

---

## 4. Séparation entre règle métier et configuration commerciale

MAHLINE doit distinguer :

### Règle métier

La règle est :

> Une coopérative possède un abonnement définissant des quotas d'Admins et de Users. Les comptes dépassant ces quotas sont facturés selon le tarif correspondant à leur type.

### Configuration commerciale

La configuration contient actuellement :

```text
START
price = 899
included_admins = 1
included_users = 1

PRO
price = 1599
included_admins = 1
included_users = 2

BUSINESS
price = 2199
included_admins = 3
included_users = 5

additional_admin_price = 199
additional_user_price = 139
```

La configuration commerciale peut évoluer sans modifier la règle métier.

---

## 5. Formule de calcul

Le montant mensuel doit être calculé à partir de la configuration active.

```text
Base subscription price
+
Additional Admins × Additional Admin Price
+
Additional Users × Additional User Price
```

Les tarifs utilisés pour un calcul de facturation doivent correspondre à la configuration applicable à la période facturée.

---

## 6. Exemple

Configuration actuelle :

```text
START
899 DH
1 Admin inclus
1 User inclus

Admin supplémentaire = 199 DH
User supplémentaire  = 139 DH
```

Une coopérative possède :

```text
2 Admins
4 Users
```

Le calcul est :

```text
899 DH
+ 1 × 199 DH
+ 3 × 139 DH
= 1 515 DH / mois
```

---

## 7. Évolution future des tarifs

Les tarifs peuvent évoluer.

Exemple :

```text
Configuration actuelle

START = 899 DH
Admin supplémentaire = 199 DH
User supplémentaire = 139 DH
```

Nouvelle configuration commerciale :

```text
START = 999 DH
Admin supplémentaire = 249 DH
User supplémentaire = 159 DH
```

Le moteur métier reste identique.

Seules les données de configuration changent.

---

## 8. Historisation des tarifs

Lorsqu'un tarif est utilisé pour une facturation, le système doit conserver suffisamment d'informations pour connaître le tarif réellement appliqué à cette période.

Une modification future du tarif ne doit jamais modifier rétroactivement une facture déjà générée.

Exemple :

```text
Septembre
Admin supplémentaire = 199 DH

Octobre
Admin supplémentaire = 249 DH
```

Une facture de septembre doit rester calculée avec **199 DH**, même après le changement vers 249 DH.

---

## 9. Principes techniques

Les valeurs commerciales suivantes ne doivent pas être codées en dur :

```text
899
1599
2199
199
139
```

Elles doivent être stockées dans une configuration commerciale persistante.

Le code doit récupérer les valeurs applicables au moment du calcul.

---

## 10. Architecture conceptuelle

```text
Cooperative
    │
    └── Subscription
            │
            └── SubscriptionPlan
                    ├── name
                    ├── price
                    ├── included_admins
                    └── included_users


Billing / Finance
    │
    └── AdditionalAccountPricing
            ├── account_type = admin
            └── account_type = user
```

Le domaine Cooperative ne doit pas devenir responsable de la tarification.

Il doit seulement connaître :

* le plan de la coopérative ;
* les comptes de la coopérative ;
* l'utilisation des quotas.

Le domaine Finance/Billing est responsable :

* des prix ;
* du calcul de facturation ;
* des périodes ;
* des factures ;
* de l'historisation des tarifs.

---

## 11. Règle de référence MAHLINE

> **Les abonnements, les quotas inclus et les tarifs des comptes supplémentaires sont des données commerciales configurables.**
>
> **La mécanique métier est figée, mais les valeurs commerciales peuvent être modifiées ultérieurement sans modifier l'architecture ni le code métier.**
>
> **Toute facturation doit conserver le tarif effectivement appliqué à la période concernée afin d'empêcher toute modification rétroactive des factures.**

---

## 12. Configuration commerciale actuelle

À la date de définition de cette règle :

```text
START
899 DH / mois
1 Admin inclus
1 User inclus

PRO
1 599 DH / mois
1 Admin inclus
2 Users inclus

BUSINESS
2 199 DH / mois
3 Admins inclus
5 Users inclus

Admin supplémentaire
199 DH / mois

User supplémentaire
139 DH / mois
```

**Ces valeurs sont la configuration actuelle et non des constantes définitives.**
