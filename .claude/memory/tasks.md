# Tâches (journal) — Ruelle d'Adem

- **2026-06-15** — Audit global : composer (CVE) ; Stripe (anti-falsification montant, idempotence webhook, plafonds, statuts failed/expired) ; sécurité (path traversal admin, `SecurityHeadersListener`) ; bugs runtime ; PHPStan ; i18n ; SEO (sitemap/hreflang/JSON-LD).
- **2026-06-16** — Lots B (RGPD : pages légales, Consent Mode v2, anonymisation, double opt-in, 16+), C (tests, `docs/TESTING.md`), D (archi : persist/flush hors contrôleurs, `nom_entite` droppée), E (enum `OrderStatus`, cache galerie, OG allégé), F (SVG, Stimulus, `llms.txt`, JSON-LD FAQ). Commit `c80095a`.
- **2026-10-06** — Audit backend : paiements atomiques, uploads, prix, newsletter/anonymisation, DevOps/CI (`docs/AUDIT-BACKEND-2026-10-06.md`). Commit `fb7178b`, CI verte.
- **2026-10-07** — Slides dev (4/4) + reçus de paiement au site. Commit `8e04c41` (`docs/DEV-SLIDES-RECEIPTS-2026-10-07.md`). Déploiement OVH tenté, non réalisé (SSH).
- **2026-10-08/21** — Optimisations panier/médias/reçus Stripe (183 tests, PHPStan 10) : `ddfd95d` ; skills locaux retirés ; CVE expression-language : `bba517d`.
