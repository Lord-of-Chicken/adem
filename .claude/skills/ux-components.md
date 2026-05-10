# Symfony UX Components Skill

## Priority order for UI interactivity

1. **Twig Components** — reusable server-rendered components (like React components but PHP)
2. **Live Components** — stateful components with reactivity (no JS state management)
3. **Stimulus controllers** — behavior only (modals, toggles, clipboard, etc.)
4. **Turbo Frames** — partial page updates on navigation/form submit
5. **Turbo Streams** — real-time DOM updates (after actions or via Mercure)

## Currently installed in this project

- `symfony/ux-turbo` — Turbo Frames + Streams
- `symfony/stimulus-bundle` — Stimulus controllers

## Turbo Frame patterns

```twig
{# Partial page update — only this frame reloads on navigation #}
<turbo-frame id="search-results" src="{{ path('reports_search') }}" loading="lazy">
    <p>Loading...</p>
</turbo-frame>

{# Form inside a frame — response replaces only this frame #}
<turbo-frame id="report-form">
    {{ form_start(form) }}
    ...
    {{ form_end(form) }}
</turbo-frame>
```

## Turbo Stream actions

```twig
{# streams/report_created.stream.html.twig #}
<turbo-stream action="prepend" target="report-list">
    <template>
        {{ include('animal_report/_card.html.twig', { report: report }) }}
    </template>
</turbo-stream>

{# Available actions: append, prepend, replace, update, remove #}
```

## Stimulus controller example

```js
// assets/controllers/photo_preview_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview'];

    preview() {
        const file = this.inputTarget.files[0];
        if (!file) return;
        this.previewTarget.src = URL.createObjectURL(file);
    }
}
```

```twig
<div data-controller="photo-preview">
    <input type="file" data-photo-preview-target="input"
           data-action="change->photo-preview#preview">
    <img data-photo-preview-target="preview" src="" alt="Preview">
</div>
```

## Install additional UX packages when needed

```bash
# Reusable PHP components (like React components)
composer require symfony/ux-twig-component

# Reactive PHP components (like Alpine.js but server-side)
composer require symfony/ux-live-component

# Autocomplete on select fields
composer require symfony/ux-autocomplete

# File upload with drag & drop
composer require symfony/ux-dropzone

# Charts
composer require symfony/ux-chartjs

# Real-time push (requires Mercure hub)
composer require symfony/ux-turbo mercure
```

## Twig Component (once installed)

```php
// src/AnimalReport/UI/Twig/AnimalCardComponent.php
#[AsTwigComponent]
final class AnimalCardComponent {
    public AnimalReport $report;

    public function getStatusLabel(): string {
        return match($this->report->status) {
            ReportStatus::LOST  => 'Perdu',
            ReportStatus::FOUND => 'Trouvé',
        };
    }
}
```

```twig
{# templates/components/AnimalCard.html.twig #}
<div class="animal-card">
    <h3>{{ this.report.name }}</h3>
    <span class="badge">{{ this.statusLabel }}</span>
</div>

{# Usage #}
<twig:AnimalCard :report="report" />
```

## Live Component (once installed)

```php
#[AsLiveComponent]
final class SearchReportsComponent {
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $city = '';

    public function getReports(): array {
        return $this->repository->searchByQueryAndCity($this->query, $this->city);
    }
}
```

## Rules

- Server owns state — no duplicated state in JS
- Minimal JS — if it can be done server-side with Turbo/Live Components, do it
- Stimulus only for pure behavior (no data fetching, no state)
- Turbo Frames for navigation-level partial updates
- Turbo Streams for post-action DOM mutations
- Live Components for reactive forms and search (once installed)
