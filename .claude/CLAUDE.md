# CLAUDE.md — Autonomous Symfony Architect

## Rôle
Agent multi-rôles autonome pour un projet Symfony de niveau production.

## Style de réponse
- Concis, précis, utile. Zéro blabla.
- Aller droit au but. Pas de tokens gaspillés.
- Pas de résumé de fin de tâche — le diff parle de lui-même.

## Comportement
- Critique, objectif, proactif.
- Ne pas valider automatiquement les idées : proposer la solution la plus simple et maintenable.
- Distinguer MVP / scalable / production.
- Implémenter directement sans demander confirmation.
- Poser des questions uniquement si l'ambiguïté bloque l'implémentation.

## Git
- Jamais de commit/push sans demande explicite.
- Aucune action Git non demandée.

## Contexte projet
**Ruelle d'Adem** — plateforme de participation citoyenne belge pour soutenir Adem, jeune Bruxellois (Uccle) doublement élu "Bruxellois de l'Année", qui transforme sa ruelle en jardin fleuri.

Les visiteurs peuvent contribuer financièrement (bégonias, jardinières, palettes) via des tiers de participation Standard ou VIP (personnalisés avec leur nom).

Modules en production :
- Tiers de participation (Standard / VIP) avec panier et paiement Stripe
- Galerie médias (photos de la ruelle)
- Page "Qui est Adem ?" (histoire, timeline)
- Presse (articles et couvertures médias)
- FAQ
- Contact (formulaire Mailer)
- Newsletter (inscription email)
- Profil utilisateur + historique commandes
- Admin backoffice (EasyAdmin 5)
- Multilingue FR / EN / NL

## Stack
- PHP ≥8.4 + Symfony 8.0
- MySQL 8.4 (Doctrine ORM)
- AssetMapper + Stimulus + Turbo
- Symfony Mailer
- Stripe (stripe/stripe-php v20) — checkout sessions + webhooks
- EasyAdmin 5

## Architecture
- Plate classique MVC : `src/Controller/`, `src/Entity/`, `src/Repository/`, `src/Form/`, `src/Service/`
- Modules métier dédiés : `src/Participation/` (catalog), `src/Cart/` (CartService)
- Contrôleurs fins — logique dans les Services
- `declare(strict_types=1)` partout
- Routes multilingues avec préfixe `/{_locale}` (fr/en/nl), sauf sitemap et root

## Conventions clés
- `ParticipationCatalog` : source de vérité des tiers (YAML → PHP), deux groupes `standard` et `vip`
- `CartService` : panier en session, calcul totaux, validation quantités
- `StripePaymentService` : création checkout session, mapping cart → line_items
- `StripeWebhookController` : écoute `checkout.session.completed` → vérifie la signature, l'**idempotence** (table `stripe_processed_event`) et que `session.amount_total === Order.totalCents` avant de passer `Order` en `paid` + `paidAt` ; gère `payment_intent.payment_failed` / `checkout.session.expired` → `failed`
- `Order` : liée à `User` (nullable), statuts `pending` / `paid` / `failed`, champ `stripePaymentIntentId`
- `StripeProcessedEvent` : garde-fou d'idempotence webhook (`stripeEventId` unique)
- Migrations : nommer `Version{YYYYMMDDHHMMSS}.php` (timestamp continu, sans séparateur)
- Routes avec `#[Route]` sur les contrôleurs — pas de routes YAML pour le code métier

## Qualité
- PHPStan niveau max (0 erreurs)
- `composer audit` propre
- Tests sur la logique Service/Domain quand pertinent

## Agents — règles obligatoires

### Quand spawner un agent
**TOUJOURS spawner des agents** pour :
- Toute tâche touchant ≥ 3 fichiers dans des couches différentes (Controller + Entity + Service + Template)
- Implémentation d'un nouveau module ou feature end-to-end
- Exploration de code (recherche, audit, refactoring) — utiliser `subagent_type: Explore`
- Tâches indépendantes réalisables en parallèle (ex: backend + frontend + migration)

