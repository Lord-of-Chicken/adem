(() => {
    const initStripe = () => {
        const body = document.body;
        if (!body || body.dataset.stripeEnabled !== '1') return;
        if (window.stripe) return;

        const key = body.dataset.stripePublishableKey || '';
        if (typeof Stripe !== 'undefined' && key !== '') {
            try {
                window.stripe = Stripe(key);
            } catch {
                // Stripe SDK failed to initialize — will retry on next turbo:load
            }
        }
    };

    document.addEventListener('DOMContentLoaded', initStripe);
    document.addEventListener('turbo:load', initStripe);
})();
