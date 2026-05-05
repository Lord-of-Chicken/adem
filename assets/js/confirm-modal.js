(() => {
    let modal = null;
    let pendingForm = null;

    function getUiTranslations() {
        const script = document.getElementById('cart-translations');
        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    function ensureModal() {
        if (modal) {
            return modal;
        }

        modal = document.createElement('dialog');
        modal.id = 'global-confirm-modal';
        modal.className = 'cart-success-modal';
        modal.innerHTML = `
            <div class="cart-success-modal__dialog" aria-labelledby="global-confirm-title">
                <h3 id="global-confirm-title" class="cart-success-modal__title"></h3>
                <p class="cart-success-modal__text" id="global-confirm-text"></p>
                <div class="cart-success-modal__actions">
                    <button type="button" class="btn btn--secondary" data-confirm-modal-action="cancel"></button>
                    <button type="button" class="btn btn--primary" data-confirm-modal-action="confirm"></button>
                </div>
            </div>
        `;

        modal.addEventListener('click', (event) => {
            const action = event.target.getAttribute('data-confirm-modal-action');

            if (action === 'cancel' || event.target === modal) {
                pendingForm = null;
                modal.close();
                return;
            }

            if (action === 'confirm' && pendingForm) {
                const formToSubmit = pendingForm;
                pendingForm = null;
                modal.close();
                formToSubmit.submit();
            }
        });

        document.body.appendChild(modal);
        return modal;
    }

    function openConfirmModal(form) {
        const currentModal = ensureModal();
        const translations = getUiTranslations();
        const message = form.getAttribute('data-confirm-message') || translations.confirm_message || 'Confirm this action?';
        const title = form.getAttribute('data-confirm-title') || translations.confirm_title || 'Confirmation';
        const confirmLabel = form.getAttribute('data-confirm-confirm-label') || translations.confirm || 'Confirm';
        const cancelLabel = form.getAttribute('data-confirm-cancel-label') || translations.cancel || 'Cancel';

        currentModal.querySelector('#global-confirm-title').textContent = title;
        currentModal.querySelector('#global-confirm-text').textContent = message;
        currentModal.querySelector('[data-confirm-modal-action="confirm"]').textContent = confirmLabel;
        currentModal.querySelector('[data-confirm-modal-action="cancel"]').textContent = cancelLabel;

        pendingForm = form;
        currentModal.showModal();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const forms = document.querySelectorAll('form[data-confirm-message]');

        forms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                openConfirmModal(form);
            });
        });
    });
})();
