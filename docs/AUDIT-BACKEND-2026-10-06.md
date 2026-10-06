# Audit backend — 6 octobre 2026

## Périmètre et état initial
Sécurité, backend, API, DB, configuration, architecture, métier, DevOps. Frontend exclu.
Branche initiale main, origin https://github.com/Lord-of-Chicken/adem.git.
Modifications préexistantes : suppressions .claude/skills et templates/components/SiteHeader.html.twig. Hors commit de cette mission.
Consignes lues : .claude/CLAUDE.md, mémoire locale, docs/TESTING.md, DEPLOYMENT.md, catalogue global et skills sécurité/qualité/DevOps. Pas de CLAUDE.md ni AGENTS.md à la racine du dépôt ; RTK.md introuvable aux emplacements vérifiés.
Baseline : 20 tests / 63 assertions réussis sur MySQL ; la commande sans cache ajoutait un avertissement PHPUnit (option de lancement, pas défaut applicatif). PHPStan niveau 10 passe avec ignorances ; sans ignorances : 50 diagnostics. MySQL 8.4 local healthy. PHP 8.5.11.

## Constats et plan
| Priorité | Preuve initiale | Correction ciblée | Validation / risque |
|---|---|---|---|
| P1 | StripeWebhookController sauvegarde le marqueur avant traitement/flush commande | Transaction unique, claim SQL, verrou commande et rollback, reprise si commande absente | Signature, montant/devise, doublons, échec partiel et concurrence MySQL ; changements limités au webhook |
| P1 | LocaleListener redirige /stripe/webhook ; routes importées seulement avec locale | Route racine explicite, conserver alias localisés | POST racine sans redirection |
| P1 | MediaItemCrudController::persistEntity déplace toute extension devinée sans validation | Service commun de validation image et stockage aléatoire | PHP, SVG, faux fichier, dimensions/taille, image valide ; pas de modification visuelle |
| P1 | deploy.sh archive .env.production, pas bin/, efface logs locaux, ignore échecs ; DEPLOYMENT.md utilise pg_dump pour MySQL et PHP 8.2 | Packaging sans secrets et déploiement explicite contrôlé, CI, exploitation/rollback/restauration documentés | Syntaxe, contenu archive, refus des actions implicites ; aucun accès production |
| P2 | CartService multiplie prix forfaitaire malgré ParticipationCatalog::lineTotalCents et tests existants | Même calcul catalogue/panier/Checkout | Forfait vs quantité, bornes, sessions invalides |
| P2 | PaymentController::success vide tout panier pour toute commande existante, même impayée/autre visiteur | Lier retour à session initiatrice + statut payé + snapshot courant | Autre visiteur, pending, replay et panier modifié |
| P2 | RegistrationFormType noms/email sans bornes DB ; ProfileFormType email vide accepté ; reorder cast permissif | Contraintes et validation stricte aux frontières | Entrées invalides sans écritures partielles |
| P2 | NewsletterController::unsubscribe laisse le token pending confirmable ; reset mail diffère si échec transport | Révoquer token pending, réponse reset uniforme, logs sans exception/PII | Confirmation après désinscription, erreur mail |
| P2 | phpstan ignoreErrors larges et migrations exclues | Extensions Doctrine/Symfony, chemins PHP complets, suppression des ignorances, types explicites | Niveau 10 sans baseline/ignorances |
| P2 | Cache galerie invalidé uniquement par repository, EasyAdmin écrit via EM | Invalidation après flush ORM | Publication/dépublication via EM visible immédiatement |
| P2 | Pas de CI versionnée, procédures sauvegarde/restore/rollback incomplètes | CI backend, scripts qualité/packaging/backup/restore et runbook | Local/isolé, CI distante après push ; procédures écrites distinguées des exécutions |

## Tests prévus
Compléter panier/catalogue/prix, état commandes, masquage PII, newsletter (succès/expiration/replay/désinscription/transport), authentification/reset/profil/CSRF/roles, Stripe (doubles API/signatures/atomicité/concurrence), uploads, repositories/cache et commandes idempotentes. Fixtures isolées et rollback ; suite intégration obligatoire en CI. Mesurer couverture via Xdebug puis examiner les branches encore non testées.

