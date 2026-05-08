/**
 * Met à jour la quantité d'une ligne du panier via AJAX
 */
window.updateCartQty = function(lineId, delta, min, max) {
    const numberSpan = document.getElementById(`number-${lineId}`);
    const displaySpan = document.getElementById(`display-qty-${lineId}`);
    const labelSpan = document.getElementById(`label-${lineId}`);
    const btnMinus = document.getElementById(`btn-minus-${lineId}`);
    const btnPlus = document.getElementById(`btn-plus-${lineId}`);

    let currentQty = parseInt(numberSpan.textContent);
    let newQty = currentQty + delta;

    if (newQty < min || newQty > max) return;

    // Interface réactive (optimiste)
    numberSpan.textContent = newQty;
    displaySpan.textContent = newQty;
    const singular = labelSpan.getAttribute('data-piece-singular') || 'pièce';
    const plural = labelSpan.getAttribute('data-piece-plural') || 'pièces';
    labelSpan.textContent = newQty > 1 ? plural : singular;
    btnMinus.disabled = (newQty <= min);
    btnPlus.disabled = (newQty >= max);

    const csrfToken = document.getElementById('cart-csrf-token').value;
    const formData = new FormData();
    formData.append('quantity', newQty);
    formData.append('_token', csrfToken);

    fetch(`/panier/ligne/${lineId}/quantite`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById(`line-total-${lineId}`).textContent = data.newLineTotal;
            document.getElementById('cart-total-display').textContent = data.newTotalHtml;
        } else {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        location.reload();
    });
}

// Initialisation des labels au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qty-label').forEach(label => {
        const singular = label.getAttribute('data-piece-singular') || 'pièce';
        const plural = label.getAttribute('data-piece-plural') || 'pièces';
        const qtyDisplay = label.previousElementSibling;
        if (qtyDisplay && qtyDisplay.classList.contains('qty-number')) {
            const qty = parseInt(qtyDisplay.textContent);
            label.textContent = qty > 1 ? plural : singular;
        }
    });
});
