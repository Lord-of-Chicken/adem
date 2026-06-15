# Décisions techniques — Ruelle d'Adem

## 2026-06-15 — Pilotage par skills projet
- `.claude/CLAUDE.md` et les skills `.claude/skills/` font foi : architecture **MVC classique** (contrôleurs fins, logique en Services, modules métier `src/Cart` + `src/Participation`), style de réponse direct (code d'abord, pas de scope creep), Definition of Done (`strict_types`, PHPStan, tests pertinents, pas d'entités exposées en sortie).
- Mémoire locale `.claude/memory/` utilisée pour tracer tâches/décisions/état après changements significatifs.

## 2026-06-15 — Sécurité paiement Stripe
- Webhook durci : vérification signature + **idempotence** (table `stripe_processed_event`) + **validation du montant** (`session.amount_total` vs `Order.totalCents`) avant passage en `paid`. Anti-falsification du panier.
- Plafonnement serveur des montants libres (1 €–100 000 €), `donor_name` ≤ 255.
- Statuts d'échec gérés : `payment_intent.payment_failed` / `checkout.session.expired` → `failed`.

## 2026-06-15 — Dépendances & en-têtes
- Mise à jour CVE : symfony/security-http, http-foundation, routing, easyadmin, twig. `composer audit` propre.
- `SecurityHeadersListener` : X-Frame-Options, X-Content-Type-Options, Referrer-Policy, HSTS (si HTTPS), CSP (Stripe/GA/Fonts autorisés, `'unsafe-inline'` assumé tant que les scripts inline existent).

## Note historique
Ce dépôt a hébergé un projet antérieur (« Animaux Perdu ») jusqu'au commit du 2026-05-14, puis a été reconstruit en « Ruelle d'Adem ». Les anciennes décisions (API-First, modules feature-based, PostGIS/pgvector) ne s'appliquent plus.
