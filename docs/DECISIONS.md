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

### Q4 — 2026-11-13 — Auteurs multiples, traducteurs, illustrateurs
- **Décision** : table `Contributor` (la personne) et table de liaison `EditionContributor` portant un rôle (auteur, traducteur, illustrateur, réalisateur), unique par (édition, personne, rôle).
- **Raison** : plusieurs personnes par document, plusieurs rôles par personne, et un traducteur est propre à une édition.

### Q5 — 2026-11-13 — Open Library incomplet ou différent du catalogue
- **Décision** : le catalogue fait foi, l'import n'écrase jamais une édition existante (il la signale). Sans titre, l'import est rejeté. Si un champ secondaire manque, la fiche est créée avec le champ vide. L'import par lot produit un rapport par ISBN (créé, ignoré, échec).
- **Raison** : ne pas corrompre des données saisies par les bibliothécaires.

## Journal des hypothèses

| Date | Hypothèse | Justification |
|---|---|---|
| 2026-11-13 | Montants en centimes de dirham, entiers | Pas d'erreur d'arrondi |
| 2026-11-13 | Le statut d'un prêt se déduit des dates (`closedAt`, `dueAt`) | Pas d'incohérence possible entre statut et dates |
| 2026-11-13 | Pas d'historique des transferts | Le sujet demande de faire circuler, pas de tracer |
| 2026-11-13 | Un exemplaire ne se prête qu'une fois à la fois : transaction avec verrou sur la ligne `Copy` | MySQL n'a pas de contrainte simple pour cela ; sera testé au niveau 2 |
| 2026-11-13 | Pas de paiement en ligne, pas d'e-mails | Hors périmètre ; le statut suffit |
| 2026-11-13 | `/admin` protégé seulement au niveau 3 | Projet local sans données réelles |
