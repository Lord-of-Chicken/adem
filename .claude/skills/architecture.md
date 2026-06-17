# Architecture Skill

> Source de vérité de la structure du projet **Ruelle d'Adem**. À lire avant toute intervention multi-couches.

## Modèle : MVC Symfony classique (PAS de DDD / CQRS / Hexagonal)

Pas de couches Domain/Application/Infrastructure, pas de Command/Query Bus, pas de ports/adapters.
Convention Symfony standard : contrôleurs fins, logique métier dans des Services, entités Doctrine avec attributs.

## Structure des dossiers

```
src/
  Controller/        # HTTP uniquement — fins, délèguent aux Services
  Entity/            # Entités Doctrine (attributs ORM directement dessus)
  Repository/        # ServiceEntityRepository + méthodes de requête
  Form/              # FormType Symfony
  Service/           # Logique métier (StripePaymentService, ...)
  Cart/              # Module métier panier — CartService (panier en session)
  Participation/     # Module métier tiers — ParticipationCatalog (catalogue tiers)
  Enum/              # Enums PHP (OrderStatus, ...)
templates/           # Twig (server-rendered)
config/              # Config Symfony, routes par attributs
migrations/          # VersionYYYYMMDDHHMMSS.php
translations/        # FR / EN / NL
```

## Conventions obligatoires

- `declare(strict_types=1);` en tête de **chaque** fichier PHP.
- Routes via attribut `#[Route]` sur les contrôleurs (pas de YAML pour le code métier), préfixe locale `#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|nl'])]` — sauf sitemap et root.
- Entités Doctrine : attributs `#[ORM\Entity]`, `#[ORM\Column]`, `#[ORM\ManyToOne]` directement sur l'entité (standard Symfony — pas de mapping XML séparé).
- Migrations nommées `Version{YYYYMMDDHHMMSS}.php` (timestamp continu, sans séparateur), toujours générées via `doctrine:migrations:diff`.
- Stack : PHP ≥8.4, Symfony 8.0, **MySQL 8.4** (Doctrine ORM), AssetMapper + Stimulus + Turbo, Stripe v20, EasyAdmin 5.

## Contrôleur fin → Service

```php
// src/Controller/CheckoutController.php
#[Route('/{_locale}', requirements: ['_locale' => 'fr|en|nl'])]
final class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'checkout', methods: ['POST'])]
    public function checkout(CartService $cart, StripePaymentService $stripe): Response
    {
        $order   = $stripe->createOrderFromCart($cart->getCart());
        $session = $stripe->createCheckoutSession($order);

        return $this->redirect($session->url);
    }
}
```

La logique (création de l'`Order`, mapping panier → line_items Stripe) vit dans le Service, jamais dans le contrôleur.

## Modules métier

### `src/Participation/` — ParticipationCatalog
Source de vérité des tiers de participation (chargés YAML → PHP), deux groupes `standard` et `vip`.
Expose les `ParticipationTier` disponibles ; pas d'accès direct au YAML ailleurs dans le code.

### `src/Cart/` — CartService
Panier stocké en session : ajout/retrait de tiers, calcul des totaux (en centimes), validation des quantités.
Aucune persistance DB du panier — il devient un `Order` au checkout.

## Domaine Stripe (paiement)

- `StripePaymentService` : crée l'`Order` depuis le panier, construit la Checkout Session, mappe cart → `line_items`.
- `StripeWebhookController` : écoute `checkout.session.completed`. Vérifie dans l'ordre : signature webhook, **idempotence** (table `stripe_processed_event` via `StripeProcessedEvent.stripeEventId` unique), puis que `session.amount_total === Order.totalCents`. Si OK → `Order` passe `paid` + `paidAt`. Gère `payment_intent.payment_failed` / `checkout.session.expired` → `failed`.

## Entités réelles

| Entité | Rôle |
|---|---|
| `Order` | Commande, liée à `User` (nullable), statut via `enum OrderStatus` (`pending`/`paid`/`failed`), `stripePaymentIntentId`, `totalCents`, `paidAt` |
| `User` | Compte utilisateur (auth session, historique commandes) |
| `ParticipationTier` | Tier de participation (Standard / VIP) |
| `MediaItem` | Élément de la galerie média (photos de la ruelle) |
| `StripeProcessedEvent` | Garde-fou d'idempotence webhook (`stripeEventId` unique) |
| `NewsletterConfirmation` | Double opt-in newsletter |

## OrderStatus (enum)

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid    = 'paid';
    case Failed  = 'failed';
}
```

## Règles

- Contrôleurs fins : pas de logique métier, pas de requête Doctrine complexe dedans — déléguer aux Services / Repositories.
- Un Service = une responsabilité. Pas de god service.
- Entités riches mais sans I/O : pas de persistance ni d'appel externe dans une entité.
- Pas de couplage entre modules `Cart` et `Participation` autre que `CartService` consommant `ParticipationCatalog`.
- Toujours générer une migration quand le schéma change — jamais de modification directe en prod.
- Webhook Stripe : signature + idempotence + cohérence montant **avant** tout changement d'état d'`Order`.
