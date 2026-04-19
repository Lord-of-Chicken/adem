import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "error"];
    
    connect() {
        // Ajouter des écouteurs pour la validation en temps réel
        this.inputs.forEach(input => {
            input.addEventListener('input', this.validateField.bind(this));
            input.addEventListener('blur', this.validateField.bind(this));
        });
        
        // Masque pour le téléphone
        this.setupPhoneMask();
    }
    
    validateField(event) {
        const input = event.target;
        const value = input.value.trim();
        const errorElement = input.parentElement.querySelector('.form__error') || 
                           this.createErrorElement(input);
        
        let isValid = true;
        let errorMessage = '';
        
        // Validation selon le type de champ
        switch(input.type) {
            case 'email':
                if (!value) {
                    isValid = false;
                    errorMessage = "L'email est obligatoire";
                } else if (!this.isValidEmail(value)) {
                    isValid = false;
                    errorMessage = "Format d'email invalide";
                }
                break;
                
            case 'tel':
                if (value && !this.isValidPhone(value)) {
                    isValid = false;
                    errorMessage = "Format de téléphone invalide. Ex: 06 12 34 56 78";
                }
                break;
                
            case 'password':
                if (!value) {
                    isValid = false;
                    errorMessage = "Le mot de passe est obligatoire";
                } else if (value.length < 8) {
                    isValid = false;
                    errorMessage = "Au moins 8 caractères";
                }
                break;
        }
        
        // Validation pour les champs texte (nom, prénom)
        if (input.tagName === 'INPUT' && input.type === 'text') {
            if (!value) {
                isValid = false;
                errorMessage = "Ce champ est obligatoire";
            } else if (value.length < 2) {
                isValid = false;
                errorMessage = "Au moins 2 caractères";
            } else if (value.length > 50) {
                isValid = false;
                errorMessage = "Maximum 50 caractères";
            }
        }
        
        // Afficher/masquer l'erreur
        if (!isValid) {
            errorElement.textContent = errorMessage;
            errorElement.style.display = 'block';
            input.classList.add('form__input--error');
        } else {
            errorElement.style.display = 'none';
            input.classList.remove('form__input--error');
        }
    }
    
    setupPhoneMask() {
        const phoneInput = this.element.querySelector('input[type="tel"]');
        if (!phoneInput) return;
        
        phoneInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\s/g, '');
            
            // Limiter à 10 chiffres pour les numéros français
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Formater automatiquement
            if (value.length >= 6) {
                value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4');
            } else if (value.length >= 4) {
                value = value.replace(/(\d{2})(\d{2})/, '$1 $2');
            }
            
            e.target.value = value;
        });
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    isValidPhone(phone) {
        const phoneRegex = /^(0[1-9])(\d{2})(\d{2})(\d{2})(\d{2})$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }
    
    createErrorElement(input) {
        const error = document.createElement('div');
        error.className = 'form__error';
        error.style.cssText = `
            color: #b00020;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: none;
        `;
        input.parentElement.appendChild(error);
        return error;
    }
}