## Limites initiales
Aucun service de production, SMTP réel, paiement Stripe réel, cron/worker distant, sauvegarde ni restauration distante vérifié. Vault prod initialisé mais ancien suivi indique vide : ne pas conclure opérationnel. Données et migrations historiques préservées. Ne jamais lancer les anciennes migrations destructives sur une base existante.

## Progression
Analyse et plan terminés ; corrections implémentées. Vérification finale et publication sur branche dédiée en cours.

### Validation intermédiaire
95 tests / 398 assertions verts sans skip, dont deux processus Stripe synchronisés sur une barrière MySQL et rollback simulé après claim. Le contrôle suivant (111 cas) a révélé un arrondi 1.005 EUR -> 100 cents (artefact binaire), corrigé à 101 cents, et a conduit à adapter des attentes de harnais (CSRF stateless via Origin, status contact 200, cache-control enrichi par Symfony). Aucun contrôle désactivé.
Sauvegarde + restauration locale réussies via les clients MySQL du conteneur : 7 tables importées, 5 formules et somme des prix 77,00 EUR identiques ; refus d’une seconde restauration dans la même base (code 2). Base restaurée ruellenadem_audit_1791302138_restorecheck. Ne prouve pas une restauration de production ni la récupération des médias/configuration.

### Revue et compléments
- P2 confirmé : anonymisation du compte laissant newsletter_confirmation.email en clair. Correction atomique avec les autres entités dans le flush ORM, expiration des tokens sans inventer de confirmation ; test d’effacement et conservation des commandes.
- P2 confirmé : désinscription profil laissant des tokens pending valides. Même révocation que le lien newsletter, test fonctionnel.
- P2 confirmé : success_url contenant `%7BCHECKOUT_SESSION_ID%7D`, variable Stripe devant rester littérale. Régression rouge avant correction, verte ensuite. Référence primaire : https://docs.stripe.com/payments/checkout/custom-success-page . Réutilisation courte du checkout en attente pour le même panier (double clic), sans réutiliser un checkout échoué ; logs Stripe limités à la classe d’erreur.
- Composer strict initialement bloqué par name/description absents, ajoutés. GD est désormais un prérequis Composer explicite pour la validation d’images corrompues. Pas de changement de dépendance de production autre que ce prérequis ; deux extensions PHPStan ajoutées en développement.
- Dernier contrôle complet : 125 tests / 536 assertions, PHPStan niveau 10 sans erreurs, mappings/schéma dédiés cohérents, lint conteneur/YAML, Composer strict et audit sans avis de vulnérabilité. Repassage final après revue en cours.
- Première couverture avant compléments : 74,11 % lignes, 66,10 % méthodes. Ajout des listes admin, actions médias, changement mot de passe, refus commandes invalides, seeder galerie et santé indisponible ; mesure finale à faire.

### Limites et risques encore accessibles uniquement en exploitation
- Migrations historiques : ancien périmètre et suppressions de tables ; analyse statique/syntaxe seulement, aucune migration exécutée. Installation neuve testée via mappings. Définir une baseline de migrations d’installation exige revue et validation de l’historique réel.
- Corrélation précoce corrigée avant publication : commande draft persistée avant l’appel, order_id transmis aux métadonnées Session et PaymentIntent, clé d’idempotence API par commande. Événement précoce et réponse interrompue récupèrent la commande depuis la référence signée, verrouillée et validée ; référence contradictoire refusée. Les anciens checkouts sans cette référence et sans ID PaymentIntent connu restent à rapprocher en exploitation (503 retryable). Un appel interrompu peut laisser un draft pending ; il ne prouve pas un paiement. Les doubles clics sont limités au même panier/session, pas une garantie d’idempotence entre plusieurs navigateurs.
- Le retour avant réception du webhook conserve le panier et renvoie au panier. Pas de faux succès, mais parcours utilisateur réel Stripe non vérifié.
- MySQL local est encore le conteneur déjà actif exposé sur 0.0.0.0:3306 ; le nouveau binding 127.0.0.1 ne prendra effet qu’à sa recréation autorisée. Aucun changement de service en cours.
- Tests HTTP/services/transactions réels localement ; API Stripe doublée, SMTP nul. Les pages EasyAdmin sont parcourues sans validation exhaustive de tous les formulaires visuels ; aucune interface modifiée. Pas de charge/benchmark ni simulation de réseau réel.
- Le seeder galerie remet published=true et l’ordre alphabétique lors d’une répétition : comportement existant conservé, ne pas l’utiliser comme synchronisation transparente des éditions admin.
- Aucun déploiement, alerte, worker, cron ni reprise/restore distant réellement exercé. Les exemples ops et le runbook requièrent adaptation/installation par l’opérateur. Les logs du reverse proxy et la rétention des données tiers Stripe/SMTP restent hors accès.

