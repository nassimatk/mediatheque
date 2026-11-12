# Modèle de données

> **Version initiale (brouillon)** : sera affinée à l'étape de conception, puis alignée sur les entités réelles.

## Diagramme

```mermaid
erDiagram
    SITE ||--o{ COPY : "héberge"
    WORK ||--|{ EDITION : "possède"
    EDITION ||--o{ COPY : "est matérialisée par"
    EDITION ||--o{ EDITION_CONTRIBUTOR : "implique"
    CONTRIBUTOR ||--o{ EDITION_CONTRIBUTOR : "participe"
    USER ||--o| MEMBER : "peut être"
    SUBSCRIPTION_PLAN ||--o{ MEMBER : "fixe les règles de"
    MEMBER ||--o{ LOAN : "emprunte"
    COPY ||--o{ LOAN : "est prêté dans"
    MEMBER ||--o{ RESERVATION : "réserve"
    EDITION ||--o{ RESERVATION : "est réservée"
    LOAN ||--o{ PENALTY : "peut générer"
    COPY ||--o{ COPY_TRANSFER : "circule via"
```

## Glossaire FR ↔ code

| Français | Code |
|---|---|
| Médiathèque (site) | `Site` |
| Œuvre | `Work` |
| Édition | `Edition` |
| Exemplaire | `Copy` |
| Adhérent | `Member` |
| Formule d'abonnement | `SubscriptionPlan` |
| Prêt | `Loan` |
| Réservation | `Reservation` |
| Pénalité | `Penalty` |
| Transfert | `CopyTransfer` |

## Choix de conception

À compléter au fil de la conception (voir aussi `DECISIONS.md`).
