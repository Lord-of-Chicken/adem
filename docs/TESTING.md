# Tests backend — Ruelle d’Adem

Symfony 8.1, PHP ≥ 8.4, PHPUnit 13.3, MySQL 8.4, PHPStan niveau 10. Frontend exclu de l’audit du 6 octobre 2026.

## Isolation et préparation
Ne jamais pointer les tests vers une base de production. Doctrine ajoute `_test${TEST_TOKEN}` au nom de base de DATABASE_URL. Choisir un suffixe unique, créer cette nouvelle base avec droits dédiés, puis :

```bash
export APP_ENV=test TEST_TOKEN=_ci
php bin/console doctrine:schema:create --env=test
php bin/console app:seed-participation-tiers --env=test
bash scripts/quality.sh
```

Le schéma est créé uniquement dans une base neuve. Les migrations historiques contiennent l’ancien projet et des drops ; aucune exécution automatique pendant les tests. SMTP est forcé à null en test. Stripe utilise des doubles et des signatures de fixture, aucune vraie clé ni API distante.
Le test bootstrap refuse un environnement différent de test. Les fixtures HTTP/repositories utilisent des transactions avec rollback. Les tests de concurrence ont besoin de commits visibles à deux processus MySQL : ils utilisent leurs propres identifiants et nettoient uniquement ces fixtures. Ne pas paralléliser plusieurs suites sur la même base.

Validation locale de l’audit : TEST_TOKEN=_audit_20261006, base ruellenadem_test_audit_20261006. Les données dev/prod sont préservées.

## Commandes

```bash
bash scripts/quality.sh
XDEBUG_MODE=coverage vendor/bin/phpunit --fail-on-skipped --coverage-clover coverage/clover.xml --coverage-text=coverage/summary.txt
composer install --prefer-dist --no-interaction --no-scripts
```

La chaîne vérifie YAML (app, traductions, CI, Compose), conteneur, mappings et schéma, PHPStan, PHPUnit sans skips/dépréciations, Composer strict/audit et syntaxe shell. `docker compose config --quiet` est un contrôle local complémentaire si Docker est disponible. La CI construit son propre MySQL et produit la couverture et une archive d’installation sans secrets. Elle ne déploie rien.

PHPStan analyse src, tests, migrations, configuration PHP, points d’entrée bin/public et importmap au niveau 10, avec extensions Doctrine/Symfony. Aucune baseline ni ignoreErrors ; seule exclusion src/var (ancien cache généré). Le chargeur Doctrine utilise la métadonnée ORM sans requête DB ; cache:warmup dev fournit le conteneur compilé pour l’analyse. Les extensions sont uniquement des dépendances de développement.

## Comportements couverts
- Catalogue/panier : prix forfaitaire et unitaire, arrondis, dons libres/bornes, quantités, consentement donneur, sessions invalides, mutations CSRF.
- Paiement : payload SDK, variable URL Stripe littérale, timeout simulé, conservation panier, réutilisation courte checkout, retour lié à la session et au panier payé, événements signés, devise/montant, rejeu, échec partiel et reprise, deux processus concurrents MySQL.
- Identité : inscription/validation, connexion/refus, permissions utilisateur/admin, reset expiré/consommé, réponse uniforme après échec mail, modification rôle interdite, changement mot de passe et anonymisation des données du compte/newsletter/commandes.
- Newsletter : double opt-in, expiration, confirmation/rejeu, désinscription par lien et profil, révocation pending, échec transport, purge.
- Médias : validation image réelle, refus PHP/SVG/corruption/taille, stockage aléatoire, cache après écriture ORM, réordonnancement validé sans écriture partielle, actions admin protégées.
- Persistance/commandes : pagination/filtrage, mise à niveau hash, isolation propriétaire commande, admin upsert/refus, seed formules et galerie, chemins médias répétables.
- Exploitation : santé 200/503 sans fuite, masquage logs, archive runtime sans secrets/refus overwrite, refus de déploiement implicite/partiel. Sauvegarde/restauration locale documentées dans l’audit.

## Limites
Un pourcentage ne prouve pas une couverture métier complète. Aucun paiement Stripe réel, SMTP réel, déploiement, migration historique, cron/worker distant ni alerte n’est validé par ces tests. Les configurations de champs et interfaces EasyAdmin ne sont pas exhaustivement exercées. Les tests de reprise ne remplacent pas un rapprochement Stripe complet en staging, ni les tests de charge.
Validation du 6 octobre : 128 tests / 567 assertions, couverture 89,27 % lignes et 73,08 % méthodes ; résultats précis et risques : [AUDIT-BACKEND-2026-10-06.md](AUDIT-BACKEND-2026-10-06.md). Voir également ../DEPLOYMENT.md.
