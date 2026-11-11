# État de session — Ruelle d'Adem

## Point de reprise (2026-11-10, vérifié contre `git log`)
- Branche `audit/backend-2026-10-06`, tête `bba517d` (2026-10-21), poussée, 5 commits d'avance sur `main`, non mergée. Working tree : seuls `.mcp.json` (modifié) et `.agents/`, `.codex/`, `AGENTS.md` (non suivis) ; rien de lié aux lots ci-dessous.
- Dernier lot livré : `ddfd95d` perf backend (panier O(N), réordonnancement médias en un SELECT, reçu Stripe sans requête PI redondante ; 183 tests / 961 assertions, PHPStan 10) puis `bba517d` (symfony/expression-language 8.1.9, CVE). Détails : `docs/PERFORMANCE-2026-10-08.md`.
- Production OVH (https://www.ruelledadem.com) : **NON DÉPLOYÉ**. Blocage : SSH `ruellen@ssh.cluster121.hosting.ovh.net` refuse l'authentification (clé publique de ce Mac à autoriser par Gaël). Procédure et garde-fous : `docs/DEPLOY-OVH-2026-10-07.md` (ne pas lancer `deploy.sh` tel quel ; appliquer seulement `Version20261007120000`, table `payment_receipt` ; sauvegarde DB/config/médias avant).
- Prochaines étapes : (1) Gaël : renouveler le mot de passe SMTP OVH (P1, secret resté dans l'historique public) ; (2) clé SSH ou autre accès pour déployer ; (3) merge de la branche vers `main` sur accord.

## Bugs / risques connus
- Reçus de paiement au site : actifs en dev seulement (`.env.dev`) ; réception email réelle et paiement Stripe réel non vérifiés ; risque de doublon SMTP si crash après acceptation (documenté dans `docs/DEV-SLIDES-RECEIPTS-2026-10-07.md`).
- Perf OVH non mesurée ; santé, sauvegarde/restauration et cron de production non vérifiés.
- Décision produit ouverte : aucune (tiers = base depuis 4c51e1d). Textes légaux : placeholders éditeur/hébergeur/BCE/DPO et relecture EN/NL à faire par un humain.

## Historique condensé
- 2026-06-15/16 : audit global lots A→F (Stripe P0, sécurité, RGPD B2, tests, archi, perf, SEO IA), commit `c80095a` sur `audit/security-hardening`, mergé depuis (`e83fd7a`).
- 2026-10-06 : audit backend (`docs/AUDIT-BACKEND-2026-10-06.md`), CI GitHub sans déploiement.
- 2026-10-07 : slides dev (4/4 comme la prod) + reçus de paiement (`docs/DEV-SLIDES-RECEIPTS-2026-10-07.md`), commit `8e04c41`.
- 2026-10-08 : optimisations mesurées, commit `ddfd95d`.
- Environnement : DB dev via `docker compose up -d` (conteneur `ruelledadem-database-1`, port 3306).
