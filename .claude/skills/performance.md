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
// BAD — N+1: one query per report to load photos
foreach ($reports as $report) {
    echo $report->getPhotos()->count(); // lazy load per iteration
}

// GOOD — single JOIN query
$reports = $this->createQueryBuilder('r')
    ->leftJoin('r.photos', 'p')
    ->addSelect('p')
    ->where('r.status = :status')
    ->setParameter('status', ReportStatus::PUBLISHED)
    ->getQuery()
    ->getResult();
```

## HTTP cache

```php
#[Route('/reports', name: 'reports_index')]
public function index(): Response {
    $response = $this->render('reports/index.html.twig', [...]);
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
public function getPublishedCount(): int {
    return $this->cache->get('reports.count', function (ItemInterface $item) {
        $item->expiresAfter(300);
        return $this->repository->countPublished();
    });
}
```

## Doctrine query cache

```php
$result = $this->createQueryBuilder('r')
    ->where('r.status = :status')
    ->setParameter('status', ReportStatus::PUBLISHED)
    ->getQuery()
    ->enableResultCache(300, 'reports_published')
    ->getResult();
```

## MySQL indexes

```sql
-- All pages
CREATE INDEX idx_report_status         ON animal_report (status);
CREATE INDEX idx_report_created_at     ON animal_report (created_at DESC);

-- Search filters
CREATE INDEX idx_report_species_status ON animal_report (species, status);
CREATE INDEX idx_report_city           ON animal_report (city(50));

-- Full-text
ALTER TABLE animal_report
    ADD FULLTEXT INDEX idx_report_fts (description, animal_name);
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
