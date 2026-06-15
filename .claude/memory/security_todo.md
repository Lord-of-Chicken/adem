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

- [ ] Migration secrets prod → Secrets Manager
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

- [ ] **Contenus juridiques à confirmer** : durées de conservation réelles à inscrire dans
  les clés de trad `contact.privacy_disclaimer` (actuellement « 12 mois », à valider avec le
  DPO/responsable) et cohérence avec la page `/politique-confidentialite`.
- [ ] **Newsletter hors inscription** : le double opt-in n'est branché que sur le formulaire
  d'inscription. Si d'autres points d'entrée newsletter existent (toggle profil
  `ProfileController::toggleNewsletter`, futur formulaire dédié), les router vers
  `NewsletterSubscriptionService::startDoubleOptIn()` également. Le toggle profil active
  encore le flag directement (acceptable : utilisateur authentifié, mais à harmoniser).
- [ ] **Purge des tokens newsletter expirés** : prévoir une commande/cron pour supprimer les
  `NewsletterConfirmation` expirés non confirmés (minimisation).
- [ ] **Anonymisation des metadata Stripe** : le `donor_name` envoyé en metadata Stripe lors
  d'une commande passée reste chez Stripe après anonymisation locale. Si exigé, prévoir un
  appel API Stripe pour purger/anonymiser la metadata des `PaymentIntent`/sessions liés.
- [ ] **Schema validate** : `doctrine:schema:validate` signale un écart pré-existant
  (table orpheline `nom_entite` issue d'anciennes migrations + commentaires DC2Type non
  remontés par MySQL). Non lié au lot B2 — à nettoyer séparément via une migration de purge.
- [ ] **i18n** : relire les traductions NL/EN des nouvelles clés (`newsletter.*`,
  `contact.privacy_*`, `home.donor_consent`, `registration.form.age_*`) par un locuteur natif.
