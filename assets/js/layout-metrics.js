(() => {
    const updateLayoutMetrics = () => {
        const banner = document.querySelector('.construction-banner');
        const header = document.querySelector('.site-header');
        const rootStyle = document.documentElement.style;

        rootStyle.setProperty('--banner-h', `${banner ? banner.offsetHeight : 0}px`);
        rootStyle.setProperty('--header-h', `${header ? header.offsetHeight : 0}px`);
    };

    updateLayoutMetrics();

    window.addEventListener('resize', updateLayoutMetrics);
    document.addEventListener('turbo:load', updateLayoutMetrics);

    if (document.fonts?.ready) {
        document.fonts.ready.then(updateLayoutMetrics);
    }
})();
