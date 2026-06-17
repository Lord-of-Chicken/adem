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

{# GOOD — compute in controller/Service/DTO, pass to template #}
{{ cart.totalFormatted }}

{# Use named blocks for extensibility #}
{% block content %}{% endblock %}

{# Prefer includes/components over repeating markup #}
{% include 'components/_tier_card.html.twig' with { tier: tier } %}
```

## Stimulus — behavior only

```js
// assets/controllers/cart_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    open() { this.dialogTarget.showModal(); }
    close() { this.dialogTarget.close(); }
}
```

```twig
{# Use data-controller in Twig #}
<div data-controller="cart">
    <button data-action="cart#open">Voir le panier</button>
    <dialog data-cart-target="dialog">...</dialog>
</div>
```

## Turbo Frames — partial page updates

```twig
{# Wrap independently updatable sections #}
<turbo-frame id="cart-summary">
    {% for line in cart.lines %}
        {{ include('cart/_line.html.twig') }}
    {% endfor %}
</turbo-frame>

{# Controller responds to Turbo Frame requests automatically #}
{# Return the full page — Turbo extracts the matching frame #}
```

## Turbo Streams — multi-target DOM updates

```php
// In controller (after add-to-cart, form submit, etc.)
return $this->renderBlock('cart/index.html.twig', 'cart_summary', [
    'cart' => $cart,
]);
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
