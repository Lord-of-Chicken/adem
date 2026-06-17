# Security TODO — Phase 0 (durcissement)

> NE PAS exécuter automatiquement. Actions manuelles à réaliser par un humain avec accès aux vrais secrets.

## 1. Migrer les secrets prod vers le Symfony Secrets Manager

Ne pas garder les secrets de production en clair dans `.env.production`.
Utiliser le vault chiffré de Symfony :

```bash
# Générer les clés du vault prod (une seule fois)
php bin/console secrets:generate-keys --env=prod

# Définir chaque secret (valeur saisie interactivement, jamais en clair dans le shell)
php bin/console secrets:set APP_SECRET --env=prod
php bin/console secrets:set DATABASE_URL --env=prod
php bin/console secrets:set MAILER_DSN --env=prod
php bin/console secrets:set STRIPE_SECRET_KEY --env=prod
php bin/console secrets:set STRIPE_WEBHOOK_SECRET --env=prod
php bin/console secrets:set STRIPE_PUBLISHABLE_KEY --env=prod
```

- Committer UNIQUEMENT `config/secrets/prod/*.encrypt.public.php` et les `*.*.php` chiffrés.
- La clé privée `config/secrets/prod/prod.decrypt.private.php` reste hors Git (déjà gitignorée) et est déployée hors-source (variable d'env / CI secret).

## 2. Régénérer les clés Stripe LIVE si `.env.production` a pu fuiter

Si `.env.production` (ou tout fichier contenant des secrets live) a pu être exposé
(commit accidentel, partage, fuite) :

- Stripe Dashboard → Developers → API keys → **Roll** la Secret Key live.
- Régénérer le **Webhook signing secret** des endpoints live concernés.
- Mettre à jour les valeurs dans le Secrets Manager (étape 1), pas dans le fichier.
- Vérifier l'historique Git : `git log -p -- .env.production` (doit être vide / inexistant).
  Si présent dans l'historique, purger (git filter-repo / BFG) ET considérer les clés
  comme compromises → roll obligatoire.

## 3. Vérifier le .gitignore des fichiers d'environnement

Déjà couvert par `.gitignore` (vérifié) :

```
.env.local
.env.*.local
.env.production
.env.local.php
.env.*.php
```

À valider en plus :

```bash
# Aucun fichier d'env sensible ne doit être suivi par Git
git ls-files | grep -E '^\.env(\.|$)' | grep -vE '\.env$|\.env\.dev$|\.env\.test$'
# (doit ne rien retourner)

git status --ignored | grep -E '\.env\.(local|production)'
# (doit apparaître comme "ignored")
```

- `.env` (valeurs par défaut non sensibles) peut rester versionné.
- Tout secret réel doit vivre dans `.env.local` / `.env.production` (gitignorés)
  ou, mieux, dans le Secrets Manager (étape 1).

## Statut

- [~] **Secrets Manager prod INITIALISÉ** (2026-06-16) : keypair sodium généré
  (`config/secrets/prod/` — clé publique committée, clé privée gitignorée + perms 600).
  Vault vide. **Reste à faire (humain, valeurs réelles)** :
  - `php bin/console secrets:set APP_SECRET --env=prod` (puis DATABASE_URL, MAILER_DSN,
    STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, STRIPE_PUBLISHABLE_KEY) avec les vraies valeurs.
  - Déployer `config/secrets/prod/prod.decrypt.private.php` hors-source (serveur/CI), JAMAIS dans git.
    Idéalement la régénérer sur le serveur prod (la clé privée a été générée sur la machine de dev).
- [ ] Roll des clés Stripe live (si fuite suspectée)
- [ ] Audit historique Git pour fichiers d'env

---

# Lot B2 — Droits RGPD côté code (implémenté 2026-06-16)

## Fait

- [x] **Droit à l'effacement → anonymisation** (`src/Service/AccountAnonymizer.php`,
  `ProfileController::delete()`). Les `Order` sont conservés (obligation fiscale 7 ans),
  les données perso du `User` sont effacées, `user.anonymized_at` ajouté (migration
  `Version20260616090000`). Le `cascade: ['remove']` sur `User::$orders` a été retiré.
  `donor_name` dupliqué dans `Order.cartData` est aussi scrubé. Tests unitaires :
  `tests/Service/AccountAnonymizerTest.php`.
- [x] **Disclaimer contact + masquage PII logs** (`templates/contact/index.html.twig`,
  `ContactController`, `src/Service/PiiMasker.php`). Le `StripeWebhookController` ne logue
  déjà aucune PII (uniquement IDs/montants) — vérifié, aucun changement nécessaire.
- [x] **Consentement donor name** (case non pré-cochée, `templates/home/index.html.twig`,
  `assets/js/home_ui.js`, enforcement serveur dans `CartController::add` via `donor_consent`).
- [x] **Âge mineur à l'inscription** (case `ageConfirmed` non mappée + `IsTrue`,
  `RegistrationFormType`, seuil belge 16 ans).
- [x] **Double opt-in newsletter** (`NewsletterConfirmation` + repo + migration
  `Version20260616093000`, `NewsletterSubscriptionService`, `NewsletterController` :
  `/newsletter/confirmer/{token}` et `/newsletter/desinscription/{token}` sans login,
  e-mail `templates/emails/newsletter_confirm.html.twig`, hook dans `RegistrationController`).

## Reste à faire / à valider par un humain

- [x] **Durée de conservation messages contact** : fixée à **6 mois** (consigne utilisateur 2026-06-16).
  Harmonisé entre `contact.privacy_disclaimer` (était 12 mois) et `legal.privacy.retention_contact`
  (était 30 jours) dans les 3 langues. NB : les messages de contact ne sont PAS stockés en DB
  (envoi e-mail direct) → la rétention s'applique à la boîte mail (action ops). Commandes : 7 ans (inchangé).
- [ ] **Newsletter hors inscription** : le double opt-in n'est branché que sur le formulaire
  d'inscription. Si d'autres points d'entrée newsletter existent (toggle profil
  `ProfileController::toggleNewsletter`, futur formulaire dédié), les router vers
  `NewsletterSubscriptionService::startDoubleOptIn()` également. Le toggle profil active
  encore le flag directement (acceptable : utilisateur authentifié, mais à harmoniser).
- [x] **Purge des tokens newsletter expirés** : commande `app:newsletter:purge-expired`
  (`src/Command/PurgeNewsletterTokensCommand.php` + `NewsletterConfirmationRepository::deleteExpiredUnconfirmed()`,
  tests `tests/Repository/NewsletterConfirmationRepositoryTest.php`). **Action ops restante** :
  la planifier en cron (ex. quotidien : `php bin/console app:newsletter:purge-expired`).
- [ ] **Anonymisation des metadata Stripe** : le `donor_name` envoyé en metadata Stripe lors
  d'une commande passée reste chez Stripe après anonymisation locale. Si exigé, prévoir un
  appel API Stripe pour purger/anonymiser la metadata des `PaymentIntent`/sessions liés.
- [x] **Schema validate** : corrigé. Table orpheline `nom_entite` droppée (migration
  `Version20260616100000`) + commentaires DC2Type retirés des migrations B2. `doctrine:schema:validate`
  est désormais pleinement en sync (mapping + DB).
- [ ] **i18n** : relire les traductions NL/EN des nouvelles clés (`newsletter.*`,
  `contact.privacy_*`, `home.donor_consent`, `registration.form.age_*`) par un locuteur natif.
