(() => {
    let modal = null;
    let pendingForm = null;

    function ensureModal() {
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'global-confirm-modal';
        modal.className = 'cart-success-modal';
        modal.innerHTML = `
            <div class="cart-success-modal__backdrop"></div>
            <div class="cart-success-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="global-confirm-title">
                <h3 id="global-confirm-title" class="cart-success-modal__title">Confirmation</h3>
                <p class="cart-success-modal__text" id="global-confirm-text"></p>
                <div class="cart-success-modal__actions">
                    <button type="button" class="btn btn--secondary" data-confirm-modal-action="cancel">Annuler</button>
                    <button type="button" class="btn btn--primary" data-confirm-modal-action="confirm">Confirmer</button>
                </div>
            </div>
        `;

        modal.addEventListener('click', (event) => {
            const action = event.target.getAttribute('data-confirm-modal-action');

            if (action === 'cancel' || event.target.classList.contains('cart-success-modal__backdrop')) {
                pendingForm = null;
                modal.classList.remove('cart-success-modal--open');
                return;
            }

            if (action === 'confirm' && pendingForm) {
                const formToSubmit = pendingForm;
                pendingForm = null;
                modal.classList.remove('cart-success-modal--open');
                formToSubmit.submit();
            }
        });

        document.body.appendChild(modal);
        return modal;
    }

    function openConfirmModal(form) {
        const currentModal = ensureModal();
        const message = form.getAttribute('data-confirm-message') || 'Confirmer cette action ?';
        const title = form.getAttribute('data-confirm-title') || 'Confirmation';
        const confirmLabel = form.getAttribute('data-confirm-confirm-label') || 'Confirmer';
        const cancelLabel = form.getAttribute('data-confirm-cancel-label') || 'Annuler';

        currentModal.querySelector('#global-confirm-title').textContent = title;
        currentModal.querySelector('#global-confirm-text').textContent = message;
        currentModal.querySelector('[data-confirm-modal-action="confirm"]').textContent = confirmLabel;
        currentModal.querySelector('[data-confirm-modal-action="cancel"]').textContent = cancelLabel;

        pendingForm = form;
        currentModal.classList.add('cart-success-modal--open');
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
