-- Exécuté une seule fois, à la création du volume MySQL.
-- Base dédiée aux tests automatisés (Doctrine ajoute le suffixe _test en environnement test).
CREATE DATABASE IF NOT EXISTS mediatheque_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON mediatheque_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
