# Frontend Skill

## Stack

- **Twig** (server-rendered templates)
- **TailwindCSS** (install via Asset Mapper: `composer require symfonycasts/tailwind-bundle`)
- **Asset Mapper** (`symfony/asset-mapper`) — no Webpack Encore, no npm build step
- **Stimulus** (`symfony/stimulus-bundle`) — installed
- **Turbo** (`symfony/ux-turbo`) — installed

## Package manager

**Always use `bun` instead of `npm` or `yarn`** — faster installs, better DX.

```bash
# Install dependencies
bun install

# Add a package
bun add some-package

# Run scripts
bun run build
```

## Asset Mapper — how it works

```php
// importmap.php manages JS dependencies (like npm but without a build step)
// Add packages: symfony console importmap:require @hotwired/turbo
// Assets served directly from assets/ — no compilation needed

// Reference assets in Twig
{{ asset('styles/app.css') }}
{{ importmap('app') }}
```

## Twig rules

```twig
{# No logic in templates — only display #}
{# BAD #}
{% set total = items|length * price %}

{# GOOD — compute in controller/DTO, pass to template #}
{{ report.totalCount }}

{# Use named blocks for extensibility #}
{% block content %}{% endblock %}

{# Prefer includes/components over repeating markup #}
{% include 'components/_animal_card.html.twig' with { report: report } %}
```

## Stimulus — behavior only

```js
// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    open() { this.dialogTarget.showModal(); }
    close() { this.dialogTarget.close(); }
}
```

```twig
{# Use data-controller in Twig #}
<div data-controller="modal">
    <button data-action="modal#open">Open</button>
    <dialog data-modal-target="dialog">...</dialog>
</div>
```

## Turbo Frames — partial page updates

```twig
{# Wrap independently updatable sections #}
<turbo-frame id="animal-report-list">
    {% for report in reports %}
        {{ include('animal_report/_card.html.twig') }}
    {% endfor %}
</turbo-frame>

{# Controller responds to Turbo Frame requests automatically #}
{# Return the full page — Turbo extracts the matching frame #}
```

## Turbo Streams — real-time DOM updates

```php
// In controller (after form submit, Messenger handler, etc.)
return $this->renderBlock('animal_report/index.html.twig', 'report_list', [
    'reports' => $reports,
]);

// Or via Mercure for push
$this->hub->publish(new Update(
    topics: ['/reports'],
    data: $this->renderView('streams/report_added.stream.html.twig', ['report' => $report]),
));
```

## Symfony UX — not yet installed, install when needed

```bash
# Twig Components (reusable, server-rendered)
composer require symfony/ux-twig-component

# Live Components (reactive, no JS framework needed)
composer require symfony/ux-live-component

# Autocomplete
composer require symfony/ux-autocomplete

# File upload with preview
composer require symfony/ux-dropzone
```

## Rules

- Server-rendered by default — avoid JS state when Turbo Frames suffice
- Accessible components: semantic HTML, ARIA attributes, keyboard navigation
- No inline styles — use TailwindCSS utility classes
- No jQuery — native JS or Stimulus only
- Reusable UI: extract Twig includes or Twig Components for repeated markup
- Mobile-first responsive design