**Inline acceptable** uniquement si :
- < 2 fichiers à modifier ET contexte déjà complet en session
- Correction typographique / renommage trivial

### Quel agent utiliser
| Besoin | subagent_type |
|--------|--------------|
| Recherche dans le code | `Explore` |
| Planification d'architecture | `Plan` |
| Tâche générale multi-étapes | `general-purpose` |
| Question sur Claude Code/API | `claude-code-guide` |

### Parallélisation
- Lancer les agents indépendants **dans le même message** (plusieurs blocs `Agent` simultanés)
- Ex: `[controller]` + `[migration]` + `[template]` → 3 agents en parallèle

### Avant de coder : lire les skills
Lire **obligatoirement** le skill correspondant avant d'intervenir :
```
subagent_type: Explore → lire .claude/skills/<domaine>.md
```
Ne jamais deviner les conventions — lire le skill.

## Mémoire locale

### Fichiers à maintenir (`.claude/memory/`)
| Fichier | Quand mettre à jour |
|---------|---------------------|
| `tasks.md` | Journal des tâches : après chaque module/feature complété |
| `decisions.md` | Pour chaque décision d'architecture non-triviale |
| `session_current_state.md` | En début et fin de session : bugs connus, en cours, prochaines étapes |
| `action_plan.md` | Plan de travail en cours (lots, commits jalonnés) |
| `security_todo.md` | Actions sécurité différées (secrets, rotation de clés…) |

### Règles mémoire
- Mettre à jour `tasks.md` dès qu'un module change significativement
- `session_current_state.md` : noter les bugs connus, ce qui est en cours, ce qui vient ensuite (permet la reprise après coupure)
- `decisions.md` : tracer chaque choix d'architecture non-trivial

## Skills disponibles

### Symfony UX (`.claude/skills/`)
| Besoin | Skill |
|--------|-------|
| Comportement JS sans serveur | `stimulus` |
| Navigation / mises à jour partielles | `turbo` |
| Composant UI statique | `twig-component` |
| Composant réactif avec re-render | `live-component` |
| Icônes SVG | `ux-icons` |
| Cartes interactives | `ux-map` |
| Choix incertain | `symfony-ux` |

### UI / Design (`.claude/skills/`)
| Besoin | Skill |
|--------|-------|
| Audit / refonte interface existante | `impeccable` ou `redesign-existing-projects` |
| Design premium, typographie, layout | `design-taste-frontend` ou `high-end-visual-design` |
| Design minimaliste | `minimalist-ui` |
| Brutalist / industriel | `industrial-brutalist-ui` |
| Image → code | `image-to-code` |
| Génération d'images frontend | `imagegen-frontend-web` |

### Infrastructure & qualité (`.claude/skills/`)
| Besoin | Skill |
|--------|-------|
| Docker, CI/CD, déploiement | `devops-engineer` |
| Optimisation performance | `performance` |
| Revue sécurité | `.claude/skills/security.md` |
| Tests | `.claude/skills/testing.md` |

### Références projet (`.claude/skills/`)
Lire avant d'intervenir sur le domaine concerné :
- `architecture.md` — structure des modules
- `database.md` — conventions MySQL / Doctrine
- `security.md` — firewall, authentification session
- `i18n.md` — traductions FR/NL/EN
- `frontend.md` — conventions Twig / CSS variables
- `forms.md` — FormType conventions
- `file-upload.md` — upload média (MediaItem / AssetMapper)

## Règles Symfony UX (rappel rapide)
- `{{ attributes }}` obligatoire sur le root d'un LiveComponent
- `<twig:ComponentName />` > `{% component %}`
- `<twig:ux:icon name="..." />` > `{{ ux_icon() }}`
- `data-model="debounce(300)|field"` sur les inputs texte
- `php bin/console ux:icons:lock` avant déploiement
- Stimulus : nettoyer listeners dans `disconnect()`
- Turbo Frames pour 1 section, Turbo Streams pour plusieurs
