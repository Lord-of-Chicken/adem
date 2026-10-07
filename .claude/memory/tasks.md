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


## Audit backend 2026-10-06
Corrections implémentées et suites étendues : paiements/atomicité, images, prix, session de retour, validation, newsletter/anonymisation/logs, configuration/DevOps. Preuves et limites dans docs/AUDIT-BACKEND-2026-10-06.md. Vérification finale, couverture et commit/push en cours.

Validation finale locale : 128 tests/567 assertions, PHPStan 10 sans ignorances, Composer strict/audit, schéma/configuration, couverture 89,27 % lignes ; publication/CI distantes à vérifier.

Publication code fb7178b confirmée après accord explicite dépôt public ; CI GitHub 37501405009 réussie. Aucune publication du site OVH ni modification de production.

## 2026-10-07 — Slides dev et reçus

Quatre slides publics de production repris dans le dev, anciennes photos retirées avec récupération. Reçus détaillés au site : notification durable, transaction atomique avec paiement, reprise et concurrence testées. Validation finale 172 tests/718 assertions, PHPStan10, qualité globale. Commit/push maintenant autorisés ; pas de déploiement. Voir docs/DEV-SLIDES-RECEIPTS-2026-10-07.md.
