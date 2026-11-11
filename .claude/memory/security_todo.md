# Security TODO — Ruelle d'Adem

> Actions manuelles (humain, vrais secrets). Ne rien exécuter automatiquement.

## Secrets prod
- [~] Symfony Secrets Manager prod initialisé (2026-06-16) : clé publique committée dans `config/secrets/prod/`, clé privée gitignorée. **Vault vide** : `secrets:set` pour APP_SECRET, DATABASE_URL, MAILER_DSN, STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, STRIPE_PUBLISHABLE_KEY (`--env=prod`, valeurs saisies interactivement). Déployer `prod.decrypt.private.php` hors-source, idéalement générée sur le serveur.
- [ ] Renouveler (roll) les clés Stripe live si `.env.production` a pu fuiter ; vérifier `git log -p -- .env.production` (doit être vide) et que `git ls-files | grep -E '^\.env(\.|$)'` ne renvoie que `.env`, `.env.dev`, `.env.test`.
- [ ] **P1 — SMTP OVH** : renouveler le mot de passe du compte SMTP (ancien commentaire versionné identique au compte actif, authentification vérifiée ; commentaire retiré mais historique public exposé). Ne pas recopier le secret ; mettre à jour la configuration privée après rotation.

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


## État confirmé audit backend 2026-10-06
Webhooks atomiques et retryables, uploads validés, prix forfaitaires cohérents, retour payé lié à session, tokens pending révoqués, newsletter anonymisée à effacement, reset anti-énumération, logs production masqués et hosts configurables. Configurations présentes ne prouvent pas les secrets/services actifs ; anciens TODO conservés comme actions opérateur à vérifier, pas résultats acquis.
