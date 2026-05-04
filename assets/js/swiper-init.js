(() => {
    const initSwiper = () => {
        if (typeof Swiper === 'undefined') {
            return;
        }

        const swiperElement = document.querySelector('.media-swiper');
        if (!swiperElement) {
            return;
        }

        new Swiper('.media-swiper', {
            effect: 'coverflow',
            grabCursor: true,
            centeredSlides: true,
            slidesPerView: 'auto',
            coverflowEffect: { rotate: 50, stretch: 0, depth: 100, modifier: 1, slideShadows: true },
            autoplay: { delay: 3000, disableOnInteraction: false },
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwiper);
    } else {
        initSwiper();
    }

    window.addEventListener('load', initSwiper);
})();
