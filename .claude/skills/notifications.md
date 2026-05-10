# Notifications Skill

## Context

Core flow: when a found animal matches a lost report, notify the owner immediately.

## Stack (already installed)

- `symfony/mailer` — transactional emails
- `symfony/notifier` — multi-channel (email, SMS)
- `symfony/messenger` — async dispatch

## Email via Symfony Mailer

```php
// src/AnimalReport/Infrastructure/Notification/MatchFoundEmail.php
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

final class MatchFoundEmail extends TemplatedEmail {
    public function __construct(AnimalReport $lostReport, AnimalReport $foundReport) {
        parent::__construct();
        $this
            ->from(new Address('contact@animauxperdu.fr', 'Animaux Perdu'))
            ->to(new Address($lostReport->owner->email, $lostReport->owner->name))
            ->subject('Un animal correspondant a été trouvé !')
            ->htmlTemplate('emails/match_found.html.twig')
            ->context([
                'lost_report'  => $lostReport,
                'found_report' => $foundReport,
            ]);
    }
}
```

## Always send async via Messenger

```php
// Application event listener
#[AsEventListener]
final class NotifyOwnerOnMatch {
    public function __invoke(AnimalReportMatchedEvent $event): void {
        // Never block HTTP response — always async
        $this->bus->dispatch(
            new SendMatchNotification($event->lostReportId, $event->foundReportId)
        );
    }
}

#[AsMessageHandler]
final class SendMatchNotificationHandler {
    public function __invoke(SendMatchNotification $message): void {
        $lost  = $this->reports->findById($message->lostReportId);
        $found = $this->reports->findById($message->foundReportId);
        $this->mailer->send(new MatchFoundEmail($lost, $found));
    }
}
```

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
            App\AnimalReport\Application\Command\SendMatchNotification: async
```

## Notification types to implement

| Event | Channel | Priority |
|---|---|---|
| Match found | Email | Critical |
| Report published | Email | High |
| Report expires in 7 days | Email | Medium |
| Report resolved | Email | Low |
| New reports in your city | Email digest | Low |

## Twig email template

```twig
{# templates/emails/match_found.html.twig #}
{% extends '@email/default/notification.html.twig' %}

{% block content %}
<p>Bonjour {{ lost_report.owner.firstName }},</p>
<p>Un animal correspondant à <strong>{{ lost_report.animalName }}</strong> a été trouvé.</p>
<a href="{{ url('animal_report_show', {id: found_report.id}) }}"
   style="background:#e86c00;color:#fff;padding:12px 24px;text-decoration:none;display:inline-block">
    Voir le signalement
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

- Always send emails **async** via Messenger — never in the HTTP request cycle
- Use `TemplatedEmail` with Twig — never string concatenation
- Retry strategy mandatory for transient SMTP failures
- Test in dev via Mailpit (see compose.override.yaml)
- Unsubscribe link mandatory in any digest/marketing email
- Transactional emails (match found, expiry) always go through regardless of preferences
