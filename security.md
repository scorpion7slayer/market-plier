# Politique de sécurité

## Signaler une vulnérabilité

Si vous découvrez une vulnérabilité de sécurité dans ce projet, merci de ne pas ouvrir d'issue publique. Contactez directement le mainteneur du projet.

## Dépendances surveillées

Ce projet utilise des dépendances tierces gérées via Composer. Les alertes Dependabot sont activées sur le dépôt GitHub.

## Historique des correctifs de sécurité

### 2026-03-29 — phpseclib/phpseclib CVE (CVSS 8.2 / High)

**Alerte** : Dependabot #1 — AES-CBC unpadding susceptible to padding oracle timing attack

**Package** : `phpseclib/phpseclib`
**Versions affectées** : `>= 3.0.0, <= 3.0.49`
**Version corrigée** : `3.0.50`

**Impact** : Les utilisateurs d'AES en mode CBC pouvaient être exposés à une attaque de type padding oracle par analyse de timing, permettant potentiellement une fuite de données confidentielles (Confidentiality: High).

**Correction appliquée** : Mise à jour de `phpseclib/phpseclib` vers `3.0.50` via `composer update phpseclib/phpseclib`.

**Référence** : [phpseclib/phpseclib@ccc21ae](https://github.com/phpseclib/phpseclib/commit/ccc21ae)
