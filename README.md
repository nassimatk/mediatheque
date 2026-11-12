# Réseau de médiathèques : application de gestion

Projet réalisé pour le **PFE Challenge d'Education Media Company** (sujet 4 : gestion d'un réseau de médiathèques).
Application web Symfony 7.4 / MySQL 8, livrée avec un environnement Docker.

## Avancement

| Niveau | Thème | État |
|---|---|---|
| 1 | Modéliser (modèle, migrations, fixtures, back-office) | ⬜ à faire |
| 2 | Règles métier et tests | ⬜ à faire |
| 3 | Produit (rôles, interface, statistiques) | ⬜ à faire |
| 4 | API externe (Open Library) | ⬜ à faire |
| 5 | Livraison (Docker, INSTALL.md) | 🟨 en cours |

> Ce tableau est mis à jour à chaque étape validée.

## Stack

- PHP 8.3, Symfony 7.4, Doctrine ORM + migrations
- MySQL 8
- Twig (front rendu côté serveur)
- PHPUnit (tests automatisés)
- Docker Compose (PHP-FPM, Nginx, MySQL)

## Lancer le projet

Le guide complet est dans [`INSTALL.md`](INSTALL.md). En résumé :

```bash
docker compose up -d --build
```

Application : http://localhost:8080

### Première installation du squelette Symfony (une seule fois)

```bash
docker compose run --rm --no-deps php sh -c "composer create-project symfony/skeleton:^7.4 /tmp/sk --no-interaction --no-scripts && cp -an /tmp/sk/. /app/ && composer config extra.symfony.docker false && composer install --no-interaction"
```

## Organisation du dépôt

| Dossier | Rôle |
|---|---|
| `src/Controller/` | Contrôleurs fins, un sous-dossier par public (Admin, Librarian, Member, Public) |
| `src/Service/` | **Règles métier** (circulation, catalogue, statistiques, intégration Open Library) |
| `src/Entity/`, `src/Repository/` | Modèle de données Doctrine |
| `src/Enum/`, `src/Exception/` | Statuts typés et erreurs métier |
| `templates/` | Vues Twig |
| `tests/` | Tests Unit / Integration / Functional |
| `migrations/` | Historique du schéma MySQL |
| `docker/` | Configuration des conteneurs |
| `docs/` | `MODELE.md` et `DECISIONS.md` |

## Workflow Git

- `main` : versions stables et validées uniquement.
- `dev` : tout le développement.
- Fusion `dev` → `main` avec `git merge --no-ff` : **jamais** de squash, rebase ni `push --force`.
- Commits courts et ciblés, format `type(portée): message` (`feat`, `fix`, `test`, `docs`, `chore`, `refactor`).
- Le code produit avec l'aide d'une IA est commité comme le reste et signalé par un trailer `Co-authored-by:`.

## Documentation

- [Modèle de données](docs/MODELE.md)
- [Journal des décisions](docs/DECISIONS.md)
