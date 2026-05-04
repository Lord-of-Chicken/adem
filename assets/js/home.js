window.updateQty = function(id, delta, min, max) {
    const input = document.getElementById('qty-' + id);
    const display = document.getElementById('num-' + id);
    const label = document.getElementById('label-' + id);
    const btnMinus = document.getElementById('minus-' + id);
    const btnPlus = document.getElementById('plus-' + id);

    if (!input || !display) return;

    let val = parseInt(input.value) + delta;

    // Validation des limites
    if (val >= min && val <= max) {
        input.value = val;
        display.textContent = val;

        // Mise à jour du label "pièce(s)"
        if (label) {
            label.textContent = val > 1 ? 'pièces' : 'pièce';
        }

        // Gestion visuelle des boutons désactivés
        if (btnMinus) btnMinus.disabled = (val <= min);
        if (btnPlus) btnPlus.disabled = (val >= max);
    }
}

function updateCartBadges(cartCount) {
    const count = parseInt(cartCount, 10);
    if (Number.isNaN(count)) {
        return;
    }

    const cartLinks = document.querySelectorAll('.site-header__cart');
    cartLinks.forEach((link) => {
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
    let modal = document.getElementById('cart-success-modal');

    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'cart-success-modal';
        modal.className = 'cart-success-modal';
        modal.innerHTML = `
            <div class="cart-success-modal__backdrop"></div>
            <div class="cart-success-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cart-success-title">
                <h3 id="cart-success-title" class="cart-success-modal__title">Ajouté au panier ! 🌸</h3>
                <p class="cart-success-modal__text">Ton article a bien été ajouté.</p>
                <div class="cart-success-modal__actions">
                    <button type="button" class="btn btn--secondary" data-cart-modal-action="continue">Continuer</button>
                    <button type="button" class="btn btn--primary" data-cart-modal-action="cart">Voir le panier</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.addEventListener('click', (event) => {
            const action = event.target.getAttribute('data-cart-modal-action');
            if (action === 'continue' || event.target.classList.contains('cart-success-modal__backdrop')) {
                modal.classList.remove('cart-success-modal--open');
            }
            if (action === 'cart') {
                window.location.href = cartUrl;
            }
        });
    }

    modal.classList.add('cart-success-modal--open');
}

window.addToCart = function(tierId) {
    const csrfToken = document.getElementById('global-csrf-token').value;
    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('tier_id', tierId);

    if (tierId === 'free_donation') {
        const amount = document.getElementById('free-amount').value;
        if (!amount || parseFloat(amount) <= 0) {
            alert('Veuillez entrer un montant valide.');
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
            alert(data.message || 'Erreur lors de l\'ajout');
        }
    })
    .catch(err => alert('Erreur technique.'));
}

// Initialisation au chargement pour désactiver les boutons "minus" si qty = min
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qty-number').forEach(el => {
        const id = el.id.replace('num-', '');
        const val = parseInt(el.textContent);
        // On pourrait appeler une fonction de vérification ici si besoin
    });
});
