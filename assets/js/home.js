function updateQty(id, delta, min, max) {
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

function addToCart(tierId) {
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
            const badge = document.querySelector('.site-header__cart-badge');
            if (badge) {
                badge.textContent = data.cartCount;
                badge.style.display = 'inline-block';
            }
            alert('Ajouté au panier ! 🌸');
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