- P2 confirmé : Monolog production bufferise les logs de route (token/URI) et normalise les exceptions. Ajout de SensitiveLogProcessor limité à prod : tokens/secrets/emails/contextes objets et messages d’exception masqués, classe/fichier/ligne conservés. Test dédié et compilation prod + enregistrement du processor vérifiés. Le diagnostic vendor debug:container sous PHP 8.5 émet une dépréciation strlen(null) dans TextDescriptor ; aucune modification vendor, lint prod réussi.

## Résultat final local
- 128 tests / 567 assertions réussis, zéro erreur, zéro échec, zéro skip ou dépréciation PHPUnit ; +108 cas par rapport aux 20 initiaux.
- Couverture Xdebug : 89,27 % lignes (1940/2173), 73,08 % méthodes (220/301), 36,92 % classes entièrement couvertes (24/65). Rapport local coverage/clover.xml et summary.txt non versionnés. La couverture restante concerne notamment branches d’erreur admin, formulaires EasyAdmin et diagnostics de configuration ; pas de prétention de couverture exhaustive des services distants.
- scripts/quality.sh réussi : 38 YAML, conteneur test, mappings et schéma test cohérents, PHPStan 10 sans baseline/ignorances, PHPUnit, Composer strict et audit (aucun avis de vulnérabilité), syntaxe de tous les scripts shell.
- Compose validé avec docker compose config --quiet. Conteneur prod linté sans appel SMTP/Stripe/DB distant, processor logs effectivement enregistré.
- Sauvegarde et restauration locales avec comparaison des données de formules + refus d’écrasement vérifiés. Ni restauration complète DB+médias+config ni déploiement réel prétendus validés.
- Git diff --check réussi ; changements frontend et suppressions de skills préexistants exclus de la mission. Dépôt distant vérifié public, main=116d96b10e96327ac0216105add418d1a2d09f97, zéro webhook GitHub et aucun workflow antérieur. Actions activées ; nouvelle CI backend sans étape de déploiement.
- Publication autorisée sur audit/backend-2026-10-06 ; résultat distant/CI à vérifier après push. Pas de commit sur main, de merge ou de déploiement.

### Complément vérifié avant commit
P2 : PaymentIntent inconnu avant la complétion et commande perdue si Stripe réussit avant une interruption locale. Correction dans PaymentController/StripePaymentService/StripeWebhookHandler : snapshot draft persistant, métadonnées order_id propagées et idempotence API par commande, rapprochement uniquement sur référence signée après contrôle des IDs et verrou. Aucun changement de schéma. Tests supplémentaires événement précoce/réponse perdue et refus Session/PI contradictoires ; 22 tests de paiement / 125 assertions verts. La valeur draft_ dans le champ session existant représente une tentative non finalisée ; aucun draft ne doit être comptabilisé comme paiement.

Base Git locale d7f488eba1ab3d15fecf4f6a85de724a352d10ca : mise à jour de dépendances déjà committée avant cette mission, conservée comme ancêtre de la branche audit. Aucun fichier frontend ajouté au commit de cette mission. Suppressions de skills et header non committés exclus.

Le contrôle automatique a refusé le push vers le dépôt public origin, demandant une autorisation explicite pour cette destination publique. Aucun push ou déploiement effectué ; commit local autorisé, publication en attente de décision utilisateur. Scan de secrets et diff validés.
