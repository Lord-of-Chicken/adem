import { Controller } from '@hotwired/stimulus';

/*
 * Drives the +/- quantity buttons of a single cart line, replacing the inline
 * `onclick="updateCartQty(...)"` handlers.
 *
 * The AJAX quantity-update logic lives in assets/js/cart.js (exposed as
 * window.updateCartQty), kept as the single source of truth.
 *
 * Values:
 *  - line-id: the cart line identifier
 *  - min-qty / max-qty: quantity bounds
 */
export default class extends Controller {
    static values = {
        lineId: String,
        minQty: { type: Number, default: 1 },
        maxQty: { type: Number, default: 500 },
    };

    decrement() {
        window.updateCartQty(this.lineIdValue, -1, this.minQtyValue, this.maxQtyValue);
    }

    increment() {
        window.updateCartQty(this.lineIdValue, 1, this.minQtyValue, this.maxQtyValue);
    }
}
