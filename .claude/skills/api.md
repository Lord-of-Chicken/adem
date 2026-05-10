# API Skill

## Status: API Platform not yet installed

This project uses Symfony Forms + Twig for the UI. If a REST/JSON API is needed:

```bash
composer require api-platform/symfony
```

## When to build an API

- Mobile app needs data
- Third-party integrations (partner shelters, municipalities)
- Public data access (open data for lost animals)

## API Platform approach (when installed)

```php
// State providers/processors over legacy DataProviders
#[ApiResource(
    operations: [
        new GetCollection(provider: AnimalReportCollectionProvider::class),
        new Get(provider: AnimalReportItemProvider::class),
        new Post(processor: CreateAnimalReportProcessor::class),
    ]
)]
final class AnimalReportResource {
    public ?Uuid $id = null;
    public string $animalName = '';
    public string $species = '';
    public string $status = '';
    public LocationResource $location;
}
```

## Without API Platform (manual JSON endpoints)

```php
#[Route('/api/reports', name: 'api_reports_list', methods: ['GET'])]
public function list(QueryBusInterface $bus): JsonResponse {
    $reports = $bus->ask(new GetPublicAnimalReports(page: 1));
    return $this->json($reports, context: ['groups' => ['report:read']]);
}
```

## Rules

- Never expose Doctrine entities directly in responses — use output DTOs or API resources
- Version APIs from day one: `/api/v1/...`
- Validate all inputs via Symfony Validator on DTOs
- Pagination mandatory on all collection endpoints
- OpenAPI spec must be generated (API Platform does this automatically)
- Authentication via Symfony Security (JWT with `lexik/jwt-authentication-bundle` if needed)

## Response format conventions

```json
// Collection
{
    "data": [...],
    "meta": { "total": 42, "page": 1, "perPage": 20 }
}

// Error
{
    "type": "https://tools.ietf.org/html/rfc9110#section-15.5.5",
    "title": "Not Found",
    "status": 404,
    "detail": "Animal report not found"
}
```

## Security for API endpoints

- Public read endpoints: no auth required
- Write endpoints: `#[IsGranted('ROLE_USER')]`
- Admin endpoints: `#[IsGranted('ROLE_ADMIN')]`
- Rate limiting on public endpoints
- CORS configured for known origins only
