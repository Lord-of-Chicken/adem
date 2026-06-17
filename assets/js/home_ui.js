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

    const fab = document.getElementById('cart-fab');
    const fabBadge = document.getElementById('cart-fab-badge');
    if (fab) {
        if (count > 0) {
            fab.classList.remove('cart-fab--hidden');
            if (fabBadge) fabBadge.textContent = count;
        } else {
            fab.classList.add('cart-fab--hidden');
        }
    }
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

export function addToCart(tierId, btn = null) {
    const translations = getUiTranslations();

    if (tierId === 'free_donation') {
        const amount = document.getElementById('free-amount').value;
        if (!amount || parseFloat(amount) <= 0) {
            showToast(translations.invalid_amount || 'Please enter a valid amount.', 'error');
            return;
        }
    }

    if (btn) btn.classList.add('btn--loading');

    const cartUrl = btn?.dataset?.cartUrl || translations.cart_add_url || '';
    const token = translations.csrf_cart_add || '';
    const formData = new FormData();
    formData.append('_token', token);
    formData.append('tier_id', tierId);

    if (tierId === 'free_donation') {
        formData.append('amount', document.getElementById('free-amount').value);
        formData.append('quantity', 1);
    } else {
        formData.append('quantity', document.getElementById('qty-' + tierId).value);
    }

    const donor = document.getElementById('donor-' + tierId);
    const donorConsent = document.getElementById('donor-consent-' + tierId);
    const donorName = donor ? donor.value.trim() : '';
    if (donorName !== '') {
        // GDPR: an explicit, non-pre-checked consent is required before the name
        // is transmitted (Stripe metadata, public display).
        if (!donorConsent || !donorConsent.checked) {
            if (btn) btn.classList.remove('btn--loading');
            showToast(translations.donor_consent_required || 'Please confirm consent to display your name.', 'error');
            return;
        }
        formData.append('donor_name', donorName);
        formData.append('donor_consent', '1');
    }

    fetch(cartUrl, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => {
            if (!res.ok) {
                console.error('Cart add failed with status:', res.status);
                throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                updateCartBadges(data.cartCount);
                showCartSuccessPopup();

                // Remettre à zéro les champs du produit
                if (tierId === 'free_donation') {
                    document.getElementById('free-amount').value = '';
                } else {
                    const qtyInput = document.getElementById('qty-' + tierId);
                    const qtyDisplay = document.getElementById('num-' + tierId);
                    const label = document.getElementById('label-' + tierId);
                    const btnMinus = document.getElementById('minus-' + tierId);
                    const btnPlus = document.getElementById('plus-' + tierId);
                    if (qtyInput) qtyInput.value = 1;
                    if (qtyDisplay) qtyDisplay.textContent = '1';
                    if (label) label.textContent = label.getAttribute('data-piece-singular') || 'pièce';
                    if (btnMinus) btnMinus.disabled = true;
                    if (btnPlus) btnPlus.disabled = false;
                }
                const donor = document.getElementById('donor-' + tierId);
                if (donor) donor.value = '';
                const donorConsent = document.getElementById('donor-consent-' + tierId);
                if (donorConsent) donorConsent.checked = false;
            } else {
                console.error('Cart add error:', data);
                showToast(data.message || (translations.add_error || 'Error adding to cart'), 'error');
            }
        })
        .catch(err => {
            console.error('Cart add technical error:', err);
            showToast(translations.technical_error || 'Technical error.', 'error');
        })
        .finally(() => { if (btn) btn.classList.remove('btn--loading'); });
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
