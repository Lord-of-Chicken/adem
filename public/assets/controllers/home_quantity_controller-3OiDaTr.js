import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['number'];

    connect() {
        // Récupérer les valeurs depuis les attributs data
        this.tierId = this.element.querySelector('.qty-btn--minus').dataset.tierId;
        this.minQty = parseInt(this.element.querySelector('.qty-btn--minus').dataset.minQty);
        this.maxQty = parseInt(this.element.querySelector('.qty-btn--plus').dataset.maxQty);
        
        // Initialiser les états des boutons
        this.updateButtonStates();
    }

    plus(event) {
        event.preventDefault();
        const currentQty = this.getCurrentQuantity();
        const newQty = Math.min(currentQty + 1, this.maxQty);
        this.setQuantity(newQty);
    }

    minus(event) {
        event.preventDefault();
        const currentQty = this.getCurrentQuantity();
        const newQty = Math.max(currentQty - 1, this.minQty);
        this.setQuantity(newQty);
    }

    getCurrentQuantity() {
        const hiddenInput = document.getElementById(`hidden-qty-${this.tierId}`);
        return parseInt(hiddenInput.value) || this.minQty;
    }

    setQuantity(quantity) {
        // Mettre à jour le champ caché
        const hiddenInput = document.getElementById(`hidden-qty-${this.tierId}`);
        hiddenInput.value = quantity;

        // Mettre à jour l'affichage
        const numberDisplay = this.element.querySelector(`[data-tier-id="${this.tierId}"].qty-number`);
        if (numberDisplay) {
            numberDisplay.textContent = quantity;
        }

        // Mettre à jour le label "pièce(s)"
        const labelDisplay = numberDisplay?.nextElementSibling;
        if (labelDisplay) {
            labelDisplay.textContent = `pièce${quantity > 1 ? 's' : ''}`;
        }

        // Mettre à jour l'état des boutons
        this.updateButtonStates();
    }

    updateButtonStates() {
        const currentQty = this.getCurrentQuantity();
        const minusBtn = this.element.querySelector('.qty-btn--minus');
        const plusBtn = this.element.querySelector('.qty-btn--plus');

        // Désactiver le bouton "-" si on est à la quantité minimale
        if (minusBtn) {
            minusBtn.disabled = currentQty <= this.minQty;
        }

        // Désactiver le bouton "+" si on est à la quantité maximale
        if (plusBtn) {
            plusBtn.disabled = currentQty >= this.maxQty;
        }
    }
}
