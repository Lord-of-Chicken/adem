# Performance Skill

## Profiling first — never optimize blind

```bash
# Symfony Profiler (dev)
http://localhost/_profiler

# Check query count — target: < 10 queries per page
# Visible in Profiler → Doctrine tab

# Blackfire (install when needed)
composer require --dev blackfire/php-sdk
```

## Doctrine N+1 — most common issue

```php
// BAD — N+1: one query per order to load its line items
foreach ($orders as $order) {
    echo $order->getLines()->count(); // lazy load per iteration
}

// GOOD — single JOIN query
$orders = $this->createQueryBuilder('o')
    ->leftJoin('o.lines', 'l')
    ->addSelect('l')
    ->where('o.status = :status')
    ->setParameter('status', OrderStatus::Paid)
    ->getQuery()
    ->getResult();
```

## HTTP cache

```php
#[Route('/galerie', name: 'gallery_index')]
public function index(): Response {
    $response = $this->render('gallery/index.html.twig', [...]);
    $response->setPublic();
    $response->setMaxAge(300);         // 5 min browser
    $response->setSharedMaxAge(60);    // 1 min reverse proxy
    return $response;
}
```

## Symfony Cache (Redis)

```php
// config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: '%env(REDIS_URL)%'

// Usage
public function getPaidOrderCount(): int {
    return $this->cache->get('orders.paid.count', function (ItemInterface $item) {
        $item->expiresAfter(300);
        return $this->repository->countPaid();
    });
}
```

## Doctrine query cache

```php
$result = $this->createQueryBuilder('o')
    ->where('o.status = :status')
    ->setParameter('status', OrderStatus::Paid)
    ->getQuery()
    ->enableResultCache(300, 'orders_paid')
    ->getResult();
```

## MySQL indexes

```sql
-- Order listing (profile history, admin)
CREATE INDEX idx_order_status      ON `order` (status);
CREATE INDEX idx_order_created_at  ON `order` (created_at DESC);

-- Per-user history filter
CREATE INDEX idx_order_user_status ON `order` (user_id, status);

-- Webhook idempotency lookup (also enforced by a UNIQUE constraint)
CREATE UNIQUE INDEX uniq_stripe_event ON stripe_processed_event (stripe_event_id);
```

## Asset performance (Asset Mapper)

```bash
# Compile for production
symfony console asset-map:compile

# TailwindCSS JIT purges unused classes automatically in production
```

## OPcache + APCu (FrankenPHP / production)

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   ; never in production
apc.enable_cli=1
apc.shm_size=64M
```

## Checklist before shipping a feature

- [ ] Symfony Profiler: query count < 10 per page
- [ ] No `findAll()` without pagination
- [ ] JOIN FETCH for all displayed associations
- [ ] Index on all WHERE / ORDER BY columns
- [ ] Cache headers on public read endpoints
- [ ] Redis cache for computed/aggregated data

## Rules

- Profile before optimizing — always use data, not intuition
- Fix N+1 with JOIN FETCH — not `fetch: EAGER` on associations
- Cache layers: HTTP > Redis > Doctrine query cache
- Never cache user-specific data in shared cache
