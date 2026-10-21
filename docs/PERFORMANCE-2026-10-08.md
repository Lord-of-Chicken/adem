# Optimisation locale — 8 octobre 2026

Cycle : analyse → plan → implémentation → tests → correction → vérification → résultat.
Aucun commit, push, déploiement ou accès de production autorisé pour cette tâche. Les 109 changements préexistants, puis les deux documents de reprise du déploiement, sont préservés. Aucun fichier d’interface, style ou image à modifier.

## Analyse et plan retenu avant correction

Stack vérifiée : PHP 8.5.11 local, Symfony 8.1, Doctrine ORM 3/DBAL 4, MySQL 8.4 local, AssetMapper, Stripe SDK, SMTP synchrone. Les contrôles de référence passent : 172 tests/718 assertions, PHPStan10, schéma et lint ; l’audit Composer rencontre un timeout réseau à relancer.

| Priorité / risque | Preuve et impact | Correction prévue / contrat préservé | Critère de réussite |
| --- | --- | --- | --- |
| 1 / faible | CartService::totalCents relit et valide N lignes pour chaque ligne : 100 lignes = 101 lectures, médiane 2,330 ms ; 1000 = 1001 lectures, 225,630 ms (21 échantillons, Xdebug désactivé). | Calcul en une passe, helper partagé avec getLineTotal. Conserver prix forfaitaires/unitaires, dons, arrondis, exceptions et première occurrence des IDs dupliqués. Aucun cache entre appels. | Une lecture de session, total inchangé, mesures répétées plus rapides aux mêmes volumes. |
| 2 / faible | MediaItemRepository::updateSortOrders charge chaque ID : 50 médias = 50 SELECT et 11,331 ms ; 200 = 200 SELECT et 43,908 ms (21 échantillons, EM froid, positions inchangées pour isoler la lecture). | Charger les IDs en une requête puis modifier les entités et flush, garder invalidation/TTL galerie, ignorer IDs absents. | Un SELECT au lieu de N ; ordre/publication/dirty changes et invalidation conservés. |
| 3 / modéré | StripeReceiptProvider lit le PaymentIntent puis getPaymentReceiptCharge le relit développé : 4 appels SDK HTTP sur 1 page, 5 sur 2 pages, client simulé. | Une lecture PaymentIntent avec latest_charge développé ; préserver méthodes publiques et contrôles complets paiement/Charge/lignes. | 3/4 appels respectivement, aucune validation relâchée, erreurs retryables conservées. |

## Pistes non retenues sans mesure suffisante

Le catalogue est déjà chargé une fois par requête ; aucun cache durable ajouté. Index de notifications pending déjà présent, lot borné à 500. Double lecture SQL find+refresh pour les reçus, index de purge newsletter et N+1 éventuel EasyAdmin restent des pistes à mesurer avant modification ; pas de refonte ni migration spéculative. Les photos de production et leurs ressources restent intactes. Aucun gain HTTP global, Core Web Vitals ou capacité OVH ne sera déduit des mesures unitaires locales.

## Protocole

Panier : mêmes fixtures mixtes unitaires/forfait/don, 10/100/1000 lignes, 3 échauffements puis 21 échantillons, PHP CLI sans Xdebug ni OPcache CLI ; aucun accès DB pour le calcul. Médias : base locale ruellenadem_test_audit_20261006 vérifiée, fixtures dans une transaction annulée ; 4/50/200 médias, EntityManager vidé avant chaque échantillon, compte SELECT instrumenté avec middleware DBAL, ordre persisté contrôlé, nombre de lignes après rollback contrôlé. Les temps isolent la lecture/hydratation : les écritures modifiant réellement l’ordre sont couvertes par tests de régression, pas incluses dans la revendication de gain SQL.

Stripe : vrai SDK et provider, faux client HTTP, clé de fixture, pas de réseau ; 1/2 pages, 15 répétitions, délai simulé 5 ms/appel. Réduction du nombre d’appels déterministe ; délais simulés ne représentent pas la latence de Stripe/OVH.

## Résultat final vérifié

| Scénario | Médiane avant → après | Travail évité |
| --- | --- | --- |
| Panier, 10 lignes | 0,034 → 0,0054 ms | 11 → 1 lecture session |
| Panier, 100 lignes | 2,330 → 0,0456 ms | 101 → 1 lecture session ; calcul O(N²) → O(N) |
| Panier, 1000 lignes (stress) | 225,630 → 0,4532 ms | 1001 → 1 lecture session |
| Médias, 4 IDs | 1,130 → 0,332 ms | 4 → 1 SELECT |
| Médias, 50 IDs | 11,331 → 0,698 ms | 50 → 1 SELECT |
| Médias, 200 IDs (stress) | 43,908 → 2,031 ms | 200 → 1 SELECT |
| Reçu Stripe, 1 page | Mesure simulée uniquement | 4 → 3 appels HTTP du SDK (−25 %) |
| Reçu Stripe, 2 pages | Mesure simulée uniquement | 5 → 4 appels HTTP du SDK (−20 %) |

