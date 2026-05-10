# Symfony 8 Skill

## Core principles

- Controllers are thin — only dispatch commands/queries and return responses
- Services are stateless and injectable via constructor promotion
- Messenger for all async operations
- Domain Events for decoupling modules
- Symfony Validator on DTOs, never on entities directly

## PHP 8.4 features to use

```php
// Property hooks (computed, read-only derived values)
class AnimalReport {
    public string $slug {
        get => strtolower(str_replace(' ', '-', $this->name));
    }
    public private(set) Uuid $id; // asymmetric visibility
}

// Readonly classes for Value Objects
readonly class Location {
    public function __construct(
        public float $lat,
        public float $lng,
        public string $city,
    ) {}
}

// Constructor promotion everywhere
public function __construct(
    private readonly MessageBusInterface $commandBus,
    private readonly QueryBusInterface $queryBus,
) {}
```

## Attribute-based config — always

```php
#[Route('/reports', name: 'animal_report_index', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
#[Cache(maxage: 60, public: false)]
public function index(): Response {}

// Autowire with attributes
#[Autowire(service: 'messenger.bus.command')]
private MessageBusInterface $commandBus;
```

## Symfony Messenger patterns

```php
// Command — mutates state
final readonly class ReportLostAnimal {
    public function __construct(
        public string $animalName,
        public string $species,
        public Location $location,
        public UserId $reportedBy,
    ) {}
}

// Handler — one command, one handler
#[AsMessageHandler]
final class ReportLostAnimalHandler {
    public function __invoke(ReportLostAnimal $command): void {
        // domain logic here
    }
}
```

## Must use

- Doctrine ORM 3.x (no raw SQL unless performance-justified and documented)
- Symfony Security (Voters for access control)
- Symfony Validator (constraints on DTOs via attributes)
- Symfony Messenger (command bus + event bus)
- Symfony Serializer (for API responses, never manual array building)

## Must never do

- Logic in controllers (beyond dispatch + response)
- Logic in Twig templates
- Expose Doctrine entities in API or UI layer
- Use public properties on entities without asymmetric visibility
- Skip `declare(strict_types=1)`
