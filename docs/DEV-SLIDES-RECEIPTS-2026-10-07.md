# Développement : slides et reçus de paiement — 7 octobre 2026

## Périmètre et état

Travail exclusivement dans `/Users/gaellayeux/Dev/RuelleDAdem`, branche `audit/backend-2026-10-06`. La production sert uniquement de source des quatre photos publiques du diaporama. L'utilisateur a précisé : reprendre uniquement les visuels des slides de production, travailler sur le dev, publication plus tard. À la clôture technique, aucun commit/push de ces changements n’avait été réalisé. Le 7 octobre, l’utilisateur a ensuite explicitement autorisé le commit et le push sur la branche d’audit ; aucun merge ni déploiement autorisé/exécuté. La publication doit être vérifiée par le SHA distant et la CI GitHub.

Préexistants préservés : suppressions `.claude/skills/**`, modification `templates/components/SiteHeader.html.twig`. Aucun remplacement du CSS, du bandeau chantier, des polices ou des améliorations RGPD par la version de production.

## Constats, plan et corrections

| Constat vérifié | Impact | Correction et fichiers | Validation |
| --- | --- | --- | --- |
| Galerie absente : la table `media_item` du dev local était vide ; Twig omet le diaporama quand aucun média n'est publié. | Fonctionnel P2 | Import des quatre photos publiques, dans l'ordre de production ; sources `assets/img/ruelle/*.jpg` et fichiers `public/img/ruelle/*.jpg`. | 4 images effectivement chargées, Swiper initialisé, bouton suivant change la photo active. |
| Anciennes photos locales non souhaitées, retrait explicitement demandé. | Contenu | 15 anciens fichiers retirés et 11 entrées locales remplacées ; copie de récupération vérifiée avant retrait ; bibliothèque du seeder remplacée par les quatre nouvelles sources. | Base : 4 lignes / 4 publiées ; exécution du seeder conserve ces mêmes quatre photos. |
| Aucun reçu de paiement envoyé au site dans le webhook existant. | Nouvelle exigence métier | Notification durable unique par commande dans `payment_receipt`, créée avec la confirmation de paiement. Envoi après commit ; commande de reprise. Services `StripeReceiptProvider`, `PaymentReceiptEmailFactory`, `PaymentReceiptSender`, commande `SendPaymentReceiptsCommand`, migration `Version20261007120000`. | Paiement signé valide uniquement, doublons d'événements, rollback, panne d'envoi et deux workers simultanés testés. |
| Le helper statique Stripe ne conserve pas les filtres de pagination des lignes. | Détail des reçus incomplet | `StripePaymentService` conserve `limit` et expansion des produits lors du parcours de toutes les pages. | Test du vrai SDK avec client HTTP simulé sur deux pages. |
| Identifiant SMTP actif dans un ancien commentaire versionné : comparaison interne identique et authentification réussie, sans divulgation du secret. | P1 : secret SMTP actif exposé | Commentaire supprimé de `config/packages/dev/mailer.yaml`, sans recopier le secret. | Diff contrôlé ; compte actif confirmé. Rotation urgente par le propriétaire ; historique Git non modifié. |

Le premier relevé navigateur prétendant qu'aucune police web n'était chargée était incomplet. L'actualisation et le rendu ont confirmé les mêmes familles configurées que la production : Rubik, Permanent Marker et Archivo Black. Aucun changement de police nécessaire/prové.

Le premier nettoyage a été rejeté par la revue automatique car il retirait les sources du seeder. Après la demande de continuer, la version sûre a sauvegardé les fichiers et lignes, remplacé d'abord la bibliothèque du seeder par les nouvelles photos, puis effectué le retrait seulement sur la base locale vérifiée. Copie dans `var/recovery/dev-slides-2026-10-07/`, ignorée par Git, hors du répertoire public. Aucun retrait de production.

## Reçu envoyé au site

Destinataire et expéditeur : paramètre existant `app.contact_email`, actuellement `info@ruelledadem.com`.

