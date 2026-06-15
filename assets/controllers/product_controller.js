import { Controller } from '@hotwired/stimulus';
import { updateQty, addToCart } from 'home';

/*
 * Drives a single product/tier card on the home page, replacing the inline
 * `onclick="updateQty(...)"` / `onclick="addToCart(...)"` handlers.
 *
 * The quantity/cart logic itself stays in home_ui.js (single source of truth) —
 * this controller is just the wiring layer.
 *
 * Values:
 *  - tier-id:  the catalog tier identifier (e.g. "begonia_unit", "free_donation")
 *  - min-qty / max-qty: quantity bounds (ignored for free_donation)
 */
export default class extends Controller {
    static values = {
        tierId: String,
        minQty: { type: Number, default: 1 },
        maxQty: { type: Number, default: 500 },
    };

    decrement() {
        updateQty(this.tierIdValue, -1, this.minQtyValue, this.maxQtyValue);
    }

    increment() {
        updateQty(this.tierIdValue, 1, this.minQtyValue, this.maxQtyValue);
    }

    add(event) {
        addToCart(this.tierIdValue, event.currentTarget);
    }
}
