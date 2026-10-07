# Reprise dev — 2026-10-07

Priorité actuelle : exclusivement dev ; seuls les quatre visuels de slides ont été repris en lecture seule de production. Commit et push explicitement autorisés ensuite par l’utilisateur (« commit et push ») le 7 octobre ; publication en cours sur audit/backend-2026-10-06, aucun déploiement. Les modifications préexistantes du header et les suppressions de skills restent préservées.

Slides corrigés et vérifiés : dev auparavant sans média ; maintenant 4/4 publiés, mêmes fichiers et ordre que la production. Après demande explicite de suppression puis continuation, 15 anciens fichiers et 11 anciennes entrées retirés avec sauvegarde vérifiée dans var/recovery/dev-slides-2026-10-07 (ignorée Git). Quatre nouvelles sources remplacent celles du seeder ; son exécution conserve 4/4.

Nouvelle exigence : reçu de chaque paiement confirmé à info@ruelledadem.com. Implémenté : notification durable unique, transaction avec paiement, envoi après commit, reprise app:send-payment-receipts, détails Stripe vérifiés et lien officiel. Migration additive appliquée exclusivement dev/test isolé. SMTP effectif OVH ssl0.ovh.net:465 vérifié en TLS/authentification sans email ; Stripe local en mode test ; .env.dev active les notifications pour les futurs paiements de test. Livraison générale/prod et tests isolés désactivés par défaut ; réception email et API/paiement Stripe réels non vérifiés. Risque résiduel de doublon SMTP en cas de crash après acceptation documenté.

