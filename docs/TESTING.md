# Tests — Ruelle d'Adem

Infra de test PHPUnit pour le projet (Symfony 8 / PHP ≥ 8.4).

## Stack

- **PHPUnit 13.1** (`phpunit/phpunit`)
- **Symfony BrowserKit + CssSelector** (tests fonctionnels `WebTestCase`)
- Pas de Pest, pas de Panther (à ajouter seulement si justifié)

## Fichiers de configuration

| Fichier | Rôle |
|---------|------|
| `phpunit.dist.xml` | Config PHPUnit standard. Bootstrap `tests/bootstrap.php`, testsuite `tests/`, `APP_ENV=test`. |
| `tests/bootstrap.php` | Charge l'autoloader + `.env` (via `Dotenv::bootEnv`). |
| `.env.test` | Variables d'env du `test` : `DATABASE_URL` pointant sur le MySQL Docker. |

> ⚠️ `config/packages/doctrine.yaml` ajoute `dbname_suffix: '_test'` en env `test`.
> La `DATABASE_URL` de `.env.test` cible donc la base **`ruellenadem`** ; Doctrine y ajoute
> `_test` → la base réelle utilisée est **`ruellenadem_test`**. Ne pas remettre le suffixe à la main.

## Prérequis

1. **Docker MySQL** démarré :
   ```bash
   docker compose up -d        # conteneur ruelledadem-database-1 (port 3306)
   docker compose ps           # vérifier "healthy"
   ```
2. **Base de test** créée + migrations appliquées :
   ```bash
   # L'utilisateur applicatif `user` n'a pas le droit CREATE DATABASE :
   # créer la base et donner les droits via root (mot de passe par défaut !ChangeMe!).
   docker exec ruelledadem-database-1 \
     mysql -uroot -p'!ChangeMe!' \
     -e "CREATE DATABASE IF NOT EXISTS ruellenadem_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
         GRANT ALL PRIVILEGES ON \`ruellenadem_test\`.* TO 'user'@'%'; FLUSH PRIVILEGES;"

   php bin/console --env=test doctrine:migrations:migrate -n
   ```
   (Une fois les droits accordés, `php bin/console --env=test doctrine:database:create --if-not-exists`
   fonctionne aussi.)

## Lancer les tests

```bash
# Toute la suite
vendor/bin/phpunit

# Uniquement les tests unitaires (aucune DB requise — toujours exécutables)
vendor/bin/phpunit tests/Cart

# Un seul fichier
vendor/bin/phpunit tests/Controller/PaymentControllerTest.php
```

### Sans base de données

Les tests fonctionnels (`WebTestCase`) détectent l'absence de connexion DB et
se **marquent `skipped`** proprement (méthode `ensureDatabase()`), sans faire
échouer la suite. Les tests unitaires `CartService`, eux, tournent toujours.

## Couverture actuelle

| Test | Type | DB | Ce qui est vérifié |
|------|------|----|--------------------|
| `tests/Cart/CartServiceTest.php` | Unitaire | non | `totalCents()` avec don libre (`custom_price_cents`) ; panier mixte tier + don ; bornes `addLine()` (prix libre < 100 ou > 10 000 000 → `InvalidArgumentException`) ; `donor_name` > 255 → exception ; tier inconnu → exception ; valeurs aux bornes acceptées. |
| `tests/Controller/SecurityControllerTest.php` | Fonctionnel | oui | Régression Phase 1 : reset password avec `resetTokenExpiresAt = null` → pas de 500, redirection propre vers la page de demande. |
| `tests/Controller/PaymentControllerTest.php` | Fonctionnel | oui | Anti-IDOR : `/payment/success?session_id=` inexistant → pas de 500, redirection vers le panier, **panier non vidé**. |
| `tests/Controller/AboutControllerTest.php` | Fonctionnel | oui | Page « Qui est Adem ? » répond 200 (URL générée via le routeur, routes localisées). |

## Conventions

- Routes **localisées** (`/{_locale}` → `/fr`, `/en`, `/nl`) : générer les URLs via
  `router->generate('route_name')` plutôt que de coder un chemin en dur.
- Tests unitaires du domaine : pas de kernel, pas de Doctrine. Session via
  `MockArraySessionStorage`, catalogue réel (lecture YAML, sans I/O DB).
- Noms de tests décrivant le comportement (`testResetWithNullExpiry...`).

## Reste à couvrir (TODO)

- **Webhook Stripe** (`StripeWebhookController`) : vérification de signature,
  idempotence (`stripe_processed_event`), contrôle `amount_total === Order.totalCents`,
  passage `pending → paid` / `failed`.
- **Formulaires** : `ResetPasswordRequestFormType`, `ResetPasswordFormType`,
  contact, newsletter (validation, soumission, flux complet).
- **`ParticipationCatalog`** : tests unitaires dédiés (`translateTier`,
  `getTranslatedTiers`, `lineTotalCents`, `formatEurosFromCents`).
- **CartService** : `updateQuantity` (clamp min/max), `removeLine`, fusion de
  lignes identiques, normalisation du `donor_name` selon `donor_field`.
- **Flux de paiement** complet (checkout → succès) avec Order persistée réelle.
- **i18n** : cohérence des routes/traductions FR/EN/NL.
