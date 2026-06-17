import { Controller } from '@hotwired/stimulus';

/*
 * Navigates the browser when a <select> changes, replacing inline
 * `onchange="window.location.href=..."` handlers (a11y/CSP hygiene).
 *
 * Modes (data-nav-select-mode-value):
 *  - "value" (default): the selected option's value IS the target URL.
 *  - "query": navigate to `?<param>=<value>` using data-nav-select-param-value.
 */
export default class extends Controller {
    static values = {
        mode: { type: String, default: 'value' },
        param: { type: String, default: '' },
    };

    navigate(event) {
        const value = event.target.value;

        if (this.modeValue === 'query') {
            const param = this.paramValue || 'value';
            window.location.href = `?${encodeURIComponent(param)}=${encodeURIComponent(value)}`;
            return;
        }

        if (value) {
            window.location.href = value;
        }
    }
}
