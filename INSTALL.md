# Guide d'installation et de prise en main

**Projet PFE Challenge — Sujet 4 : Gestion d'un réseau de médiathèques (EMC)**  
Application web complète sous **Symfony 7.4 / PHP 8.3 / MySQL 8**, déployée avec **Docker Compose**.

---

## 1. Prérequis

* **Docker** et **Docker Compose v2** (recommandé : Docker Desktop sous Windows/Mac, ou Docker Engine sous Linux)
* **Git**
* Ports réseau disponibles sur votre machine hôte :
  * Port web : **8081** (http://localhost:8081)
  * Port MySQL : **3307** (accès base depuis l'hôte)  
  *(Ces ports sont modifiables dans `.env.local` en cas de conflit).*

---

## 2. Démarrage de l'application en 3 étapes

### Étape 1 : Cloner le dépôt et lancer les conteneurs

Ouvrez un terminal et exécutez :

```bash
git clone https://github.com/nassimatk/mediatheque.git
cd mediatheque
docker compose up -d --build
```

> Les conteneurs `mediatheque-php-1`, `mediatheque-nginx-1` et `mediatheque-database-1` démarrent en arrière-plan.

---

### Étape 2 : Initialiser la base de données et charger les données de test

Exécutez les deux commandes suivantes pour créer les tables et injecter les données de démonstration réalistes :

```bash
# 1. Exécuter les migrations MySQL
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

# 2. Charger les jeux de données (Fixtures)
docker compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
```

L'application est immédiatement prête à l'adresse : **[http://localhost:8081](http://localhost:8081)**.

---

## 3. Comptes de démonstration

La base est pré-remplie avec 3 comptes représentatifs des rôles du cahier des charges :

| Rôle | Identifiant (Email) | Mot de passe | Périmètre & Accès autorisés |
|---|---|---|---|
| **Administrateur** | `admin@mediatheque.ma` | `admin123` | Back-Office complet (`/admin`), pilotage des statistiques du réseau (`/admin/statistiques`), gestion des sites et des formules d'abonnement. |
| **Bibliothécaire** | `librarian@mediatheque.ma` | `librarian123` | **Comptoir de circulation** (`/comptoir`) : enregistrement des prêts, retours avec constat d'état (dégradé/perdu), réceptions de navette inter-sites, encaissement des pénalités, **import Open Library par ISBN** (`/comptoir/import-isbn`), consultation du catalogue en back-office (lecture seule). |
| **Adhérent** | `adherent@mediatheque.ma` | `adherent123` | Portail public (`/catalogue`), réservation de documents avec choix du site de retrait, **Espace adhérent** (`/espace-membre`) avec suivi des emprunts, historique des prêts et annulation de réservation. |

---

## 4. Navigation & Parcours recommandés pour l'évaluation

* **Portail public (Style éditorial)** : `http://localhost:8081`  
  *Page d'accueil avec recherche par mots-clés/catégories, dernières acquisitions, présentation des deux médiathèques (Centre-Ville et Quartier Nord) et services.*
* **Catalogue & Fiche document** : `http://localhost:8081/catalogue`  
  *Filtres par support (Livre, BD, DVD), statut de disponibilité en rayon, réservation en ligne pour les adhérents.*
* **Comptoir Bibliothécaire** : `http://localhost:8081/comptoir` *(nécessite le compte `librarian@mediatheque.ma`)*  
  *Guichet de circulation rapide au code-barres (prêts, retours, transits, encaissement direct des pénalités).*
* **Importation Open Library (Niveau 4)** : `http://localhost:8081/comptoir/import-isbn`  
  *Import unitaire ou par lot d'ISBN (ex: `9780140449136`, `9782070360024`), rapport détaillé par ligne et dédoublonnage automatique.*
* **Espace Adhérent** : `http://localhost:8081/espace-membre` *(nécessite le compte `adherent@mediatheque.ma`)*  
  *Prêts en cours, réservations prêtes ou en attente, bouton d'annulation et historique des prêts archivés.*
* **Statistiques Réseau (Niveau 3)** : `http://localhost:8081/admin/statistiques` *(nécessite le compte `admin@mediatheque.ma`)*  
  *Calcul du taux de rotation des collections ($\text{Prêts} \div \text{Exemplaires}$), Top 10 des sollicitations et liste des documents dormants.*

---

## 5. Exécution des tests automatisés

La totalité des tests unitaires et fonctionnels s'exécute dans l'environnement Docker :

```bash
docker compose exec -T php php bin/phpunit
```

**Résultat attendu :**
```text
OK (45 tests, 167 assertions)
```

---

## 6. Dépannage & Commandes utiles

* **Vider le cache Symfony en cas de modification** :
  ```bash
  docker compose exec -T php php bin/console cache:clear
  ```
* **Conflit de ports sur l'hôte** :  
  Si le port `8081` ou `3307` est déjà utilisé sur votre machine, créez un fichier `.env.local` à la racine :
  ```ini
  WEB_PORT=8085
  DB_PORT=3308
  ```
  Puis redémarrez : `docker compose up -d`.
* **Réinitialiser complètement la base de données** :
  ```bash
  docker compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
  ```
* **Repartir d'un environnement vierge** :
  ```bash
  docker compose down -v
  docker compose up -d --build
  docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
  docker compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
  ```
