.DEFAULT_GOAL := help
.PHONY: help up down logs shell install migrate fixtures test-db test reset

DC  = docker compose
PHP = $(DC) exec php

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

up: ## Construit et démarre les conteneurs
	$(DC) up -d --build

down: ## Arrête les conteneurs
	$(DC) down

logs: ## Suit les logs
	$(DC) logs -f

shell: ## Ouvre un shell dans le conteneur PHP
	$(PHP) bash

install: ## Installe les dépendances Composer
	$(PHP) composer install

migrate: ## Applique les migrations
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Recharge les données de démonstration (EFFACE la base de dev)
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction

test-db: ## Prépare la base de test
	$(PHP) php bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration

test: test-db ## Lance les tests automatisés
	$(PHP) php bin/phpunit

reset: ## Repart de zéro (supprime la base de dev)
	$(DC) down -v
	$(DC) up -d --build
