CREATE DATABASE IF NOT EXISTS dbGestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbGestion;

CREATE TABLE IF NOT EXISTS operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_complet VARCHAR(255) NOT NULL,
    adresse VARCHAR(255),
    telephone VARCHAR(50),
    poids_or DECIMAL(10,2) NOT NULL,
    teneur_or VARCHAR(50),
    cent_pourcent DECIMAL(10,2),
    prix_total DECIMAL(12,2),
    date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);