import './bootstrap';

const navRoot = document.querySelector('[data-nav-root]');

if (navRoot) {
    const navToggle = navRoot.querySelector('[data-nav-toggle]');
    const navPanel = navRoot.querySelector('[data-nav-panel]');
    const mobileNav = window.matchMedia('(max-width: 980px)');

    const setNavState = (open) => {
        navRoot.classList.toggle('is-nav-open', open);
        navToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const closeNav = () => setNavState(false);

    navToggle?.addEventListener('click', () => {
        setNavState(!navRoot.classList.contains('is-nav-open'));
    });

    navPanel?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileNav.matches) {
                closeNav();
            }
        });
    });

    const handleViewportChange = () => {
        if (!mobileNav.matches) {
            closeNav();
        }
    };

    if (typeof mobileNav.addEventListener === 'function') {
        mobileNav.addEventListener('change', handleViewportChange);
    } else if (typeof mobileNav.addListener === 'function') {
        mobileNav.addListener(handleViewportChange);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNav();
        }
    });
}

const revealElements = document.querySelectorAll('[data-reveal]');

if (revealElements.length > 0) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const showElement = (element) => {
        element.classList.add('is-visible');
    };

    const isNearViewport = (element) => {
        const rect = element.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        return rect.top <= viewportHeight * 1.15;
    };

    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
        revealElements.forEach(showElement);
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                showElement(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.01,
            rootMargin: '0px 0px 18% 0px',
        });

        revealElements.forEach((element) => {
            const delay = element.dataset.revealDelay;

            if (delay) {
                element.style.setProperty('--reveal-delay', `${delay}s`);
            }

            if (isNearViewport(element)) {
                showElement(element);
                return;
            }

            observer.observe(element);
        });
    }
}

const lightboxTriggers = document.querySelectorAll('[data-lightbox-trigger], .imported-gallery img, .wp-import-gallery img');

if (lightboxTriggers.length > 0) {
    const lightbox = document.createElement('div');
    lightbox.className = 'site-lightbox';
    lightbox.innerHTML = `
        <button class="site-lightbox__close" type="button" aria-label="Close image">&times;</button>
        <button class="site-lightbox__nav site-lightbox__nav--prev" type="button" aria-label="Previous image">&#8249;</button>
        <div class="site-lightbox__stage">
            <img class="site-lightbox__image" alt="">
        </div>
        <button class="site-lightbox__nav site-lightbox__nav--next" type="button" aria-label="Next image">&#8250;</button>
    `;

    document.body.appendChild(lightbox);

    const image = lightbox.querySelector('.site-lightbox__image');
    const closeButton = lightbox.querySelector('.site-lightbox__close');
    const prevButton = lightbox.querySelector('.site-lightbox__nav--prev');
    const nextButton = lightbox.querySelector('.site-lightbox__nav--next');

    let items = [];
    let currentIndex = 0;

    const syncOrientation = () => {
        image.classList.remove('is-portrait', 'is-landscape', 'is-square');

        const { naturalWidth, naturalHeight } = image;

        if (!naturalWidth || !naturalHeight) {
            return;
        }

        if (naturalHeight > naturalWidth * 1.05) {
            image.classList.add('is-portrait');
            return;
        }

        if (naturalWidth > naturalHeight * 1.05) {
            image.classList.add('is-landscape');
            return;
        }

        image.classList.add('is-square');
    };

    const getGroupItems = (trigger) => {
        const explicitGroup = trigger.dataset.lightboxGroup;

        if (explicitGroup) {
            return Array.from(document.querySelectorAll(`[data-lightbox-trigger][data-lightbox-group="${explicitGroup}"]`));
        }

        const importedGallery = trigger.closest('.imported-gallery, .wp-import-gallery');

        if (importedGallery) {
            return Array.from(importedGallery.querySelectorAll('img'));
        }

        return [trigger];
    };

    const getItemData = (node) => {
        if (node.dataset.lightboxSrc) {
            return {
                src: node.dataset.lightboxSrc,
                alt: node.dataset.lightboxAlt || '',
            };
        }

        if (node.tagName === 'IMG') {
            return {
                src: node.currentSrc || node.src,
                alt: node.alt || '',
            };
        }

        const imageNode = node.querySelector('img');

        return {
            src: imageNode?.currentSrc || imageNode?.src || node.getAttribute('href') || '',
            alt: imageNode?.alt || '',
        };
    };

    const render = () => {
        const item = items[currentIndex];

        if (!item) {
            return;
        }

        image.classList.remove('is-portrait', 'is-landscape', 'is-square');
        image.src = item.src;
        image.alt = item.alt || '';

        if (image.complete) {
            syncOrientation();
        }

        prevButton.hidden = items.length <= 1;
        nextButton.hidden = items.length <= 1;
    };

    const open = (trigger) => {
        const groupItems = getGroupItems(trigger)
            .map(getItemData)
            .filter((item) => item.src);

        if (groupItems.length === 0) {
            return;
        }

        items = groupItems;
        currentIndex = Math.max(0, getGroupItems(trigger).indexOf(trigger));
        render();
        lightbox.classList.add('is-open');
        document.body.classList.add('lightbox-open');
    };

    const close = () => {
        lightbox.classList.remove('is-open');
        document.body.classList.remove('lightbox-open');
        image.removeAttribute('src');
        image.alt = '';
        items = [];
        currentIndex = 0;
    };

    const move = (direction) => {
        if (items.length <= 1) {
            return;
        }

        currentIndex = (currentIndex + direction + items.length) % items.length;
        render();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-lightbox-trigger], .imported-gallery img, .wp-import-gallery img');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        open(trigger);
    });

    closeButton?.addEventListener('click', close);
    prevButton?.addEventListener('click', () => move(-1));
    nextButton?.addEventListener('click', () => move(1));
    image.addEventListener('load', syncOrientation);
    image.addEventListener('error', () => {
        image.classList.remove('is-portrait', 'is-landscape', 'is-square');
    });

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        }

        if (event.key === 'ArrowLeft') {
            move(-1);
        }

        if (event.key === 'ArrowRight') {
            move(1);
        }
    });
}

