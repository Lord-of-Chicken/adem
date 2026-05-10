# Database Skill

## Stack

- **MySQL 8.x**
- **Doctrine ORM 3.x** (`doctrine/orm: ^3.6`)
- **Doctrine Migrations 4.x** (`doctrine/doctrine-migrations-bundle: ^4.0`)

## Doctrine ORM 3.x breaking changes to know

```php
// ORM 3: find() returns null, not false
$entity = $repository->find($id); // null if not found

// ORM 3: lazy loading proxies removed — use explicit joins
// BAD: $report->getAnimal()->getName() (triggers lazy load, removed in ORM 3)
// GOOD: JOIN in DQL/QueryBuilder

// ORM 3: EntityRepository::findBy() still works, but avoid for complex queries
// Use QueryBuilder or custom DQL methods in Repository classes
```

## UUID primary keys — mandatory

```php
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class AnimalReport {
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    public function __construct() {
        $this->id = Uuid::v7(); // v7 = time-ordered, better for PostgreSQL indexes
    }
}
```

## Migrations — mandatory workflow

```bash
# Always generate, never write manually
symfony console doctrine:migrations:diff

# Review generated migration before running
symfony console doctrine:migrations:migrate

# Never modify schema directly in production
```

## Repository pattern

```php
// Interface in Domain layer (port)
interface AnimalReportRepositoryInterface {
    public function findById(Uuid $id): ?AnimalReport;
    public function save(AnimalReport $report): void;
    public function findLostInCity(string $city): array;
}

// Implementation in Infrastructure layer (adapter)
class DoctrineAnimalReportRepository extends ServiceEntityRepository
    implements AnimalReportRepositoryInterface {

    public function findLostInCity(string $city): array {
        return $this->createQueryBuilder('r')
            ->where('r.location.city = :city')
            ->andWhere('r.status = :status')
            ->setParameter('city', $city)
            ->setParameter('status', ReportStatus::LOST)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

## MySQL-specific notes

- UUID stored as `CHAR(36)` or `BINARY(16)` (use `uuid` Doctrine type from `symfony/uid`)
- `JSON` type supported since MySQL 5.7 for flexible metadata
- Full-text search via `FULLTEXT` index + `MATCH() AGAINST()` (see `search.md`)
- Use `utf8mb4` charset everywhere — never `utf8` (incomplete Unicode support)

## Performance rules

- Avoid N+1: always use `JOIN FETCH` or `addSelect` for related entities
- Add indexes on foreign keys, status columns, and search fields
- Use `EXPLAIN ANALYZE` before shipping any non-trivial query
- Paginate all list queries — never `findAll()` in production code
- Doctrine result cache for read-heavy queries (use Redis adapter)

## Rules

- Migrations mandatory — no schema changes without migration files
- No schema changes directly in production
- Relations explicit (`#[ORM\ManyToOne]`, etc.) — never rely on convention
- No circular entity dependencies
- Soft-delete via status field, not `deletedAt` (prefer explicit state machines)
