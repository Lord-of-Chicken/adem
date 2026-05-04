(() => {
    const updateLayoutMetrics = () => {
        const banner = document.querySelector('.construction-banner');
        const header = document.querySelector('.site-header');
        const rootStyle = document.documentElement.style;

        const bannerHeight = banner ? banner.offsetHeight : 0;
        const headerHeight = header ? header.offsetHeight : 0;

        rootStyle.setProperty('--banner-h', `${bannerHeight}px`);
        rootStyle.setProperty('--header-h', `${headerHeight}px`);
    };

    updateLayoutMetrics();

    document.addEventListener('DOMContentLoaded', updateLayoutMetrics);
    window.addEventListener('load', updateLayoutMetrics);
    window.addEventListener('resize', updateLayoutMetrics);

    window.setTimeout(updateLayoutMetrics, 0);
    window.setTimeout(updateLayoutMetrics, 200);
    window.setTimeout(updateLayoutMetrics, 600);

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(updateLayoutMetrics);
    }
})();
