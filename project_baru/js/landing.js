document.addEventListener('DOMContentLoaded', () => {
    // 1. Scroll Reveal Animation using Intersection Observer
    const reveals = document.querySelectorAll('.reveal');
    
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        reveals.forEach(reveal => {
            revealObserver.observe(reveal);
        });
    } else {
        // Fallback for older browsers
        const revealOnScroll = () => {
            reveals.forEach(reveal => {
                const windowHeight = window.innerHeight;
                const elementTop = reveal.getBoundingClientRect().top;
                const elementVisible = 100;
                if (elementTop < windowHeight - elementVisible) {
                    reveal.classList.add('active');
                }
            });
        };
        window.addEventListener('scroll', revealOnScroll);
        revealOnScroll();
    }

    // 2. Custom Dark/Light theme toggle mechanism (CoreUI compatible)
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const themeIcon = document.getElementById('theme-icon');
    const storageKey = 'coreui-free-bootstrap-admin-template-theme';

    const getAppliedTheme = () => {
        return document.documentElement.getAttribute('data-coreui-theme') || 'light';
    };

    const updateIcon = (theme) => {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.className = 'fas fa-sun text-warning'; // Show Sun in Dark Mode
        } else {
            themeIcon.className = 'fas fa-moon'; // Show Moon in Light Mode
        }
    };

    // Sync UI icon on initial load
    updateIcon(getAppliedTheme());

    // Observe data-coreui-theme changes to sync icon dynamically if modified elsewhere
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-coreui-theme') {
                updateIcon(getAppliedTheme());
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = getAppliedTheme();
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Set attributes and store preference
            document.documentElement.setAttribute('data-coreui-theme', targetTheme);
            localStorage.setItem(storageKey, targetTheme);
            updateIcon(targetTheme);

            // Dispatch event for system integrity (like charts in background dashboard)
            const event = new Event('ColorSchemeChange');
            document.documentElement.dispatchEvent(event);
        });
    }

    // 3. Horizontal Car Slider centering scale effect
    const slider = document.getElementById('car-slider');
    const slides = document.querySelectorAll('.car-slide');
    const prevBtn = document.getElementById('slide-prev');
    const nextBtn = document.getElementById('slide-next');

    if (slider && slides.length > 0) {
        const getSetWidth = () => {
            const setSize = slides.length / 3;
            let width = 0;
            for (let i = 0; i < setSize; i++) {
                width += slides[i].offsetWidth + 30; // 30px gap
            }
            return width;
        };

        const initMiddleScroll = () => {
            slider.style.scrollBehavior = 'auto';
            slider.scrollLeft = getSetWidth();
            slider.style.scrollBehavior = 'smooth';
        };

        // Initialize position on load
        setTimeout(initMiddleScroll, 150);

        const updateActiveSlide = () => {
            const containerCenter = slider.scrollLeft + slider.offsetWidth / 2;
            let closestSlide = null;
            let minDistance = Infinity;

            slides.forEach(slide => {
                const slideCenter = slide.offsetLeft + slide.offsetWidth / 2;
                const distance = Math.abs(containerCenter - slideCenter);
                if (distance < minDistance) {
                    minDistance = distance;
                    closestSlide = slide;
                }
            });

            slides.forEach(slide => {
                if (slide === closestSlide) {
                    slide.classList.add('active-slide');
                } else {
                    slide.classList.remove('active-slide');
                }
            });
        };

        const checkInfiniteLoop = () => {
            const setWidth = getSetWidth();
            if (setWidth <= 0) return;

            // If scrolled into Set A (left clone set)
            if (slider.scrollLeft < setWidth - 50) {
                slider.style.scrollBehavior = 'auto';
                slider.scrollLeft = slider.scrollLeft + setWidth;
                slider.style.scrollBehavior = 'smooth';
            }
            // If scrolled into Set C (right clone set)
            else if (slider.scrollLeft >= (setWidth * 2) - 50) {
                slider.style.scrollBehavior = 'auto';
                slider.scrollLeft = slider.scrollLeft - setWidth;
                slider.style.scrollBehavior = 'smooth';
            }
        };

        // Scroll event listener with requestAnimationFrame and debounce for seamless loop jump
        let ticking = false;
        let scrollTimeout;
        
        slider.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateActiveSlide();
                    ticking = false;
                });
                ticking = true;
            }

            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                checkInfiniteLoop();
            }, 100);
        });

        // Trigger initial active slide calculation
        setTimeout(updateActiveSlide, 200);

        // Click nav buttons
        if (prevBtn && nextBtn) {
            const getScrollAmount = () => {
                if (slides.length > 0) {
                    return slides[0].offsetWidth + 30; // Slide width + gap
                }
                return 410;
            };

            prevBtn.addEventListener('click', () => {
                slider.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
            });

            nextBtn.addEventListener('click', () => {
                slider.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
            });
        }
    }
});
