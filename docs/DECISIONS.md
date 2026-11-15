# Journal des décisions

Chaque décision est datée et justifiée. Format : contexte, décision, raison.

## Décisions d'architecture

### D-001 — 2026-11-12 — Monolithe Symfony + Twig
- **Décision** : application unique rendue côté serveur (Twig), sans front séparé.
- **Raison** : le front est libre dans l'énoncé ; un front séparé doublerait le travail sans valeur ajoutée sur 4 jours.

### D-002 — 2026-11-12 — Règles métier dans `src/Service/`
- **Décision** : aucune règle métier dans les contrôleurs, templates ou le back-office.
- **Raison** : un seul endroit à tester et à expliquer ; contrôleurs fins.

### D-003 — 2026-11-12 — Back-office avec EasyAdmin
- **Décision** : utiliser EasyAdmin pour les écrans de gestion (niveau 1).
- **Raison** : gain de temps important pour des écrans CRUD standard.

### D-004 — 2026-11-12 — Workflow Git `main` / `dev`
- **Décision** : développement sur `dev`, fusion vers `main` par `merge --no-ff`, sans réécriture d'historique.
- **Raison** : `main` reste stable ; l'historique complet est exigé par l'énoncé.

### D-005 — 2026-11-12 — Environnement Docker
- **Décision** : PHP 8.3-FPM, Nginx, MySQL 8 via `compose.yaml` ; schéma géré uniquement par les migrations.
- **Raison** : installation reproductible en une commande (niveau 5).

### D-006 — 2026-11-13 — Version d'EasyAdmin figée à ^5.10
- **Décision** : EasyAdmin 5 (version installée), contrainte `^5.10` au lieu de `*`.
- **Raison** : éviter qu'une mise à jour majeure imprévue casse le back-office.

### D-007 — 2026-11-13 — Stratégie de test
- **Décision** : PHPUnit 11 (`symfony/test-pack`), base MySQL dédiée `mediatheque_test`, tests répartis en `Unit`, `Integration` et `Functional`.
- **Raison** : les règles métier du niveau 2 seront testées contre un vrai MySQL.

### D-008 — 2026-11-13 — Port web par défaut 8081
- **Décision** : l'application est exposée sur `localhost:8081`.
- **Raison** : le port 8080 est souvent déjà occupé sur les postes de développement ; il reste modifiable via `WEB_PORT` dans `.env`.

### Q1 — 2026-11-13 — Deux éditions d'un même roman sont-elles la même œuvre ?
- **Décision** : oui. `Work` est la création, `Edition` une publication précise (ISBN, éditeur, année, support). Une réédition, un poche ou une traduction sont des éditions de la même œuvre ; une adaptation (film) est une œuvre distincte.
- **Raison** : le prêt porte sur l'exemplaire, la réservation sur l'édition, et le catalogue regroupe par œuvre. Trois niveaux, trois besoins.

