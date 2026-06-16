# Database Skill

## Stack

- **MySQL 8.4**
- **Doctrine ORM 3.x** (`doctrine/orm: ^3.6`)
- **Doctrine Migrations 4.x** (`doctrine/doctrine-migrations-bundle: ^4.0`)

## Doctrine ORM 3.x breaking changes to know

```php
// ORM 3: find() returns null, not false
$entity = $repository->find($id); // null if not found

// ORM 3: lazy loading proxies removed — use explicit joins
// BAD: $order->getUser()->getEmail() (triggers lazy load, removed in ORM 3)
// GOOD: JOIN in DQL/QueryBuilder

// ORM 3: EntityRepository::findBy() still works, but avoid for complex queries
// Use QueryBuilder or custom DQL methods in Repository classes
```

## UUID primary keys — recommended

```php
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class MediaItem {
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    public private(set) Uuid $id;

    public function __construct() {
        $this->id = Uuid::v7(); // v7 = time-ordered, better for B-tree indexes
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

## Repository pattern (Symfony standard)

```php
// src/Repository/OrderRepository.php
// One ServiceEntityRepository per entity, query methods live here.
class OrderRepository extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Order::class);
    }

    /** @return Order[] */
    public function findPaidForUser(User $user): array {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', OrderStatus::Paid)
            ->orderBy('o.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

## MySQL-specific notes

- UUID stored as `CHAR(36)` or `BINARY(16)` (use `uuid` Doctrine type from `symfony/uid`)
- `JSON` type supported for flexible metadata (e.g. `MediaItem` attributes)
- Use `utf8mb4` charset everywhere — never `utf8` (incomplete Unicode support)

## Performance rules

- Avoid N+1: always use `JOIN FETCH` or `addSelect` for related entities
- Add indexes on foreign keys, status columns, and frequently filtered fields
- Use `EXPLAIN ANALYZE` before shipping any non-trivial query
- Paginate all list queries — never `findAll()` in production code
- Doctrine result cache for read-heavy queries (use Redis adapter)

## Rules

- Migrations mandatory — no schema changes without migration files
- No schema changes directly in production
- Relations explicit (`#[ORM\ManyToOne]`, etc.) — never rely on convention
- No circular entity dependencies
- Statuses via PHP enum stored as string (e.g. `OrderStatus`), not magic strings