Contrôles globaux exécutés : 172 tests/718 assertions, PHPStan10 sans erreur, Composer strict/audit et schémas dev/test synchronisés. Détails/preuves/activation : docs/DEV-SLIDES-RECEIPTS-2026-10-07.md. P1 confirmé : ancien secret SMTP commenté identique au compte actif (auth réussie) ; commentaire retiré mais historique public inchangé. Renouvellement urgent OVH par propriétaire et mise à jour configuration privée, sans divulgation du secret. SSH OVH jamais connecté (clé d'hôte inconnue), aucune production modifiée.

--- État précédent conservé ---

# Reprise audit backend — 2026-10-06

Demande active : audit/corrections backend seulement, PHPUnit manquant, PHPStan complet, DevOps, puis commit/push autorisés après validation. Production interdite. Le texte joint remplace les anciens ordres de commit.
Dépôt /Users/gaellayeux/Dev/RuelleDAdem, main, origin public https://github.com/Lord-of-Chicken/adem.git.
Préexistant à préserver/exclure : suppressions .claude/skills et header templates/components/SiteHeader.html.twig.
Plan/preuves : docs/AUDIT-BACKEND-2026-10-06.md. Skills sécurité, revue PHP, qualité Symfony et DevOps lus. Dépendances ajoutées seulement phpstan-doctrine/phpstan-symfony.
Corrections déjà écrites : transaction webhook/lock/rollback, routes webhook/upload racine, validation upload partagée, prix forfaitaire, retour paiement lié session/snapshot/statut, contraintes formulaires, reset public uniforme/logs sans PII, désinscription révoque pending sans inventer consentement, cache invalidé après flush, MySQL bind localhost, hosts prod configurables.
DevOps écrit : CI sans déploiement, scripts qualité/package/deploy explicite/backup/restore isolé, health, exemples cron/worker et DEPLOYMENT.md réécrit.
Validation initiale 20 tests/63 assertions ; dernier contrôle complet 128 tests/567 assertions, zéro skip. PHPStan niveau 10 sans ignorances ni erreurs. Composer strict/audit et schéma dédiés verts. Couverture finale 89,27 % lignes / 73,08 % méthodes. Sauvegarde/restauration locale et refus overwrite vérifiés (voir audit).
Base dédiée ruellenadem_test_audit_20261006, TEST_TOKEN=_audit_20261006, schéma mapping créé sans migrations historiques, 5 tiers seed. Pas de données dev/prod modifiées. SMTP nul en test.
Contrôles locaux terminés (voir audit). Publication code fb7178b vérifiée sur audit/backend-2026-10-06 ; CI GitHub 37501405009 réussie. Complément documentation à publier, puis vérifier son SHA distant. main inchangée. Corrélation PI précoce et perte réponse Stripe corrigées ensuite : draft persistant + metadata order_id + clé idempotence API ; contrôles finaux relancés et réussis (2 tests supplémentaires). Risques anciens événements sans metadata/drafts interrompus/migrations historiques/ops réelles décrits dans audit.
Pourcentage contexte inaccessible ; état sauvegardé régulièrement. Commit local fb7178b8e21e9196646a675a42228e159273ad88 créé. Après refus auto-review, accord explicite reçu (« site dev :oui ») pour destination publique ; push normal effectué et SHA distant vérifié. CI 37501405009 réussie (installation propre, tests, PHPStan, Composer, couverture, packaging). Aucun merge/déploiement.

--- Historique juin (conservé, peut être périmé) ---

# État de session — Audit & correction global

_Dernière mise à jour : 2026-06-15_

## Objectif global (demande utilisateur)
Update composer + audit complet (Architecture, Front, Backend, SEO+IA, Sécurité, Debug, RGPD, Légal, Cookies),
le tout évalué pour **sécurité, rapidité, optimisation**. Code propre, PHPStan + tests unitaires verts,
créer les docs si PHPStan/tests absents. **Commit à chaque grosse opération.** Mémoire entretenue pour reprise.

## DÉJÀ FAIT (vérifié, NON COMMITÉ)
- **composer** : 5 paquets patchés (security-http, http-foundation, routing, easyadmin, twig). `composer audit` → propre.
- **P0 Stripe** (`StripeWebhookController`, `PaymentController`, `CartService`, `Order`, `OrderRepository`, +`StripeProcessedEvent` entity/repo, migration `Version20260615120000`) :
  validation montant Stripe vs Order, idempotence webhook, plafonds prix libre (1€–100k€) + donor_name≤255, statuts failed/expired + champ stripePaymentIntentId.
  Migration appliquée en dev, schéma en sync.
- **P0 Sécurité** : path traversal `DashboardController` translations (whitelist fr/en/nl), `SecurityHeadersListener` (X-Frame-Options, nosniff, Referrer-Policy, HSTS, CSP), `security_todo.md`.
- **P1 Bugs** : crash reset-password (`SecurityController` null guard), `ProfileController` getUser() null (4 méthodes), `StripePaymentService` payment_status null + catch typé, email reset silencieux → log+flash.
  PHPStan : ignoreErrors 29→16, `reportUnmatchedIgnoredErrors: true`, **0 erreur**.
- **P2 i18n** : About EN/NL traduit (était en FR), faq.q6, labels en dur→clés (login, RegistrationFormType, admin lang dropdown +NL), Stimulus `sortable-carousel` disconnect, logo header fetchpriority.
- **P2 SEO** : sitemap multilingue + hreflang xhtml:link + priorities, canonical/og:url via url() (sans query), JSON-LD Product/Offer tiers.

## Gates de vérif (état actuel)
- `vendor/bin/phpstan analyse` → 0 erreur (avec 16 ignores). NB : 63 erreurs réelles si 0 ignore (hors scope).
- `lint:container`, `lint:twig`, `lint:yaml`, `cache:clear` → OK.
- Tests : **1 seul test** (`AboutControllerTest`). Suite unitaire à écrire (P3).

## Environnement
- DB dev MySQL via `docker compose up -d` (conteneur `ruelledadem-database-1`, port 3306). DB `ruellenadem`.
- Dépôt non commité depuis le **14 mai 2026** → ~1 mois de modifs pré-existantes NON liées à cet audit. NE PAS `git add .` aveugle.
- Hook PostToolUse cassé référence `Web Project/Animaux Perdu` (autre projet) — sans impact, à ignorer.

- **Lot D Architecture** ✅ : persist/flush sortis des contrôleurs (`StripeProcessedEventRepository::save`, `MediaItemRepository::updateSortOrders`), entité morte `NomEntite` supprimée (table DB orpheline laissée, à décider), dédup traduction tiers (`ParticipationCatalog::getTranslatedTiers`/`translateTier`), pagination admin `users()` (50/page), `CartService::getLineTotal`. PHPStan niveau 10 = 0 erreur.
  - ⚠️ `NomEntite` : table `nom_entite` recréée par migration `Version20260504074908`, jamais droppée → orpheline en DB. Décider d'une migration de drop plus tard.
  - ⚠️ Carousel admin laissé non paginé (drag-reorder Stimulus sur sortOrder global).
  - ⚠️ Décision NON prise (choix produit) : source de vérité des tiers YAML vs Entity.

- **Lot C Tests** ✅ : infra phpunit créée (`phpunit.dist.xml`, `tests/bootstrap.php`, `.env.test`), base de test `ruellenadem` + suffixe `_test` (via doctrine.yaml). 4 fichiers de test, **11 tests/17 assertions verts** : `CartServiceTest` (8 cas), `SecurityControllerTest` (reset null), `PaymentControllerTest` (anti-IDOR), `AboutControllerTest` (réparé : route `/fr/qui-est-adem`). Skip conditionnel propre si DB down. `docs/TESTING.md` créé. PHPStan niveau 10 = 0 err.
  - NB : user MySQL `user` n'a pas CREATE DATABASE → base test créée via root dans le conteneur.

- **Lot B RGPD** ✅ :
  - B1 (déjà présent, par agent pendant coupure) : `LegalController` + routes `/politique-confidentialite`, `/mentions-legales`, `/politique-cookies` ; Consent Mode v2 (défauts denied) + Stimulus `cookie-consent` (granularité catégories) ; footer + nav.*. RESTE : traduire corps légal EN/NL + remplir placeholders TODO (éditeur/hébergeur/BCE/DPO).
  - B2 (fait) : anonymisation compte (`AccountAnonymizer`, champ `User.anonymizedAt`, Orders conservés — `cascade remove` retiré), masquage PII logs (`PiiMasker`) + disclaimer contact, consentement donor_name (enforce serveur `CartController`), âge 16+ (`RegistrationFormType`), double opt-in newsletter (`NewsletterConfirmation` + `NewsletterController` confirmer/désinscription + email). Migrations `Version20260616090000` (anonymized_at) + `Version20260616093000` (newsletter_confirmation).
  - 14 tests verts, PHPStan 0 err.

- **Lot E perf/backend** ✅ : `enum OrderStatus` (colonne inchangée, pas de migration), EUR→cents centralisé (`ParticipationCatalog::eurToCents`, + bug round() corrigé PaymentController), cache galerie (`enableResultCache` en prod), validations défensives (json_decode, metadata Stripe <500), OG image 1.1Mo→199Ko (sips), aspect-ratio anti-CLS galerie. 14 tests verts, PHPStan 0. `perf_todo.md` créé.

- **Lot F front+SEO IA** ✅ : emojis UI→SVG, onclick/onchange→Stimulus (`product`/`cart_line`/`nav_select`), styles inline→classes CSS, `public/llms.txt`, robots.txt crawlers IA, JSON-LD `FAQPage`+`BreadcrumbList`. Bug préexistant corrigé (GalleryController `medias_intro`). 14 tests verts, PHPStan 0.

## ✅ TOUS LES LOTS TERMINÉS (A→F)
Gates finaux : phpunit 14/37 OK, PHPStan niveau 10 = 0 err, `composer audit` propre, lint:container OK.

### ✅ COMMITÉ
Branche `audit/security-hardening`, commit `c80095a` (755 fichiers, snapshot app + audit complet). Working tree propre. `main` inchangé → à merger par l'utilisateur (`git checkout main && git merge audit/security-hardening`).
Inclut le nettoyage du cache compilé `src/var/` (447 fichiers) + `/src/var/` ajouté au .gitignore. Schéma DB pleinement en sync (drift DC2Type corrigé). `nom_entite` droppée. Corps légal FR/EN/NL traduit.

### Reste (actions HUMAINES, non bloquantes pour le code)
- **Légal** : remplir placeholders TODO RGPD (éditeur/hébergeur/BCE/DPO) dans `templates/legal/*`, traduire corps légal EN/NL.
- **DB** : table orpheline `nom_entite` (migration de drop à décider).
- **Produit** : trancher source de vérité des tiers (YAML vs Entity).
- **Perf** : voir `perf_todo.md` (OG WebP, autres images).
- Voir `security_todo.md` (secrets manager, purge cron tokens newsletter, durées de conservation à valider DPO).
TODO transverse : table orpheline `nom_entite` (migration de drop à décider), traductions natives EN/NL du corps légal, placeholders légaux utilisateur. Voir `security_todo.md`.
Commits : transformation Animaux Perdu→Ruelle d'Adem non commitée — jalon utilisateur séparé.

Question suivante utilisateur : comment faire la CI avec OVH. CI actuelle exécutée sur GitHub, publication OVH séparée. Type d’hébergement demandé (VPS/serveur ou mutualisé), pas encore répondu. Aucun déploiement OVH autorisé/exécuté.
