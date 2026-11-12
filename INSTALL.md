# Guide d'installation

> Squelette : sera finalisé et testé sur une machine propre au niveau 5.

## Prérequis

- Docker et Docker Compose v2
- Git
- Ports libres : 8080 (web) et 3307 (MySQL), modifiables dans `.env.local`

## Installation

```bash
git clone <URL_DU_DEPOT>
cd mediatheque
docker compose up -d --build
```

## Données de démonstration

À compléter (commande de chargement des fixtures).

## Comptes de démonstration

À compléter (adhérent, bibliothécaire, administrateur).

## Lancer les tests

```bash
docker compose exec php php bin/phpunit
```

## Dépannage

- **Port déjà utilisé** : changer `WEB_PORT` ou `DB_PORT` dans `.env.local`.
- **Erreur de permissions sur `var/` (Linux)** : `docker compose exec php chmod -R a+rwX var`.
- **Repartir de zéro** : `docker compose down -v && docker compose up -d --build`.
