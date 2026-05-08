(() => {
    let swiperInstance = null;

    const cleanup = () => {
        if (swiperInstance) {
            swiperInstance.destroy(true, true);
            swiperInstance = null;
        }
        document.querySelector('.lightbox')?.remove();
    };

    const initSwiper = () => {
        cleanup();

        if (typeof Swiper === 'undefined') return;

        const swiperElement = document.querySelector('.media-swiper');
        if (!swiperElement) return;

        swiperInstance = new Swiper('.media-swiper', {
            effect: 'coverflow',
            grabCursor: true,
            centeredSlides: true,
            loop: true,
            speed: 500,
            slidesPerView: 'auto',
            coverflowEffect: { rotate: 30, stretch: 10, depth: 150, modifier: 1, slideShadows: true },
            autoplay: { delay: 4000, disableOnInteraction: false, pauseOnMouseEnter: true },
            pagination: { el: '.swiper-pagination', clickable: true, dynamicBullets: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            keyboard: { enabled: true },
            a11y: { enabled: true },
        });

        initLightbox(swiperInstance);
    };

    const initLightbox = (swiper) => {
        const swiperEl = document.querySelector('.media-swiper');
        if (!swiperEl) return;

        const images = [...swiperEl.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate) img')];
        if (!images.length) return;

        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <button class="lightbox__close" aria-label="Fermer">✕</button>
            <button class="lightbox__nav lightbox__nav--prev" aria-label="Précédent">&#8592;</button>
            <img class="lightbox__img" src="" alt="">
            <button class="lightbox__nav lightbox__nav--next" aria-label="Suivant">&#8594;</button>
            <span class="lightbox__counter"></span>
        `;
        document.body.appendChild(lightbox);

        const img = lightbox.querySelector('.lightbox__img');
        const counter = lightbox.querySelector('.lightbox__counter');
        let currentIndex = 0;

        const show = (index) => {
            currentIndex = (index + images.length) % images.length;
            img.src = images[currentIndex].src;
            img.alt = images[currentIndex].alt;
            counter.textContent = `${currentIndex + 1} / ${images.length}`;
            lightbox.classList.add('lightbox--open');
            document.body.classList.add('mobile-menu-open');
            swiper.autoplay.stop();
        };

        const hide = () => {
            lightbox.classList.remove('lightbox--open');
            document.body.classList.remove('mobile-menu-open');
            swiper.autoplay.start();
        };

        swiperEl.addEventListener('click', (e) => {
            const clickedImg = e.target.closest('.swiper-slide:not(.swiper-slide-duplicate) img');
            if (!clickedImg) return;
            const index = images.indexOf(clickedImg);
            if (index !== -1) show(index);
        });

        lightbox.querySelector('.lightbox__close').addEventListener('click', hide);
        lightbox.querySelector('.lightbox__nav--prev').addEventListener('click', () => show(currentIndex - 1));
        lightbox.querySelector('.lightbox__nav--next').addEventListener('click', () => show(currentIndex + 1));

        lightbox.addEventListener('click', (e) => { if (e.target === lightbox) hide(); });

        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('lightbox--open')) return;
            if (e.key === 'Escape') hide();
            if (e.key === 'ArrowLeft') show(currentIndex - 1);
            if (e.key === 'ArrowRight') show(currentIndex + 1);
        });
    };

    // Turbo navigation
    document.addEventListener('turbo:load', initSwiper);

    // Fallback sans Turbo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwiper);
    } else {
        initSwiper();
    }
})();
