# Note conceptuelle : gestion d'un réseau de médiathèques
**PFE Challenge EMC · Sujet 4 · Nassima Taikouk · 15 novembre 2026 · Dépôt : [github.com/nassimatk/mediatheque](https://github.com/nassimatk/mediatheque)**

---

## 1. Pourquoi ce sujet
J’ai comparé les quatre sujets selon la difficulté technique, le risque de blocage et la possibilité de livrer un travail complet dans le délai. Les trois autres cumulaient des difficultés lourdes : disponibilité par tronçons de trajet (covoiturage), recettes imbriquées et conversions d’unités (restaurant), plan de salle interactif et API Google (billetterie). La médiathèque offre un domaine que chacun comprend, des règles métier riches mais bornées, et une API externe simple et sans clé (Open Library). J’ai préféré un projet complet, testé et défendable à un projet ambitieux et inachevé.

---

## 2. Compréhension et découpage du besoin
J'ai lu les cinq niveaux comme des couches cumulatives et j'ai d'abord tranché les « questions à trancher », car elles conditionnent le modèle : une œuvre regroupe plusieurs éditions ; la réservation porte sur l'édition, pas sur l'exemplaire ; un adhérent peut réserver un document disponible seulement sur l'autre site (l'exemplaire passe alors en transit) ; un document réservé reste 5 jours calendaires de côté à partir de son arrivée au site de retrait ; les contributeurs sont liés à l'édition avec un rôle (auteur, traducteur, illustrateur) ; si Open Library est incomplet ou diffère du catalogue, le catalogue local fait foi. Chaque décision est datée dans `docs/DECISIONS.md`.

---

## 3. Grands choix de conception et d'architecture
* **Monolithe Symfony 7.4 + Twig + MySQL 8** : le front est libre ; un front séparé n'aurait apporté aucun gain.
* **Règles métier uniquement dans `src/Service/`** (`LoanService`, `ReservationService`, `StatsService`, `IsbnImporter`) ; contrôleurs fins ; back-office EasyAdmin en lecture seule sur prêts, réservations et pénalités, pour qu'aucun écran ne contourne les règles.
* **Modèle Œuvre → Édition → Exemplaire** : le prêt porte sur l'exemplaire ; montants en centimes ; compte (`User`, rôles hiérarchisés) distinct du dossier adhérent (`Member`).
* **Intégrité** : transactions et verrou pessimiste sur l'exemplaire contre les prêts simultanés ; jetons CSRF sur toutes les actions qui modifient des données.
* **Open Library** : sans clé, paquets de 20 ISBN, aucun appel pour un ISBN déjà connu, rapport par ligne.
* **Livraison** : Docker Compose (PHP-FPM, Nginx, MySQL), migrations automatiques, fixtures en une commande, `INSTALL.md`.

---

## 4. Ordre d'avancement
Le modèle et les migrations d'abord, car ils sont le socle de tous les niveaux ; puis les fixtures réalistes et le back-office (niveau 1). Les services de circulation et leurs tests sont venus avant toute interface (niveau 2), pour que les écrans ne dupliquent pas les règles. Ensuite la sécurité, le catalogue public, l'espace adhérent, le comptoir bibliothécaire et les statistiques (niveau 3). Open Library est venu en dernier parmi les fonctions, car isolé derrière un service (niveau 4). L'environnement Docker a été posé dès le squelette et finalisé à la fin avec la documentation (niveau 5). Développement sur la branche `dev`, fusion dans `main` avec `--no-ff`, sans réécriture de l'historique.

---

## 5. Place de l'IA
J’ai utilisé ChatGPT et claude comme assistant tout au long du projet : analyse du sujet, comparaison des sujets, conception du modèle. Je n’ai rien accepté sans le relire, l’exécuter et le tester (plus de 40 tests automatisés). Les relectures critiques ont fait apparaître des défauts réels : trois bugs dans la file des réservations (un exemplaire en transit captant deux exemplaires, une annulation laissant l’exemplaire attribué, une double attribution à l’expiration), des formulaires sans jeton CSRF et une configuration de tests assouplie. Je les ai fait corriger et couvrir par des tests. Les traces sont dans l’archive jointe.

Les propositions générées par l'IA ont été utilisées comme des pistes d'aide et non comme des solutions automatiquement acceptées. Les choix finaux ont été confrontés à l'architecture existante, aux exigences du sujet et aux règles métier du projet. Les modifications ont ensuite été implémentées et vérifiées à l'aide des tests et des exécutions de l'application. Cette utilisation a également mis en évidence certaines limites de l'IA : les propositions de code peuvent nécessiter des adaptations pour correspondre aux entités, aux méthodes et à la configuration réelles du projet. Il est donc indispensable de vérifier chaque suggestion avant son intégration. En définitive, l'IA a permis de gagner du temps dans la recherche de solutions, la compréhension des problèmes et la préparation des tests, tout en laissant à l'équipe la responsabilité de la conception, des décisions techniques, de l'implémentation et de la validation finale.

---

## 6. Avec une semaine de plus
Un test d'intégration sur MySQL pour la circulation, les pénalités et la file FIFO a pu être finalisé et validé (`tests/Integration/CirculationIntegrationTest.php`) ; avec une semaine de plus, le travail porterait sur : la planification automatique de l'expiration des réservations et des notifications par e-mail ; un import Open Library plus robuste (reprise après erreur partielle) ; des index et des tests de charge pour 8 000 documents et 1 500 adhérents ; une intégration continue GitHub Actions.
