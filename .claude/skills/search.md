# Search Skill

## Context

Core feature: search lost/found animals by city, species, date, keywords.

## CQRS Query object

```php
final readonly class SearchAnimalReports {
    public int $offset;

    public function __construct(
        public ?string   $keywords = null,
        public ?string   $city     = null,
        public ?Species  $species  = null,
        public ?ReportType $type   = null,
        public ?\DateTimeImmutable $since = null,
        public int       $page     = 1,
        public int       $perPage  = 20,
    ) {
        $this->offset = ($page - 1) * $perPage;
    }
}

final readonly class SearchAnimalReportsResult {
    public function __construct(
        /** @var AnimalReportSummaryDTO[] */
        public array $items,
        public int   $total,
        public int   $page,
        public int   $perPage,
    ) {}

    public function hasMore(): bool {
        return ($this->page * $this->perPage) < $this->total;
    }
}
```

## Repository method (MySQL full-text)

```php
public function search(SearchAnimalReports $query): SearchAnimalReportsResult {
    $qb = $this->createQueryBuilder('r')
        ->where('r.status = :status')
        ->setParameter('status', ReportStatus::PUBLISHED);

    if ($query->species !== null) {
        $qb->andWhere('r.species = :species')
           ->setParameter('species', $query->species);
    }

    if ($query->city !== null) {
        $qb->andWhere('r.location.city LIKE :city')
           ->setParameter('city', '%' . $query->city . '%');
    }

    if ($query->keywords !== null) {
        // MySQL FULLTEXT search (requires FULLTEXT index on description)
        $qb->andWhere('MATCH(r.description) AGAINST (:kw IN BOOLEAN MODE)')
           ->setParameter('kw', $query->keywords . '*');
    }

    if ($query->since !== null) {
        $qb->andWhere('r.createdAt >= :since')
           ->setParameter('since', $query->since);
    }

    $total = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

    $items = $qb
        ->orderBy('r.createdAt', 'DESC')
        ->setMaxResults($query->perPage)
        ->setFirstResult($query->offset)
        ->getQuery()
        ->getResult();

    return new SearchAnimalReportsResult($items, (int) $total, $query->page, $query->perPage);
}
```

## MySQL FULLTEXT index (migration)

```sql
ALTER TABLE animal_report
    ADD FULLTEXT INDEX idx_report_fts (description, animal_name);

CREATE INDEX idx_report_status        ON animal_report (status);
CREATE INDEX idx_report_species_status ON animal_report (species, status);
CREATE INDEX idx_report_city          ON animal_report (city(50));
CREATE INDEX idx_report_created_at    ON animal_report (created_at DESC);
```

## Live search with Turbo Frames + Stimulus debounce

```twig
<turbo-frame id="search-results">
    <form data-controller="search-form"
          data-action="input->search-form#submit"
          action="{{ path('reports_search') }}"
          method="GET">
        <input name="q" value="{{ query.keywords }}" placeholder="Race, couleur...">
        <select name="city">...</select>
        <select name="species">...</select>
    </form>

    {% for report in results.items %}
        {{ include('animal_report/_card.html.twig') }}
    {% endfor %}

    {% if results.hasMore() %}
        <a href="?page={{ query.page + 1 }}" data-turbo-frame="search-results">
            Voir plus
        </a>
    {% endif %}
</turbo-frame>
```

```js
// assets/controllers/search_form_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    submit() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.element.requestSubmit(), 300);
    }
}
```

## Cache search results (Redis)

```php
#[AsMessageHandler]
final class SearchAnimalReportsHandler {
    public function __invoke(SearchAnimalReports $query): SearchAnimalReportsResult {
        $cacheKey = 'search_' . md5(serialize($query));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(300); // 5 min
            return $this->repository->search($query);
        });
    }
}
```

## Rules

- Always paginate — never return unbounded result sets
- MySQL FULLTEXT index for keyword search on description/name
- Index all columns used in WHERE / ORDER BY
- Cache read-heavy search results in Redis (5 min TTL)
- Invalidate cache on new report published via Messenger event listener
