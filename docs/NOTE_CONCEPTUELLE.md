# Note Conceptuelle — Gestion d'un Réseau de Médiathèques
**PFE Challenge — Education Media Company (EMC) — Sujet 4**  
*Auteure : Nassima Taikouk | Date : 15 Novembre 2026 | Version : Finale (Niveaux 1 à 5)*

---

## 1. Contexte & Vision Métier

Le projet répond aux exigences d'Education Media Company pour la gestion informatisée et mutualisée d'un réseau de lecture publique composé de deux établissements de quartier : la **Médiathèque Centre-Ville** et la **Médiathèque Quartier Nord (Al Amal)**. 

Le système unifie le catalogue des collections, met en œuvre une carte d'adhérent unique valable sur tout le réseau, automatise les transferts physiques par navette inter-sites et garantit une gestion rigoureuse des flux de circulation (emprunts, retours, réservations et pénalités forfaitaires).

---

## 2. Modèle de Données & Architecture Applicative

•Monolithe Symfony 7.4 + Twig : le front est libre dans l’énoncé ; un front séparé aurait doublé le travail sans gain.
•Règles métier uniquement dans src/Service/ : contrôleurs fins, aucune règle dans les vues ni dans le back-office, donc un seul endroit à tester.
•Œuvre → édition → exemplaire : le prêt porte sur l’exemplaire, la réservation sur l’édition. Le statut d’un prêt se déduit des dates ; les montants sont en centimes.
•Concurrence : verrou pessimiste (SELECT … FOR UPDATE) sur l’exemplaire, dans une transaction, pour empêcher deux prêts simultanés.
•Sécurité : hiérarchie de rôles et jeton CSRF sur toutes les actions qui modifient des données.
•API externe : sans clé ; User-Agent identifié, paquets de 20 ISBN, aucun appel pour un ISBN déjà connu. Le catalogue fait foi : un import n’écrase jamais une fiche existante.
•Traçabilité : décisions et hypothèses datées dans docs/DECISIONS.md (D-001 à D-015, réponses aux 5 questions) ; modèle dans docs/MODELE.md.

* **Modèle documentaire en trois niveaux (inspiré de FRBR)** :
  * **`Work` (Œuvre)** : entité intellectuelle abstraite (titre original, genre), permettant de regrouper plusieurs publications sous une même notice.
  * **`Edition` (Édition)** : notice catalographique matérielle (ISBN-13 de référence, éditeur, année de parution, type de support, URL de couverture).
  * **`Copy` (Exemplaire)** : volume physique concret situé dans les rayons d'un site précis, doté d'un code-barres univoque (`MED-xxxxxx`) et d'un cycle d'états (`Available`, `Borrowed`, `Reserved`, `InTransit`, `Damaged`, `Lost`).
* **Découplage Compte / Lecteur (`User` vs `Member`)** :  
  L'authentification et les habilitations de sécurité (`User`, rôles RBAC hiérarchisés `ROLE_ADMIN`, `ROLE_LIBRARIAN`, `ROLE_MEMBER`) sont strictement disjointes du dossier d'inscription (`Member`, numéro de carte, formule d'abonnement, pénalités).
* **Contrôleurs fins et encapsulation des règles métier** :  
  100 % des invariants métiers résident dans le dossier `src/Service/` (`LoanService`, `ReservationService`, `StatsService`, `IsbnImporter`). Le back-office EasyAdmin est bridé en lecture seule sur les prêts, réservations et pénalités (**D-016**) pour interdire tout contournement manuel des services.

---

## 3. Choix Techniques Majeurs & Résolution des Problématiques Clés

1. **Anti-concurrence et intégrité des prêts (Niveau 2)** :  
   Pour éradiquer tout risque de double prêt concurrent d'un même document physique par deux bibliothécaires simultanés, `LoanService::checkout` opère au sein d'une transaction avec **verrouillage pessimiste en écriture (`PESSIMISTIC_WRITE`)** sur la ligne `Copy`.
2. **File d'attente FIFO équitable & Navette inter-sites (Niveau 2 & 3)** :  
   Les réservations sont gérées selon un ordre strict de premier arrivé, premier servi (`createdAt ASC`). Lors de la restitution d'un document au comptoir, le système détecte s'il est attendu : si le site de retrait souhaité diffère du site de restitution, l'exemplaire bascule immédiatement en transit (`InTransit`). À son arrivée physique au comptoir récepteur, une fenêtre de retrait de **5 jours calendaires** est amorcée.
3. **Comptoir de circulation & Rôles RBAC (Niveau 3)** :  
   L'interface dédiée `/comptoir` offre aux bibliothécaires des opérations rapides au lecteur code-barres (prêt, retour avec constat d'avarie/perte, réception de navette et encaissement direct des pénalités). Les adhérents disposent de leur propre espace en ligne avec annulation de réservation et historique complet de leurs prêts.
4. **Importation externe résiliente Open Library (Niveau 4)** :  
   L'API *Open Library Books* est interrogée par paquets de 20 ISBN sans secret ni clé d'API. Le **catalogue local fait foi** (les ISBN existants sont ignorés sans écrasement), les ISBN-10 sont convertis en ISBN-13 avec validation préalable de la clé de contrôle, et la fiche bibliographique est créée sans générer d'exemplaires physiques arbitraires (**D-015**).

---

## 4. Métriques de Pilotage & Fiabilité

* **Taux de rotation normalisé** : calculé selon le standard des bibliothèques ($\text{Prêts totaux} \div \text{Exemplaires totaux}$), enrichi par l'identification des documents dormants (0 prêt) pour guider le réassort ou le désherbage.
* **Assurance qualité** : **45 tests automatisés (167 assertions)** exécutés sous PHPUnit, couvrant l'ensemble des cas nominaux et limites (concurrence, pénalités, lots Open Library et mocks HTTP étanches au réseau réel).
* **Déploiement conteneurisé** : conteneurs Docker légers (Nginx 1.27, PHP 8.3-FPM, MySQL 8) démarrables en une seule commande documentée.


## 5. Place de l’IA

* J’ai utilisé Chatgpt comme assistant tout au long du projet : analyse du sujet, comparaison des sujets, conception du modèle. Je n’ai rien accepté sans le relire, l’exécuter et le tester (plus de 40 tests automatisés). Les relectures critiques ont fait apparaître des défauts réels : trois bugs dans la file des réservations (un exemplaire en transit captant deux exemplaires, une annulation laissant l’exemplaire attribué, une double attribution à l’expiration), des formulaires sans jeton CSRF et une configuration de tests assouplie. Je les ai fait corriger et couvrir par des tests. Les traces sont dans l’archive jointe.Les propositions générées par l'IA ont été utilisées comme des pistes d'aide et non comme des solutions automatiquement acceptées. Les choix finaux ont été confrontés à l'architecture existante, aux exigences du sujet et aux règles métier du projet. Les modifications ont ensuite été implémentées et vérifiées à l'aide des tests et des exécutions de l'application.
Cette utilisation a également mis en évidence certaines limites de l'IA : les propositions de code peuvent nécessiter des adaptations pour correspondre aux entités, aux méthodes et à la configuration réelles du projet. Il est donc indispensable de vérifier chaque suggestion avant son intégration.
En définitive, l'IA a permis de gagner du temps dans la recherche de solutions, la compréhension des problèmes et la préparation des tests, tout en laissant à l'équipe la responsabilité de la conception, des décisions techniques, de l'implémentation et de la validation finale.