### Q2 — 2026-11-13 — Réserver un document disponible uniquement dans l'autre site ?
- **Décision** : oui. La réservation indique un site de retrait.
  - Si un exemplaire est déjà disponible sur le site de retrait : la réservation est refusée (l'adhérent doit simplement aller le chercher en rayon).
  - Si un exemplaire est disponible uniquement sur l'autre site : l'exemplaire est attribué à la réservation et passe immédiatement en `CopyStatus::InTransit` vers le site de retrait. La réservation reste au statut `ReservationStatus::Pending` avec `copy` renseigné pendant toute la durée du voyage.
  - Si aucun exemplaire n'est disponible sur le réseau : la réservation entre dans la file d'attente FIFO classique (`Pending` avec `copy IS NULL`).
- **Raison** : un réseau de médiathèques sert justement à partager les collections sans bloquer inutilement les documents déjà en rayon.

### Q3 — 2026-11-13 — Durée de mise de côté et cycle d'expiration
- **Décision** : 5 jours ouvrés à partir de la disponibilité physique sur le site de retrait, configurable via le paramètre `app.reservation_hold_days`.
  - Si le document est rendu sur le site de retrait : il passe immédiatement à `Reserved` et la réservation à `Ready` avec `holdUntil = now + holdDays`.
  - Si le document a voyagé depuis l'autre site : le délai de 5 jours commence **à l'arrivée physique** (`receiveTransit`), afin que le temps de transport ne pénalise pas l'adhérent.
  - Passé ce délai (`holdUntil < now`), la réservation passe à `Expired` (via `expireOutdatedReservations()` ou la commande CLI `app:reservations:expire`) et l'exemplaire est réattribué à la réservation suivante dans la file FIFO (avec mise en transit si besoin), ou remis en rayon (`Available`).
- **Raison** : équité pour l'adhérent (le voyage ne mange pas son délai de retrait) et fluidité de rotation des collections.

### D-009 — 2026-11-14 — Sécurisation CSRF des actions adhérent
- **Décision** : protection par jeton CSRF obligatoire sur les formulaires d'emprunt, de prolongation et de réservation.
- **Raison** : empêcher toute falsification de requête inter-sites (CSRF) sur les opérations modifiant l'état de la circulation.

### D-010 — 2026-11-14 — Forfaits de perte et dégradation configurables
- **Décision** : tarification forfaitaire de 50,00 MAD (5 000 centimes) pour perte et 20,00 MAD (2 000 centimes) pour dégradation, configurables via `app.penalty_lost_cents` et `app.penalty_damaged_cents`. En cas de perte, l'exemplaire n'étant pas restitué, son site de rattachement n'est pas modifié.
- **Raison** : éviter les montants en dur dans le code métier et garantir la cohérence géographique des exemplaires perdus.

### D-011 — 2026-11-14 — Découplage strict des réservations en transit dans la file FIFO
- **Décision** : `findNextPendingForEdition` filtre strictement les réservations avec `copy IS NULL` et départage par `id ASC`.
- **Raison** : éviter qu'une réservation déjà en transit reçoive un deuxième exemplaire lors du retour d'un autre document de la même édition.

### D-012 — 2026-11-14 — Rôles et contrôle d'accès granulaire (RBAC)
- **Décision** : Mise en place d'une hiérarchie de rôles Symfony (`ROLE_ADMIN` hérite de `ROLE_LIBRARIAN` et `ROLE_MEMBER`, `ROLE_LIBRARIAN` hérite de `ROLE_MEMBER`).
  - `ROLE_ADMIN` : Contrôle absolu sur l'application, gestion exclusive des formules d'abonnement (`SubscriptionPlan`) et des médiathèques (`Site`), accès aux statistiques du réseau.
  - `ROLE_LIBRARIAN` : Accès au comptoir de circulation (`/comptoir`) et accès en **lecture seule** au back-office EasyAdmin (`/admin`) via `ReadOnlyForLibrarianTrait` (actions `NEW`, `EDIT`, `DELETE` désactivées sur le catalogue et les usagers).
  - `ROLE_MEMBER` : Accès restreint à son espace personnel (`/espace-membre`) : consultation des prêts ouverts, historique des prêts passés, prolongation et annulation de réservations.
- **Raison** : Respecter le principe de moindre privilège. Les bibliothécaires opèrent au quotidien sans risque de supprimer des entités système ou de modifier des tarifs, réservés à la direction administrative.

### D-013 — 2026-11-14 — Comptoir de circulation dédié pour les bibliothécaires (`/comptoir`)
- **Décision** : Création d'une interface de guichet spécifique, rapide et intuitive pour les agents d'accueil, articulée en trois opérations :
  1. **Prêt express** : saisie carte adhérent + code-barres document avec contrôle automatique des quotas et pénalités.
  2. **Retour express** : saisie code-barres + médiathèque de retour + déclaration d'état (normal, dégradé, perdu avec calcul de pénalité).
  3. **Réception de transit** : validation de l'arrivée physique d'une navette, déclenchant le passage en statut `Ready` et la fenêtre de retrait de 5 jours.
  Elle intègre également un suivi en temps réel des navettes inter-sites en cours.
- **Raison** : Les bibliothécaires au contact du public ont besoin d'une interface de circulation fluide, manipulable au lecteur code-barres, bien plus directe que la navigation CRUD d'un back-office classique.

### D-014 — 2026-11-14 — Définition et calcul du taux de rotation des collections
- **Décision** : Le taux de rotation global du réseau est calculé selon la formule normalisée des bibliothèques :
  $$\text{Taux de rotation} = \frac{\text{Nombre total de prêts enregistrés}}{\text{Nombre total d'exemplaires physiques en circulation}}$$
  Le service `StatsService` fournit cet indicateur (arrondi à 2 décimales), complété par le Top 10 des éditions les plus sollicitées et la liste des documents dormants (ayant au moins 1 exemplaire en rayon mais 0 prêt enregistré).
- **Raison** : Fournir une mesure claire de la vitalité des collections pour orienter la politique documentaire (réassort des succès et désherbage des titres dormants).

### Q4 — 2026-11-13 — Auteurs multiples, traducteurs, illustrateurs
- **Décision** : table `Contributor` (la personne) et table de liaison `EditionContributor` portant un rôle (auteur, traducteur, illustrateur, réalisateur), unique par (édition, personne, rôle).
- **Raison** : plusieurs personnes par document, plusieurs rôles par personne, et un traducteur est propre à une édition.

### Q5 — 2026-11-13 — Open Library incomplet ou différent du catalogue
- **Décision** : le catalogue fait foi, l'import n'écrase jamais une édition existante (il la signale). Sans titre, l'import est rejeté. Si un champ secondaire manque, la fiche est créée avec le champ vide. L'import par lot produit un rapport par ISBN (créé, ignoré, échec).
- **Raison** : ne pas corrompre des données saisies par les bibliothécaires.

### D-015 — 2026-11-15 — Import Open Library sans exemplaires physiques automatiques
- **Décision** :
  1. L'import Open Library crée uniquement la fiche bibliographique : une `Work` (l'œuvre), une `Edition` (le livre avec son ISBN-13, son titre, son éditeur, son année et son URL de couverture), et les `Contributor` reliés via `EditionContributor` avec le rôle `ContributorRole::Author`.
  2. Aucun exemplaire physique (`Copy`) n'est créé automatiquement lors de l'import : le bibliothécaire ajoute ensuite les exemplaires en rayon via le back-office lorsqu'il reçoit les volumes physiques.
  3. L'ISBN-13 est l'identifiant pivot de référence du catalogue. Tout ISBN-10 saisi est validé puis converti en ISBN-13 avant toute recherche en base ou appel API.
  4. L'URL de couverture fournie par Open Library est stockée telle quelle en base ; aucune image binaire n'est téléchargée localement.
  5. Une œuvre (`Work`) portant exactement le même titre est réutilisée pour regrouper les différentes éditions sous une même œuvre.
- **Raison** : Séparation stricte entre catalogage bibliographique et inventaire physique des collections. Respect des préconisations EMC et conformité avec la réponse Q5.

## Journal des hypothèses

| Date | Hypothèse | Justification |
|---|---|---|
| 2026-11-13 | Montants en centimes de dirham, entiers | Pas d'erreur d'arrondi |
| 2026-11-13 | Le statut d'un prêt se déduit des dates (`closedAt`, `dueAt`) | Pas d'incohérence possible entre statut et dates |
| 2026-11-13 | Pas d'historique des transferts | Le sujet demande de faire circuler, pas de tracer |
| 2026-11-13 | Un exemplaire ne se prête qu'une fois à la fois : transaction avec verrou sur la ligne `Copy` | MySQL n'a pas de contrainte simple pour cela (testé au niveau 2) |
| 2026-11-13 | Pas de paiement en ligne, pas d'e-mails | Hors périmètre ; le statut de réservation suffit |
| 2026-11-14 | `/admin` et `/comptoir` protégés par rôles | `ROLE_ADMIN` (complet), `ROLE_LIBRARIAN` (comptoir + back-office lecture seule), `ROLE_MEMBER` (espace adhérent) |
| 2026-11-14 | Taux de rotation = prêts totaux ÷ exemplaires totaux | Formule standard d'évaluation de la vivacité des collections en bibliothèque |
| 2026-11-14 | Pas de réservation d'un document déjà en rayon | Priorité à l'emprunt direct sur place pour ne pas bloquer les rayons (Q2) |
| 2026-11-15 | Import Open Library : fiche bibliographique seule sans exemplaire physique | Séparation entre notice catalographique et gestion de stock physique en rayon (D-015) |