const focalPickers = document.querySelectorAll('[data-focal-picker]');

if (focalPickers.length > 0) {
    const clamp = (value) => Math.min(1, Math.max(0, value));
    const round = (value) => Math.round(value * 10000) / 10000;

    focalPickers.forEach((picker) => {
        const preview = picker.querySelector('[data-focal-preview]');
        const handle = picker.querySelector('[data-focal-handle]');
        const form = picker.closest('form');
        const xInput = form?.querySelector('[data-focal-x-input]');
        const yInput = form?.querySelector('[data-focal-y-input]');

        if (!preview || !handle || !xInput || !yInput) {
            return;
        }

        let dragging = false;

        const render = (x, y) => {
            const nextX = round(clamp(x));
            const nextY = round(clamp(y));

            picker.style.setProperty('--focal-x', `${nextX * 100}%`);
            picker.style.setProperty('--focal-y', `${nextY * 100}%`);
            preview.style.objectPosition = `${nextX * 100}% ${nextY * 100}%`;
            xInput.value = nextX.toFixed(4);
            yInput.value = nextY.toFixed(4);
        };

        const setFromPointer = (event) => {
            const rect = picker.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            render(x, y);
        };

        render(
            parseFloat(xInput.value || picker.dataset.focalX || '0.5'),
            parseFloat(yInput.value || picker.dataset.focalY || '0.25'),
        );

        picker.addEventListener('pointerdown', (event) => {
            dragging = true;
            picker.setPointerCapture(event.pointerId);
            setFromPointer(event);
        });

        picker.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            setFromPointer(event);
        });

        picker.addEventListener('pointerup', (event) => {
            dragging = false;

            if (picker.hasPointerCapture(event.pointerId)) {
                picker.releasePointerCapture(event.pointerId);
            }
        });

        picker.addEventListener('pointercancel', () => {
            dragging = false;
        });

        xInput.addEventListener('input', () => {
            render(parseFloat(xInput.value || '0.5'), parseFloat(yInput.value || '0.25'));
        });

        yInput.addEventListener('input', () => {
            render(parseFloat(xInput.value || '0.5'), parseFloat(yInput.value || '0.25'));
        });
    });
}
