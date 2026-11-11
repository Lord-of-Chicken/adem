# CLAUDE.md — Ruelle d'Adem (Symfony)

Règles transverses (méthode, git, agents, style, mémoire) : `~/.claude/CLAUDE.md`. Ici, uniquement le spécifique au projet.
Jumeau : `AGENTS.md` (même contenu, garder les deux cohérents).

## Contexte
**Ruelle d'Adem** — plateforme de participation citoyenne belge pour soutenir Adem, jeune Bruxellois (Uccle) doublement élu « Bruxellois de l'Année », qui transforme sa ruelle en jardin fleuri. Les visiteurs contribuent (bégonias, jardinières, palettes) via des tiers Standard ou VIP (nom personnalisé).
Modules : tiers + panier + paiement Stripe · galerie médias · « Qui est Adem ? » · presse · FAQ · contact (Mailer) · newsletter (double opt-in) · profil + commandes · admin EasyAdmin 5 · FR/EN/NL.
Dépôt : `~/Dev/RuelleDAdem`, branche de travail `audit/backend-2026-10-06` (poussée, tête bba517d, 5 commits d'avance sur `main`, non mergée). Public : github.com/Lord-of-Chicken/adem. Production : OVH, NON DÉPLOYÉ depuis l'audit (voir `docs/DEPLOY-OVH-2026-10-07.md`).

## Stack
PHP ≥ 8.4 · Symfony 8.1 · MySQL 8.4 (Doctrine, DB dev `ruellenadem` via `docker compose up -d`) · AssetMapper + Stimulus + Turbo · Mailer · Stripe (stripe-php v20) · EasyAdmin 5 · PHPUnit + PHPStan niveau 10 (extensions doctrine/symfony).

## Architecture
- MVC : `src/Controller/`, `Entity/`, `Repository/`, `Form/`, `Service/` ; modules `src/Participation/` (catalogue), `src/Cart/` (`CartService`).
- Contrôleurs fins, logique en Services, persist/flush dans les Repositories. `declare(strict_types=1)` partout.
- Routes `#[Route]` avec préfixe `/{_locale}` (fr/en/nl), sauf sitemap, webhook, upload, root.
- Migrations : `Version{YYYYMMDDHHMMSS}.php`. Ne jamais rejouer les migrations historiques globalement ni le seeder galerie en production.

## Conventions clés
- `ParticipationCatalog` : tiers `standard` / `vip`, source de vérité en base (depuis 4c51e1d) ; EUR→cents via `eurToCents`.
- `CartService` : panier en session, totaux, quantités ; mémo uniquement pendant un calcul.
- `StripePaymentService` : checkout session, cart → line_items. `StripeWebhookController` : signature, idempotence (`stripe_processed_event`), `amount_total === Order.totalCents`, transaction atomique et reprenable ; `checkout.session.completed` → `paid`, `payment_intent.payment_failed` / `checkout.session.expired` → `failed`.
- `Order` (`OrderStatus` enum pending/paid/failed, `stripePaymentIntentId`, `User` nullable). Reçus : `payment_receipt` + `app:send-payment-receipts` (actif en dev seulement).
- RGPD : anonymisation au lieu de suppression de compte (commandes conservées 7 ans), consentement `donor_name`, 16+ à l'inscription, purge `app:newsletter:purge-expired`.

## Qualité (à passer avant tout lot)
`vendor/bin/phpstan analyse` (0 erreur) · `composer test` (recrée la base de test comme la CI — `schema:create` + `app:seed-participation-tiers`, jamais les migrations historiques — puis PHPUnit `--fail-on-skipped` ; 183 tests / 960 assertions au 2026-11-11, MySQL via `docker compose up -d database`) · `composer audit` · lint container/twig/yaml. Tests dans le même lot que la fonctionnalité.

## Skills
- Références projet locales `.claude/skills/*.md` : lire avant d'intervenir — `architecture`, `database`, `security`, `i18n`, `frontend`, `forms`, `file-upload`, `testing`, `performance`, `devops`, `notifications`, `DESIGN`.
- Skills Symfony UX / UI / infra : copies locales retirées (commit ddfd95d) ; ils vivent dans `~/.claude/skills/` (stimulus, turbo, twig-component, live-component, ux-icons, ux-map, symfony-ux, impeccable, design-taste-frontend, high-end-visual-design, minimalist-ui, redesign-existing-projects, image-to-code, imagegen-frontend-web, devops-engineer, performance). Les copier dans `.claude/skills/` (`cp -RL`) avant usage, puis les lire.
- Règles UX : `{{ attributes }}` sur le root d'un LiveComponent ; `<twig:Nom />` ; `<twig:ux:icon name="…" />` ; `data-model="debounce(300)|champ"` ; `ux:icons:lock` avant déploiement ; Stimulus : nettoyer dans `disconnect()` ; Turbo Frame pour 1 section, Stream pour plusieurs.

## Mémoire locale (`.claude/memory/`)
`session_current_state.md` (point de reprise en tête) · `tasks.md` (journal) · `decisions.md` · `action_plan.md` · `security_todo.md` · `perf_todo.md`.
