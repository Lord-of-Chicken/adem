# Tâches (journal) — Ruelle d'Adem

## 2026-06-15 — Audit global + corrections P0/P1/P2
- **composer** : 5 paquets patchés (CVE), `composer audit` propre.
- **Stripe** : anti-falsification montant, idempotence webhook (`StripeProcessedEvent` + migration `Version20260615120000`), plafonds prix libre, statuts failed/expired, champ `stripePaymentIntentId`.
- **Sécurité** : path traversal admin translations (whitelist fr/en/nl), `SecurityHeadersListener`, `security_todo.md`.
- **Bugs runtime** : crash reset-password (guard null), `ProfileController` getUser() null, `StripePaymentService` payment_status null + catch typé, email reset silencieux → log+flash.
- **PHPStan** : ignoreErrors 29→16, `reportUnmatchedIgnoredErrors: true`, 0 erreur.
- **i18n** : About EN/NL traduit (était en FR), labels en dur → clés, Stimulus disconnect, logo fetchpriority.
- **SEO** : sitemap multilingue + hreflang + priorities, canonical/og:url propres, JSON-LD Product/Offer.

## Reste à faire
Voir `action_plan.md` : Lot D (archi) en cours, puis B (RGPD/légal), C (tests), E (perf), F (front).

## État dépôt
Working tree = transformation « Animaux Perdu » → « Ruelle d'Adem » non commitée (447 suppressions src/ + reconstruction) depuis le commit 2026-05-14. À committer comme jalon séparé des correctifs d'audit.
