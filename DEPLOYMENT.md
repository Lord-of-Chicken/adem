# Déploiement et exploitation — Ruelle d’Adem

Ce document décrit une procédure à valider sur staging. Aucun déploiement de production n’est autorisé par l’audit. Une configuration présente ne prouve pas son fonctionnement.

## Installation reproductible
PHP >=8.4 pour l’application ; PHP 8.5 pour PHPUnit 13 et la CI. MySQL 8.4, Composer 2. Extensions : ctype, iconv, intl, mbstring, PDO MySQL, GD (validation image), sodium, XML/DOM. Les fichiers composer.lock et bun.lock restent versionnés. `composer install --prefer-dist --no-interaction --no-scripts`, puis commandes Symfony explicites. Ne jamais faire `composer update` en production.

## Environnements et secrets
`.env` ne contient que des valeurs de référence publiques. Fournir APP_ENV=prod, APP_DEBUG=0, APP_SECRET, DATABASE_URL, MAILER_DSN, STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET, DEFAULT_URI et TRUSTED_HOSTS depuis l’environnement sécurisé du serveur ou `shared/.env.local` (600, lisible par le compte de service). TRUSTED_HOSTS est une expression régulière ancrée ; l’adapter au domaine réellement déployé. Configurer les trusted proxies uniquement pour les adresses du reverse proxy connu.
Le vault Symfony est initialisé mais sa présence ne prouve pas que les secrets y sont renseignés. Ne pas archiver ou versionner les clés privées `*.decrypt.private.php`. Si un vault chiffré est utilisé, fournir sa clé de déchiffrement séparément. L’archive produite exclut les secrets locaux et la clé privée.

## CI et vérification locale
La CI `.github/workflows/backend.yml` ne déploie rien. Elle construit une base MySQL éphémère dédiée, crée le schéma depuis les mappings, charge les formules puis exécute PHPUnit sans skips, PHPStan niveau 10 sans ignorances, lint configuration/conteneur, validation schéma, Composer et couverture. Les migrations historiques incluent l’ancien projet et des drops : ne pas les exécuter aveuglément pour initialiser une installation neuve.
En local, choisir un TEST_TOKEN distinct, créer cette base isolée et lancer `bash scripts/quality.sh`. Aucun test ne doit utiliser la base de production. SMTP est forcé à null en environnement test ; Stripe est doublé dans les tests.

## Release
1. Autorisation de déploiement distincte, sauvegarde cohérente DB + médias + configuration sécurisée, restauration de cette sauvegarde vérifiée isolément.
2. Préparer `/var/www/adem/{releases,shared}` ; secrets dans `shared/.env.local`, médias dans `shared/media`, journaux dans `shared/log`. Le compte de service peut écrire cache/log/médias, pas le code. Aucun chmod 777.
3. `bash scripts/package.sh /tmp/adem-release.tar.gz` crée un artefact sans secrets incluant bin/console, public/index.php et translations. Contrôler son contenu avant transfert.
4. Examiner `doctrine:migrations:status` et les SQL attendus sur staging. Sauvegarder avant toute évolution de schéma. Toute migration destructive exige une autorisation explicite. Ne jamais générer/appliquer un diff automatiquement en production.
5. Après approbation uniquement : `bash scripts/deploy.sh --apply HOST USER /var/www/adem https://HOST/health`. Le script prépare un nouveau dossier, installe le lockfile, vérifie schéma/conteneur, compile les assets et bascule le lien `current`. Il n’applique aucune migration.
6. `/health` contrôle la connexion DB et retourne 200 ou 503 sans détails internes ; il ne prouve ni les emails, ni les paiements, ni les workers. Tester connexion, panier, paiement Stripe sandbox et livraison webhook sur staging, puis vérifier les journaux et alertes.
7. Configurer le serveur HTTP avec racine `current/public`, PHP-FPM >=8.4 et HTTPS. Seul index.php est exécutable ; refuser les autres PHP, dotfiles et répertoires de secrets. Les médias sont des images publiques validées ; tout besoin de médias privés implique un autre flux autorisé.
Le script de déploiement partiel historique est désactivé : il mélangeait versions/configuration et pouvait annoncer un succès après échec.

## Stripe et tâches
Endpoint canonique : POST `/stripe/webhook`, sans redirection de locale. Événements gérés : checkout.session.completed, checkout.session.expired, payment_intent.payment_failed. Signature obligatoire, montant exact et devise EUR, idempotence atomique avec commande verrouillée. Une commande absente ou un traitement interrompu répond 503 et reste retryable. Les nouveaux checkouts persistent un draft avant l’appel et transmettent order_id à la Session et au PaymentIntent avec une clé d’idempotence par commande ; les webhooks peuvent récupérer la référence après perte de réponse. Un draft pending ne prouve pas un paiement ; rapprocher les tentatives interrompues et les anciens événements sans métadonnée en staging puis exploitation. Paiements carte uniquement ; ajouter d’autres moyens requiert les événements asynchrones correspondants.
Mailer est synchrone dans ce dépôt. Les transports Chat/Sms async sont configurés mais aucun traitement métier custom Messenger n’existe. `ops/messenger.service.example` est un exemple à installer uniquement si utilisé, avec contrôle `messenger:failed:show` et procédure de reprise. `ops/newsletter.cron.example` doit être adapté puis installé par l’opérateur ; vérifier une exécution réelle et l’absence de chevauchement.

## Sauvegarde et restauration
MySQL utilise mysqldump, jamais pg_dump. Fournir MYSQL_CNF vers un fichier protégé de client MySQL, puis `MYSQL_CNF=/chemin/protege.cnf bash scripts/backup.sh BASE /chemin/nouveau-backup.sql`. Snapshot transactionnel InnoDB ; aucun changement de schéma simultané. Chiffrer, contrôler l’intégrité, conserver hors serveur avec rétention et accès limités ; inclure shared/media et secrets séparément.
`restore-check.sh NOUVELLE_BASE_restorecheck /chemin/backup.sql` refuse toute base existante et importe dans un nouveau schéma. Le compte doit avoir les droits appropriés. Vérifier tables, comptes/commandes/montants/statuts, accès applicatif et médias dans un environnement isolé. Une importation réussie ne prouve pas à elle seule la restauration métier complète. RPO/RTO/rétention restent à décider selon hébergement et budget ; aucun service payant créé.

## Retour arrière et observabilité
Noter la cible `readlink current` avant release. Sur staging puis après autorisation, recréer `current.next` vers cette ancienne release et remplacer atomiquement current ; recharger PHP-FPM si nécessaire et tester `/health` et parcours métier. Ne pas exécuter automatiquement les migrations down : le code ancien doit être compatible avec le schéma actuel, sinon restauration DB approuvée séparément. Garder les anciennes releases et sauvegardes tant que la release n’est pas validée.
Prod écrit les logs JSON sur stderr : le superviseur doit les collecter avec rotation/rétention. Surveiller HTTP 5xx, erreurs Stripe/retries/écarts de montant, échecs SMTP, profondeur des queues et heartbeat cron. Définir les destinataires et tester une alerte volontaire sur staging. Les tokens/liens et corps de messages ne doivent pas être conservés dans les logs. Les durées et preuves d’exploitation sont à documenter par l’opérateur.
