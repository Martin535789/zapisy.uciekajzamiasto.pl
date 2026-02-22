-- Schema bazy danych dla systemu zapisów na testy nart
-- Uruchom ten plik przed pierwszym wdrożeniem aplikacji
-- Kompatybilny z MySQL 5.7+ / MariaDB 10.2+

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Tabela uczestników
CREATE TABLE IF NOT EXISTS `participants` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `imie`        VARCHAR(100)     NOT NULL,
    `nazwisko`    VARCHAR(100)     NOT NULL,
    `adres`       VARCHAR(255)     NOT NULL,
    `miasto`      VARCHAR(100)     NOT NULL,
    `email`       VARCHAR(255)     NOT NULL,
    `telefon`     VARCHAR(20)      NOT NULL,
    `wiek`        TINYINT UNSIGNED NOT NULL,
    `wzrost`      SMALLINT UNSIGNED NOT NULL COMMENT 'Wzrost w cm',
    `waga`        DECIMAL(5,1)     NOT NULL COMMENT 'Waga w kg',
    `data_zapisu` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela użytkowników administracyjnych
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50)  NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domyślny administrator: login=admin, hasło=admin123
-- ZMIEŃ HASŁO PO PIERWSZYM LOGOWANIU!
-- Skrót bcrypt wygenerowany przez: password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12])
INSERT IGNORE INTO `admin_users` (`username`, `password`)
VALUES ('admin', '$2y$12$tETsUMvcdCI1viEKylkFG.TqqP/iTnnekhhLwk3R5vZO61AW3gj0S');

-- Aby zmienić hasło, wygeneruj nowy hash w PHP:
--   $hash = password_hash('NoweHaslo', PASSWORD_BCRYPT, ['cost' => 12]);
-- Następnie wykonaj w MySQL (wklejając wygenerowany hash jako ciąg znaków):
--   UPDATE admin_users SET password = '$2y$12$...' WHERE username = 'admin';