Une seconde série complète contre les classes exportées du SHA 8e04c41 confirme les ordres de grandeur : panier 100 lignes 2,334 → 0,0455 ms ; 1000 lignes 223,669 → 0,4509 ms ; médias 50 IDs 12,686 → 0,693 ms. Pic supplémentaire de mémoire du calcul panier 1000 lignes : 793072 → 458032 octets. Le chargement groupé utilise temporairement plus de mémoire : à 200 médias, supplément de pic 23440 → 85576 octets (environ +61 Kio), en contrepartie de 199 lectures évitées. Il conserve les mêmes entités gérées ; aucun cache persistant ajouté.

Les premiers timings Stripe à 2 pages étaient bruités (31,714 → 32,372 ms), donc ne justifiaient aucun gain de latence. Série répétée avec Xdebug désactivé : 30,679 → 24,403 ms à délai simulé constant. Seule la réduction d’appels est une preuve applicable au code ; aucune latence réelle Stripe/OVH revendiquée. Les détails de toutes les séries, p95 et paramètres figurent dans PERFORMANCE-2026-10-08-measurements.json.

### Modifications et validation

Quatre fichiers applicatifs : src/Cart/CartService.php, src/Repository/MediaItemRepository.php, src/Service/StripePaymentService.php, src/Service/StripeReceiptProvider.php. Aucun contrat de méthode publique retiré, aucune migration ou dépendance ajoutée, aucun fichier frontend/image modifié.

Tests de régression : calcul mixte en volume, prix forfaitaires/dons, session malformée, tiers absent, doublons/firstmatch et mutation entre appels ; chargement SQL groupé, ordre/publication, IDs inexistants, batch vide, modifications ORM en attente et invalidation cache ; vrai SDK Stripe sur 1/2 pages avec nombre exact d’appels, Charge absente/non développée et contrôles de paiement existants. Le test SQL a reproduit l’échec avant correction (31 SELECT au lieu de 1), puis passe après correction.

Contrôles globaux finaux : TEST_TOKEN=_audit_20261006 bash scripts/quality.sh réussi : **183 tests / 961 assertions**, aucune erreur/skip/dépréciation signalée ; PHPStan niveau 10 sans ignorances ; YAML/conteneur/schéma test ; Composer strict et audit sans avis de vulnérabilité ; scripts shell. Le timeout Packagist initial est résolu lors du contrôle final. Twig : 48 fichiers valides. docker compose config --quiet réussi, sans recréation/démarrage de service. Syntaxe PHP des deux outils de mesure validée. Relecture indépendante sans bug confirmé. Diff applicatif relu ; scan des ajouts sans identifiant effectif ni clé privée ; contrôle des espaces du diff et des nouveaux fichiers réussi.

Cache : seul mémo local de montants, détruit au retour de totalCents, donc aucune durée de vie interappel et aucun partage entre utilisateurs. Le cache galerie reste public, TTL 3600 s, invalidé après flush/sauvegarde/suppression/réordonnancement par les mécanismes existants ; test d’invalidation réussi. Aucun cache de données de paiement/panier/email ajouté.

### Reproduire les mesures

Les outils refusent toute cible backend autre que APP_ENV=test, TEST_TOKEN=_audit_20261006, host 127.0.0.1, base effective ruellenadem_test_audit_20261006. Ne pas les exécuter simultanément avec la suite de tests sur cette base. Les fixtures médias sont annulées et le nombre de lignes après rollback est vérifié. Stripe est exclusivement un faux client HTTP avec clé de fixture et aucune connexion réseau.

```bash
APP_ENV=test TEST_TOKEN=_audit_20261006 XDEBUG_MODE=off php scripts/benchmark-backend.php
XDEBUG_MODE=off php scripts/benchmark-stripe-receipts.php
```

Pour une comparaison sans modifier le checkout, exporter CartService.php, MediaItemRepository.php, StripePaymentService.php et StripeReceiptProvider.php du SHA 8e04c41 dans un dossier temporaire, puis fournir RUELLE_PERF_REFERENCE_DIR=/chemin/reference aux mêmes commandes. La référence de cette session est /tmp/ruelle-performance-reference-8e04c41 ; sa présence n’est pas garantie au-delà de la session.

### Limites et suites

Les volumes sont synthétiques, le panier injecté directement en session pour exercer le calcul ; 1000 lignes et 200 médias sont des tests de stress, pas des volumes de production constatés. Les pics mémoire décrivent le supplément pendant l’opération instrumentée, pas la consommation totale d’un serveur. Les temps médias mesurent lecture/hydratation et un flush sans changement de positions : aucun gain identique prétendu pour le temps total d’une écriture ou d’une page HTTP. Aucune charge réseau réelle, TTFB/LCP navigateur, latence SMTP/Stripe ou capacité OVH mesurée. Les ressources visuelles et leurs tailles sont inchangées. Code public existant et fonctionnalités sensibles validés par les tests, pas une garantie universelle sur toutes les intégrations.

Les trois améliorations retenues et leur validation locale sont terminées. Les autres pistes restent des hypothèses, pas des anomalies prouvées. Déploiement OVH, rotation SMTP, réception réelle de reçus et restauration de production restent des actions d’exploitation séparées ; aucun accès distant, commit, push ou déploiement réalisé pendant cette optimisation.