Email texte et HTML échappé : commande, date Europe/Brussels, montant EUR, références session/paiement/transaction, coordonnées disponibles du payeur, carte masquée, lignes effectivement facturées (toutes les pages), prix, quantité facturée, quantité choisie et nom VIP, lien vers le reçu officiel Stripe. La carte complète et le CVC ne sont jamais inclus. Les objets session, PaymentIntent et Charge sont vérifiés contre le paiement local. Le reçu officiel est un lien fourni par Stripe, pas un PDF généré ou un email prétendument émis par Stripe. Référence : [documentation Stripe des reçus](https://docs.stripe.com/receipts).

La notification n'est créée qu'au premier passage confirmé à `paid`, dans la même transaction que le paiement et la revendication de l'événement. Une contrainte unique et un verrou de la notification évitent les doublons ordinaires et les envois concurrents. Un échec API/SMTP laisse la notification en attente sans annuler le paiement. La table ne duplique pas les coordonnées du payeur ; les détails sont relus chez Stripe à l'envoi.

La migration additive a été exécutée explicitement seulement sur le dev `ruellenadem` et le test isolé `ruellenadem_test_audit_20261006`. Les anciennes migrations n'ont pas été exécutées. Le retour arrière ne supprime pas silencieusement l'historique des notifications.

## Validation finale exécutée

- `TEST_TOKEN=_audit_20261006 bash scripts/quality.sh` : succès.
- PHPUnit : **172 tests / 718 assertions**, aucun test ignoré ou dépréciation signalée.
- PHPStan niveau 10 : aucune erreur, sans nouvelle exclusion.
- Lint YAML et conteneur, schéma test, Composer strict/audit, syntaxe des scripts : succès.
- Schéma dev : mapping et base synchronisés.
- Tests provider/factory : invité, coordonnées absentes, HTML hostile, email/URL invalides, montant/devise/statut incohérents, carte masquée, totalité des lignes et quantité forfaitaire.
- Tests livraison : pas d'envoi avant commit, paiement préservé après panne, reprise, livraison désactivée, refus explicite de fausse réussite de la commande, concurrence réelle de deux processus avec un seul envoi simulé.
- Navigateur dev : quatre images chargées, Swiper initialisé et photo suivante vérifiée. Après nettoyage, les quatre URLs AssetMapper répondent HTTP 200. Capture locale conservée pour le bilan.
- Diff relu, `git diff --check` réussi. Aucun email réel ni appel réel de paiement exécuté pendant les tests.

## Activation et limites réelles

La valeur versionnée par défaut de `MAILER_DSN` est nulle, mais la surcharge locale effective configure `ssl0.ovh.net:465`. Une connexion TLS/authentification SMTP réelle a réussi, sans expédier d'email. Stripe local est vérifié en mode test. `.env.dev` active désormais `PAYMENT_RECEIPTS_SEND_ENABLED=1` uniquement sur le dev. Les prochains paiements de test confirmés peuvent donc notifier l'adresse du site. La valeur par défaut générale `0` conserve les notifications en attente ailleurs ; `.env.test` force aussi `0` pour la suite isolée. La réception effective en boîte mail reste non vérifiée.

Avant activation, configurer un transport SMTP réel dans la configuration locale non versionnée, vérifier l'expéditeur et la réception à l'adresse du site, puis définir `PAYMENT_RECEIPTS_SEND_ENABLED=1`. La surcharge dev de Mailer utilise déjà le DSN effectif local. Le mot de passe SMTP actif est identique à l’ancien commentaire versionné : renouvellement urgent dans OVH, puis mise à jour de la configuration privée. La suppression du commentaire ne retire pas le secret de l’historique public. Ne jamais partager le nouveau mot de passe dans le chat ou le dépôt.

La reprise s'effectue avec `php bin/console app:send-payment-receipts --limit=50 --env=prod --no-interaction`, via un cron approprié au futur hébergement. Aucun cron OVH installé ou vérifié. L'envoi immédiat est tenté après le webhook quand activé ; la commande couvre les pannes API/SMTP et les interruptions.

Limite SMTP : une interruption après acceptation par le serveur SMTP mais avant l'enregistrement du succès peut produire un doublon lors de la reprise. L'exactement-une-fois nécessiterait un fournisseur offrant une clé d'idempotence. Le SMTP est vérifié uniquement au niveau connexion/authentification. La livraison d'un reçu, l'API Stripe réelle, un paiement réel et la planification ne sont pas déclarés vérifiés.

Les paiements historiques déjà `paid` ne sont pas réexpédiés automatiquement. La nouvelle exigence concerne les prochaines confirmations ; tout rattrapage historique exige une sélection explicite.
