# Architecture Skill

## Default model: Hexagonal + DDD-lite + CQRS

Each feature = one module. Modules are isolated and communicate only via Domain Events or Application Services.

## Folder structure per module

```
src/
  {Module}/
    Domain/
      Entity/           # Aggregate roots and entities
      ValueObject/      # Immutable domain primitives
      Event/            # Domain events (past tense: AnimalReportCreated)
      Repository/       # Interfaces only (ports)
      Exception/        # Domain exceptions
      Service/          # Pure domain services (no I/O)
    Application/
      Command/          # Write-side: command + handler pairs
      Query/            # Read-side: query + handler + result DTO
      DTO/              # Input DTOs (validated) + Output DTOs
      EventListener/    # Reacts to domain events
    Infrastructure/
      Doctrine/         # Repository implementations, Doctrine mappings
      Messenger/        # Async message handlers
      External/         # Third-party adapters (APIs, storage...)
    UI/
      Controller/       # HTTP layer only
      Form/             # Symfony Forms
      Twig/             # Twig/Live Components
```

## CQRS pattern

```php
// Command = intent to change state (no return value)
final readonly class CreateAnimalReport { ... }
final class CreateAnimalReportHandler { public function __invoke(...): void }

// Query = read without side effects (returns DTO)
final readonly class GetAnimalReportById { public function __construct(public Uuid $id) {} }
final class GetAnimalReportByIdHandler { public function __invoke(...): AnimalReportDTO }
```

## DDD-lite rules

- **Aggregate root**: controls invariants for its cluster of entities
- **Value Object**: immutable, equality by value, not identity
- **Domain Event**: named in past tense (`AnimalReportCreated`, `AnimalFound`)
- **Repository interface** in Domain layer, **implementation** in Infrastructure
- No Doctrine annotations/attributes on Domain entities — use XML or separate mapping files if purity matters

## Dependency rules

```
UI → Application → Domain
Infrastructure → Domain (implements ports)
Infrastructure → Application (implements interfaces)
Domain has ZERO external dependencies
```

## Goals

- Isolation of business logic (testable without Symfony or Doctrine)
- Replaceable adapters (swap Doctrine for another ORM without touching Domain)
- Scalability (modules independently deployable in the future)

## Never

- Tight coupling between modules (use events or shared kernel only)
- Circular dependencies between layers
- God services (one service = one responsibility)
- Anemic domain model (behavior belongs on entities, not only in services)
- Infrastructure concerns (Doctrine, HTTP) in Domain layer
