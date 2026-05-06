import { showToast } from './toast.js';

export function updateQty(id, delta, min, max) {
    const input = document.getElementById('qty-' + id);
    const display = document.getElementById('num-' + id);
    const label = document.getElementById('label-' + id);
    const btnMinus = document.getElementById('minus-' + id);
    const btnPlus = document.getElementById('plus-' + id);

    if (!input || !display) return;

    let val = parseInt(input.value) + delta;

    if (val >= min && val <= max) {
        input.value = val;
        display.textContent = val;

        if (label) {
            const singular = label.getAttribute('data-piece-singular') || 'pièce';
            const plural = label.getAttribute('data-piece-plural') || 'pièces';
            label.textContent = val > 1 ? plural : singular;
        }

        if (btnMinus) btnMinus.disabled = (val <= min);
        if (btnPlus) btnPlus.disabled = (val >= max);
    }
}

function getUiTranslations() {
    const script = document.getElementById('cart-translations');
    if (!script) return {};
    try {
        return JSON.parse(script.textContent || '{}');
    } catch {
        return {};
    }
}

function updateCartBadges(cartCount) {
    const count = parseInt(cartCount, 10);
    if (Number.isNaN(count)) return;

    document.querySelectorAll('.site-header__cart').forEach((link) => {
        let badge = link.querySelector('.site-header__cart-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'site-header__cart-badge';
                link.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = 'inline-block';
        } else if (badge) {
            badge.remove();
        }
    });
}

function showCartSuccessPopup() {
    const cartUrl = document.getElementById('global-cart-url')?.value || '/panier';
    const translations = getUiTranslations();
    let modal = document.getElementById('cart-success-modal');

    if (!modal) {
        modal = document.createElement('dialog');
        modal.id = 'cart-success-modal';
        modal.className = 'cart-success-modal';
        modal.innerHTML = `
            <div class="cart-success-modal__dialog" aria-labelledby="cart-success-title">
                <h3 id="cart-success-title" class="cart-success-modal__title"></h3>
                <p class="cart-success-modal__text"></p>
                <div class="cart-success-modal__actions">
                    <button type="button" class="btn btn--secondary" data-cart-modal-action="continue"></button>
                    <button type="button" class="btn btn--primary" data-cart-modal-action="cart"></button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', (event) => {
            const action = event.target.getAttribute('data-cart-modal-action');
            if (action === 'continue' || event.target === modal) modal.close();
            if (action === 'cart') window.location.href = cartUrl;
        });
    }

    modal.querySelector('#cart-success-title').textContent = translations.title || 'Added to cart!';
    modal.querySelector('.cart-success-modal__text').textContent = translations.text || 'Your item has been added.';
    modal.querySelector('[data-cart-modal-action="continue"]').textContent = translations.continue || 'Continue';
    modal.querySelector('[data-cart-modal-action="cart"]').textContent = translations.cart || 'View cart';
    modal.showModal();
}

export function addToCart(tierId) {
    const csrfToken = document.getElementById('global-csrf-token').value;
    const translations = getUiTranslations();
    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('tier_id', tierId);

    if (tierId === 'free_donation') {
        const amount = document.getElementById('free-amount').value;
        if (!amount || parseFloat(amount) <= 0) {
            showToast(translations.invalid_amount || 'Please enter a valid amount.', 'error');
            return;
        }
        formData.append('amount', amount);
        formData.append('quantity', 1);
    } else {
        const qty = document.getElementById('qty-' + tierId).value;
        formData.append('quantity', qty);
    }

    const donor = document.getElementById('donor-' + tierId);
    if (donor) formData.append('donor_name', donor.value);

    fetch('/panier/ajouter', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateCartBadges(data.cartCount);
            showCartSuccessPopup();
            if (tierId === 'free_donation') document.getElementById('free-amount').value = '';
        } else {
            showToast(data.message || (translations.add_error || 'Error adding to cart'), 'error');
        }
    })
    .catch(() => showToast(translations.technical_error || 'Technical error.', 'error'));
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qty-number').forEach(el => {
        const id = el.id.replace('num-', '');
        const val = parseInt(el.textContent);
        const btnMinus = document.getElementById('minus-' + id);
        const label = document.getElementById('label-' + id);

        if (btnMinus) {
            const minQty = parseInt(document.getElementById('qty-' + id)?.value || 1);
            btnMinus.disabled = (val <= minQty);
        }

        if (label) {
            const singular = label.getAttribute('data-piece-singular') || 'pièce';
            const plural = label.getAttribute('data-piece-plural') || 'pièces';
            label.textContent = val > 1 ? plural : singular;
        }
    });
});
