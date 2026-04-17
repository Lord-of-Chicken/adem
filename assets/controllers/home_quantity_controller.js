import { Controller } from '@hotwired/stimulus';
// On importe les fonctions de ton contrôleur de protection
import { generateCsrfHeaders, generateCsrfToken } from './csrf-protection-controller'; 

export default class extends Controller {
    static targets = ['number'];

    connect() {
        const minusBtn = this.element.querySelector('.qty-btn--minus');
        const plusBtn = this.element.querySelector('.qty-btn--plus');
        
        // On récupère les IDs et limites
        this.tierId = minusBtn.dataset.tierId;
        this.minQty = parseInt(minusBtn.dataset.minQty) || 1;
        this.maxQty = parseInt(plusBtn.dataset.maxQty) || 100;
        
        this.updateButtonStates();
    }

    plus(event) {
        event.preventDefault();
        const currentQty = this.getCurrentQuantity();
        if (currentQty < this.maxQty) {
            this.setQuantity(currentQty + 1);
        }
    }

    minus(event) {
        event.preventDefault();
        const currentQty = this.getCurrentQuantity();
        if (currentQty > this.minQty) {
            this.setQuantity(currentQty - 1);
        }
    }

    getCurrentQuantity() {
        // Utilisation de this.element pour limiter la recherche à la ligne actuelle
        const hiddenInput = document.getElementById(`hidden-qty-${this.tierId}`);
        return parseInt(hiddenInput.value) || this.minQty;
    }

    async setQuantity(quantity) {
        // 1. Mise à jour UI immédiate
        const hiddenInput = document.getElementById(`hidden-qty-${this.tierId}`);
        if (hiddenInput) hiddenInput.value = quantity;

        const numberDisplay = this.element.querySelector(`[data-tier-id="${this.tierId}"].qty-number`);
        if (numberDisplay) {
            numberDisplay.textContent = quantity;
            const labelDisplay = numberDisplay.nextElementSibling;
            if (labelDisplay && labelDisplay.classList.contains('qty-label')) {
                labelDisplay.textContent = `pièce${quantity > 1 ? 's' : ''}`;
            }
        }

        this.updateButtonStates();

        // 2. Préparation du CSRF (indispensable pour ton projet)
        // On s'assure que le token est généré dans le cookie/champ avant l'envoi
        generateCsrfToken(this.element); 
        const csrfHeaders = generateCsrfHeaders(this.element);

        // 3. Envoi au serveur
        try {
            const response = await fetch(`/cart/quantity/${this.tierId}`, {
                method: 'POST',
                headers: {
                    ...csrfHeaders, // On injecte les headers CSRF dynamiques
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ 'quantity': quantity })
            });

            if (!response.ok) throw new Error('Erreur lors de la mise à jour');

            // Si tu as un élément "Total du panier" ailleurs, 
            // il faudra probablement recharger pour rafraîchir le calcul global
            // window.location.reload();

        } catch (error) {
            console.error("Erreur:", error);
            // Optionnel : remettre l'ancienne valeur en cas d'échec
        }
    }

    updateButtonStates() {
        const currentQty = this.getCurrentQuantity();
        const minusBtn = this.element.querySelector('.qty-btn--minus');
        const plusBtn = this.element.querySelector('.qty-btn--plus');

        if (minusBtn) minusBtn.disabled = (currentQty <= this.minQty);
        if (plusBtn) plusBtn.disabled = (currentQty >= this.maxQty);
    }
}