# State Machine Skill

## Context

Animal reports have a lifecycle. Symfony Workflow handles this natively.

## Report lifecycle

```
CREATED → PUBLISHED → MATCHED → RESOLVED
                ↓
            EXPIRED (after 90 days without activity)
            CLOSED  (manually closed by owner)
```

## Install

```bash
composer require symfony/workflow
```

## Workflow definition

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        animal_report:
            type: state_machine
            marking_store:
                type: method
                property: status
            supports:
                - App\AnimalReport\Domain\Entity\AnimalReport
            initial_marking: created
            places:
                - created
                - published
                - matched
                - resolved
                - expired
                - closed
            transitions:
                publish:
                    from: created
                    to:   published
                match:
                    from: published
                    to:   matched
                resolve:
                    from: [published, matched]
                    to:   resolved
                expire:
                    from: published
                    to:   expired
                close:
                    from: [created, published, matched]
                    to:   closed
                reopen:
                    from: [expired, closed]
                    to:   published
```

## Entity integration

```php
enum ReportStatus: string {
    case CREATED   = 'created';
    case PUBLISHED = 'published';
    case MATCHED   = 'matched';
    case RESOLVED  = 'resolved';
    case EXPIRED   = 'expired';
    case CLOSED    = 'closed';
}

class AnimalReport {
    #[ORM\Column(type: 'string', enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::CREATED;

    public function getStatus(): ReportStatus { return $this->status; }
    public function setStatus(ReportStatus $status): void { $this->status = $status; }
}
```

## Application layer usage

```php
#[AsMessageHandler]
final class PublishAnimalReportHandler {
    public function __construct(
        private readonly WorkflowInterface $animalReportWorkflow,
        private readonly AnimalReportRepositoryInterface $reports,
    ) {}

    public function __invoke(PublishAnimalReport $command): void {
        $report = $this->reports->findById($command->reportId)
            ?? throw new AnimalReportNotFoundException($command->reportId);

        if (!$this->animalReportWorkflow->can($report, 'publish')) {
            throw new InvalidTransitionException('publish', $report->getStatus());
        }

        $this->animalReportWorkflow->apply($report, 'publish');
        $this->reports->save($report);
    }
}
```

## Workflow events → Domain Events

```php
#[AsEventListener(event: 'workflow.animal_report.completed.match')]
final class OnAnimalReportMatched {
    public function __construct(private readonly MessageBusInterface $eventBus) {}

    public function __invoke(CompletedEvent $event): void {
        $this->eventBus->dispatch(
            new AnimalReportMatchedEvent($event->getSubject()->getId())
        );
    }
}
```

## Debug

```bash
symfony console workflow:dump animal_report | dot -Tpng -o workflow.png
```

## Rules

- Transitions enforced by workflow — never bypass with direct `setStatus()`
- Forbidden transitions throw domain exceptions, not silent failures
- Workflow events trigger Domain Events via Messenger
- Every transition is logged for audit trail
