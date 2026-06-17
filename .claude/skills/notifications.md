# Notifications Skill

## Context

Transactional emails only — no real-time push. Three real flows:
- **Order confirmation** — after an `Order` is marked `paid` (via the Stripe webhook).
- **Contact form** — message sent to the project mailbox from the contact page.
- **Newsletter double opt-in** — confirmation email with a one-time token (`NewsletterConfirmation`).

## Stack (already installed)

- `symfony/mailer` — transactional emails
- `symfony/messenger` — async dispatch (install/enable when async is needed)

## Email via Symfony Mailer

```php
// src/Service/Email/OrderConfirmationEmail.php
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

final class OrderConfirmationEmail extends TemplatedEmail {
    public function __construct(Order $order) {
        parent::__construct();
        $this
            ->from(new Address('contact@ruelledadem.be', "Ruelle d'Adem"))
            ->to(new Address($order->getEmail(), $order->getCustomerName()))
            ->subject('Merci pour votre participation !')
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);
    }
}
```

## Send async via Messenger (recommended)

Never block the HTTP response — dispatch the email through Messenger.

```php
#[AsMessageHandler]
final class SendOrderConfirmationHandler {
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(SendOrderConfirmation $message): void {
        $order = $this->orders->find($message->orderId);
        if ($order === null) {
            return;
        }
        $this->mailer->send(new OrderConfirmationEmail($order));
    }
}
```

The webhook (`StripeWebhookController`) only flips `Order` to `paid`; the confirmation
email is dispatched from there (or via a domain event), never sent synchronously in the
webhook request cycle.

## Messenger transport config

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
        routing:
            App\Message\SendOrderConfirmation: async
```

## Notification types to implement

| Event | Channel | Priority |
|---|---|---|
| Order paid (confirmation + receipt) | Email | Critical |
| Order failed / payment declined | Email | High |
| Contact form submitted | Email (to project mailbox) | High |
| Newsletter subscription (double opt-in) | Email | High |

## Newsletter double opt-in

```php
// On subscribe: create NewsletterConfirmation with a unique token, email the confirm link.
$confirmation = new NewsletterConfirmation($email, $token);
$em->persist($confirmation);
$mailer->send(new NewsletterConfirmEmail($email, $token));

// On GET /newsletter/confirm/{token}: validate token, mark confirmed, then persist subscriber.
```

## Twig email template

```twig
{# templates/emails/order_confirmation.html.twig #}
{% extends 'emails/base.html.twig' %}

{% block content %}
<p>Bonjour {{ order.customerName }},</p>
<p>Merci pour votre participation au projet de la Ruelle d'Adem.</p>
<p>Montant : <strong>{{ (order.totalCents / 100)|number_format(2, ',', ' ') }} €</strong></p>
<a href="{{ url('order_show', {id: order.id}) }}"
   style="background:#2e7d32;color:#fff;padding:12px 24px;text-decoration:none;display:inline-block">
    Voir ma commande
</a>
{% endblock %}
```

## Local dev: Mailpit

```yaml
# compose.override.yaml — add mailpit service
services:
    mailer:
        image: axllent/mailpit
        ports:
            - "1025:1025"   # SMTP
            - "8025:8025"   # Web UI
```

```bash
# .env.dev
MAILER_DSN=smtp://localhost:1025
# Preview emails: http://localhost:8025
```

## Rules

- Send emails **async** via Messenger — never inside the Stripe webhook or checkout request cycle
- Use `TemplatedEmail` with Twig — never string concatenation
- Retry strategy mandatory for transient SMTP failures
- Test in dev via Mailpit (see compose.override.yaml)
- Newsletter is double opt-in; unsubscribe link mandatory in any marketing/digest email
- Transactional emails (order confirmation, payment failure) always go through regardless of preferences
- Respect the multilingual context (FR/EN/NL): localize subject and template per recipient locale
