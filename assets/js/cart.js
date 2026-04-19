/**
 * Met à jour la quantité d'une ligne du panier via AJAX
 */
function updateCartQty(lineId, delta, min, max) {
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
    labelSpan.textContent = newQty > 1 ? 'pièces' : 'pièce';
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
