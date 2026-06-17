# API Skill

## Status: API Platform not yet installed

This project uses Symfony Forms + Twig for the UI. If a REST/JSON API is needed:

```bash
composer require api-platform/symfony
```

## When to build an API

- Mobile app needs data
- Third-party integrations (partner associations, municipality of Uccle)
- Public data access (open data on the project's progress / participations)

## API Platform approach (when installed)

```php
// State providers/processors over legacy DataProviders
#[ApiResource(
    operations: [
        new GetCollection(provider: ParticipationTierCollectionProvider::class),
        new Get(provider: ParticipationTierItemProvider::class),
        new Post(processor: CreateOrderProcessor::class),
    ]
)]
final class ParticipationTierResource {
    public ?int $id = null;
    public string $name = '';
    public string $group = '';      // standard | vip
    public int $priceCents = 0;
}
```

## Without API Platform (manual JSON endpoints)

```php
#[Route('/api/tiers', name: 'api_tiers_list', methods: ['GET'])]
public function list(ParticipationCatalog $catalog): JsonResponse {
    $tiers = $catalog->allTiers();
    return $this->json($tiers, context: ['groups' => ['tier:read']]);
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
    "detail": "Participation tier not found"
}
```

## Security for API endpoints

- Public read endpoints: no auth required
- Write endpoints: `#[IsGranted('ROLE_USER')]`
- Admin endpoints: `#[IsGranted('ROLE_ADMIN')]`
- Rate limiting on public endpoints
- CORS configured for known origins only
