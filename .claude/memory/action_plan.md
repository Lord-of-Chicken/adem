# Plan de correction global — Ruelle d'Adem

_Source : audits du 2026-06-15 (archi, front, backend, stripe, SEO, sécurité, qualité/tests, RGPD/légal/cookies)._
Règles : 1 commit par lot terminé (gates verts), MAJ `session_current_state.md` après chaque lot.

## Définition of Done par lot
`vendor/bin/phpstan analyse` (0 err) + `vendor/bin/phpunit` (vert) + `lint:container/twig/yaml` + `cache:clear` → puis commit + MAJ mémoire.

---

## LOT A — DÉJÀ FAIT (à committer) ✅
composer update + P0 Stripe/sécurité + P1 bugs/phpstan + P2 i18n/SEO. Voir session_current_state.md.
→ **Commit 1** : "security+stripe: deps CVE, webhook hardening, runtime bugs, i18n/SEO" (staging ciblé, PAS git add .)

## LOT B — RGPD / Légal / Cookies (P0 — risque amende 2-20k€) 🔴
- [ ] Privacy policy `/politique-confidentialite` (finalités, base légale, durées, droits, transfert Stripe US, contact)
- [ ] Mentions légales `/mentions-legales` (éditeur, hébergeur — loi belge)
- [ ] Politique cookies dédiée + liens footer vers les 3 pages
- [ ] Cookie consent : Consent Mode v2 GA, granularité par catégorie, GA chargé après consentement, doc cookies Stripe/GA
- [ ] Donor name → Stripe : checkbox consentement + doc transfert hors UE ; option affichage anonyme
- [ ] Droit à l'effacement : anonymiser au lieu de delete (garder Order 7 ans légal) dans ProfileController
- [ ] Newsletter double opt-in (entité confirmation + token + email + lien désinscription sans login)
- [ ] Contact form : disclaimer finalité/rétention ; masquer PII dans logs
- [ ] Mineurs (Adem <16) : confirmation 16+ à l'inscription ; droit à l'image galerie
- [ ] Export données (portabilité)
→ **Commit 2** (legal pages) + **Commit 3** (cookies/consent) + **Commit 4** (droits RGPD : erasure/newsletter/export)

## LOT C — Tests + Debug (P3) 🔴 (1 seul test actuellement)
- [ ] `tests/Cart/CartServiceTest` : totalCents (don libre + mixte), plafonds → InvalidArgumentException
- [ ] `tests/Controller/SecurityControllerTest` : reset token expiresAt null → pas de 500
- [ ] `tests/Controller/PaymentControllerTest` : success session_id inexistant → anti-IDOR
- [ ] Étendre couverture Services/Domain (Cart, Participation, Stripe)
- [ ] Config base de test si absente (.env.test, schema:create --env=test)
- [ ] Docs : si modules sans tests/phpstan, créer `docs/TESTING.md` + documenter zones non couvertes
→ **Commit 5** : "tests: cart/security/payment regression suite"

## LOT D — Architecture (P2/P3, ratio effort/gain)
- [ ] #1 Sortir persist/flush des contrôleurs → Repositories (StripeProcessedEventRepository::save, MediaItemRepository::updateSortOrders) [~1j, meilleur payoff]
- [ ] #2 Trancher source de vérité tiers : YAML **ou** Entity (pas les deux)
- [ ] Pagination listes admin (findAll medias/users)
- [ ] Dédup logique traduction tiers (3 contrôleurs → service)
- [ ] Dédup recalcul total panier (CartController → CartService::getLineTotal)
- [ ] Supprimer entité morte NomEntite ; catch(\Exception) trop large → typé
- [ ] DIFFÉRÉ (sur-ingénierie à cette échelle) : panier session→DB, abstraction PaymentGateway
→ **Commit 6** : "refactor: persistence in repositories, dedup tier logic, pagination"

## LOT E — Backend / optimisation / rapidité
- [ ] Centraliser EUR→cents (dupliqué 4×) → ParticipationCatalog::eurToCents
- [ ] enum OrderStatus (remplace strings pending/paid/failed)
- [ ] Valider json_decode AdminController ; longueur metadata Stripe
- [ ] Result cache Doctrine galerie publiée (3600s)
- [ ] Images : compresser OG (1.1Mo→<200ko), aspect-ratio galerie (CLS), WebP
→ **Commit 7** : "perf+cleanup: enum, price helper, gallery cache, image optim"

## LOT F — Front + SEO IA (AEO/GEO) (P3)
Front :
- [ ] Emojis UI → SVG (a11y), onclick→Stimulus, styles inline→classes
- [ ] OG images uniques par page
SEO IA (visibilité/citabilité par moteurs IA — le projet n'a PAS de feature IA) :
- [ ] `public/llms.txt` (résumé structuré du site pour LLM)
- [ ] `robots.txt` : politique crawlers IA (GPTBot, ClaudeBot, PerplexityBot, Google-Extended)
- [ ] Schema.org `FAQPage` (page FAQ) + `BreadcrumbList` ; vérifier `Organization` complet
- [ ] Contenu citable par passage (Q/R nets : About, FAQ)
→ **Commit 8** : "front+aeo: a11y, per-page OG, llms.txt, AI-crawler policy, FAQ schema"

## Ordre recommandé (utilisateur : commencer par l'architecture)
A(commit) → **D(archi)** → B(RGPD) → C(tests) → E(perf) → F(polish)
