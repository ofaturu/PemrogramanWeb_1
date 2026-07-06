document.addEventListener('DOMContentLoaded', () => {
    // 1. Animasi scroll reveal menggunakan Intersection Observer
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
        // Cadangan untuk browser lama
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

    // 2. Mekanisme sakelar tema gelap/terang kustom (kompatibel dengan CoreUI)
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const themeIcon = document.getElementById('theme-icon');
    const storageKey = 'coreui-free-bootstrap-admin-template-theme';

    const getAppliedTheme = () => {
        return document.documentElement.getAttribute('data-coreui-theme') || 'light';
    };

    const updateIcon = (theme) => {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.className = 'fas fa-sun text-warning'; // Tampilkan Matahari di Mode Gelap
        } else {
            themeIcon.className = 'fas fa-moon'; // Tampilkan Bulan di Mode Terang
        }
    };

    // Sinkronkan ikon UI saat pertama kali dimuat
    updateIcon(getAppliedTheme());

    // Amati perubahan data-coreui-theme untuk menyinkronkan ikon secara dinamis jika diubah di tempat lain
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
            
            // Setel atribut dan simpan preferensi ke penyimpanan lokal
            document.documentElement.setAttribute('data-coreui-theme', targetTheme);
            localStorage.setItem(storageKey, targetTheme);
            updateIcon(targetTheme);

            // Kirim event untuk integritas sistem (seperti grafik di dashboard latar belakang)
            const event = new Event('ColorSchemeChange');
            document.documentElement.dispatchEvent(event);
        });
    }

    // 3. Efek pembesaran skala tengah Slider Mobil Horizontal
    const slider = document.getElementById('car-slider');
    const slides = document.querySelectorAll('.car-slide');
    const prevBtn = document.getElementById('slide-prev');
    const nextBtn = document.getElementById('slide-next');

    if (slider && slides.length > 0) {
        const getSetWidth = () => {
            const setSize = slides.length / 3;
            let width = 0;
            for (let i = 0; i < setSize; i++) {
                width += slides[i].offsetWidth + 30; // jarak antar kartu (gap) 30px
            }
            return width;
        };

        const initMiddleScroll = () => {
            slider.style.scrollBehavior = 'auto';
            slider.scrollLeft = getSetWidth();
            slider.style.scrollBehavior = 'smooth';
        };

        // Inisialisasi posisi gulir saat dimuat
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

            // Jika digulir masuk ke Set A (set klon sebelah kiri)
            if (slider.scrollLeft < setWidth - 50) {
                slider.style.scrollBehavior = 'auto';
                slider.scrollLeft = slider.scrollLeft + setWidth;
                slider.style.scrollBehavior = 'smooth';
            }
            // Jika digulir masuk ke Set C (set klon sebelah kanan)
            else if (slider.scrollLeft >= (setWidth * 2) - 50) {
                slider.style.scrollBehavior = 'auto';
                slider.scrollLeft = slider.scrollLeft - setWidth;
                slider.style.scrollBehavior = 'smooth';
            }
        };

        // Listener event scroll dengan requestAnimationFrame dan debounce untuk lompatan loop tak terbatas
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

        // Picu kalkulasi awal slide yang aktif
        setTimeout(updateActiveSlide, 200);

        // Pengikatan tombol navigasi panah
        if (prevBtn && nextBtn) {
            const getScrollAmount = () => {
                if (slides.length > 0) {
                    return slides[0].offsetWidth + 30; // Lebar slide + gap
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

        // 4. Mekanisme Penyaringan Kategori Armada (Mobil vs Motor)
        const filterButtons = document.querySelectorAll('.btn-filter');
        if (filterButtons.length > 0) {
            filterButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    // Hapus kelas aktif dari semua tombol filter
                    filterButtons.forEach(b => b.classList.remove('active'));
                    // Tambah kelas aktif ke tombol yang diklik
                    btn.classList.add('active');
                    
                    const filterValue = btn.getAttribute('data-filter');
                    
                    // Sembunyikan atau tampilkan slide berdasarkan jenis
                    slides.forEach(slide => {
                        const jenis = slide.getAttribute('data-jenis');
                        if (filterValue === 'all' || jenis === filterValue) {
                            slide.style.display = 'block';
                        } else {
                            slide.style.display = 'none';
                        }
                    });
                    
                    // Kembalikan slider ke awal tengah jika semua, atau ke ujung kiri jika difilter
                    if (filterValue === 'all') {
                        initMiddleScroll();
                    } else {
                        slider.style.scrollBehavior = 'auto';
                        slider.scrollLeft = 0;
                        slider.style.scrollBehavior = 'smooth';
                    }
                    
                    // Perbarui slide aktif setelah perubahan filter
                    setTimeout(updateActiveSlide, 100);
                });
            });
        }
    }
});
