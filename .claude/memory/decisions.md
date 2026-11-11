# Décisions techniques — Ruelle d'Adem

- **Architecture** : MVC classique (pas DDD/CQRS), contrôleurs fins, modules `src/Cart` et `src/Participation`. Le dépôt a hébergé « Animaux Perdu » jusqu'au 2026-05-14 : ses anciennes décisions ne s'appliquent plus.
- **Stripe (2026-06-15)** : signature + idempotence (`stripe_processed_event`) + `amount_total` vs `Order.totalCents` ; montants libres 1 €–100 000 €, `donor_name` ≤ 255 ; échecs → `failed`.
- **En-têtes (2026-06-15)** : `SecurityHeadersListener` (X-Frame-Options, nosniff, Referrer-Policy, HSTS, CSP avec `'unsafe-inline'` assumé tant que des scripts inline existent).
- **Tiers** : source de vérité en base (4c51e1d), plus le YAML.
- **Audit backend (2026-10-06)** : PHPStan 10 sans ignorances (extensions doctrine/symfony), GD requis pour les uploads, tests sur MySQL jetable avec mappings plutôt que migrations historiques, CI sans déploiement.
- **Reçus (2026-10-07)** : une notification par commande dans `payment_receipt`, créée dans la transaction du paiement ; SMTP après commit, reprise par commande ; données payeur relues chez Stripe ; doublon possible après crash SMTP (documenté) ; activation dev seulement.
- **Optimisations (2026-10-08)** : mesurer avant/après, trois goulots retenus (panier, médias, reçu Stripe) ; contrats, cache galerie 3600 s et visuels inchangés ; pas de cache durable ni de migration. Preuves : `docs/PERFORMANCE-2026-10-08.md`.
