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

## Réponses aux « Questions à trancher »

> À rédiger lors de la conception du modèle (étape 2).

1. Deux éditions d'un même roman sont-elles la même œuvre ? — *à trancher*
2. Un adhérent peut-il réserver un document disponible uniquement dans l'autre site ? — *à trancher*
3. Combien de temps un document réservé reste-t-il mis de côté une fois revenu ? — *à trancher*
4. Comment représenter les auteurs multiples, les traducteurs, les illustrateurs ? — *à trancher*
5. Que faire quand Open Library renvoie des données incomplètes ou différentes du catalogue ? — *à trancher*

## Journal des hypothèses

| Date | Hypothèse | Justification |
|---|---|---|
| | | |
