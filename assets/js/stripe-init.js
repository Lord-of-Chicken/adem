(() => {
    const initStripe = () => {
        const body = document.body;
        if (!body || body.dataset.stripeEnabled !== '1') {
            return;
        }

        const key = body.dataset.stripePublishableKey || '';
        if (typeof Stripe !== 'undefined' && key !== '') {
            try {
                window.stripe = Stripe(key);
            } catch (error) {
                setTimeout(initStripe, 100);
            }
        } else {
            setTimeout(initStripe, 100);
        }
    };

    document.addEventListener('DOMContentLoaded', initStripe);
})();
