-- Migration: Ajout de la colonne auth_token pour remplacer user_id dans la session
-- Date: 2026-02-12
-- Description: Amélioration de la sécurité en utilisant un token aléatoire au lieu de l'ID auto-incrémenté

ALTER TABLE users ADD COLUMN auth_token VARCHAR(64) UNIQUE NOT NULL;

-- Index pour optimiser les recherches par token
CREATE INDEX idx_auth_token ON users(auth_token);
