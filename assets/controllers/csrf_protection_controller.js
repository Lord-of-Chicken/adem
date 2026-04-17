import { Controller } from '@hotwired/stimulus';
import { generateCsrfHeaders } from './csrf-protection-controller'; // Ajustez le chemin vers votre fichier CSRF

export default class extends Controller {
    connect() {
        const minusBtn = this.element.querySelector('.qty-btn--minus');
        const plusBtn = this.element.querySelector('.qty-btn--plus');
        
        this.tierId = minusBtn.dataset.tierId;
        this.minQty = parseInt(minusBtn.dataset.minQty) || 1;
        this.maxQty = parseInt(plusBtn.dataset.maxQty) || 500;
        
        this.updateButtonStates();
    }

    plus(event) {
        event.preventDefault();
        this.changeQuantity(1);
    }

    minus(event) {
        event.preventDefault();
        this.changeQuantity(-1);
    }

    changeQuantity(delta) {
        const currentQty = this.getCurrentQuantity();
        const newQty = currentQty + delta;

        if (newQty >= this.minQty && newQty <= this.maxQty) {
            this.setQuantity(newQty);
        }
    }

    getCurrentQuantity() {
        return parseInt(document.getElementById(`hidden-qty-${this.tierId}`).value) || 1;
    }

    async setQuantity(quantity) {
        // 1. Mise à jour visuelle (Optimiste)
        const hiddenInput = document.getElementById(`hidden-qty-${this.tierId}`);
        hiddenInput.value = quantity;

        const numberDisplay = this.element.querySelector(`[data-tier-id="${this.tierId}"].qty-number`);
        if (numberDisplay) {
            numberDisplay.textContent = quantity;
            const label = numberDisplay.nextElementSibling;
            if (label) label.textContent = `pièce${quantity > 1 ? 's' : ''}`;
        }

        this.updateButtonStates();

        // 2. Gestion du CSRF pour Symfony
        // On récupère les headers CSRF dynamiquement depuis le formulaire "Retirer" 
        // ou tout autre champ CSRF présent dans la ligne
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        };

        // On cherche le champ CSRF dans la ligne pour générer les headers
        const csrfHeaders = generateCsrfHeaders(this.element);
        Object.assign(headers, csrfHeaders);

        // 3. Envoi au serveur
        try {
            await fetch(`/cart/quantity/${this.tierId}`, {
                method: 'POST',
                headers: headers,
                body: new URLSearchParams({ 'quantity': quantity })
            });
            
            // Note : Si vous voulez mettre à jour le prix total global, 
            // vous devrez peut-être rafraîchir la page ou renvoyer le nouveau total en JSON.
        } catch (e) {
            console.error("Erreur CSRF ou réseau", e);
        }
    }

    updateButtonStates() {
        const currentQty = this.getCurrentQuantity();
        this.element.querySelector('.qty-btn--minus').disabled = currentQty <= this.minQty;
        this.element.querySelector('.qty-btn--plus').disabled = currentQty >= this.maxQty;
    }
}