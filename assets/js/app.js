/**
 * FDTV App - PWA and Core Functionality
 */

class FDTVApp {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.init();
    }

    init() {
        // Register service worker
        this.registerServiceWorker();

        // Set up install prompt
        this.setupInstallPrompt();

        // Check if already installed
        this.checkInstallStatus();

        // Set up UI enhancements
        this.setupUIEnhancements();
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js', {
                    scope: '/'
                });

                console.log('[FDTV] Service Worker registered:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });

            } catch (error) {
                console.error('[FDTV] Service Worker registration failed:', error);
            }
        }
    }

    setupInstallPrompt() {
        // Capture the install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[FDTV] Install prompt captured');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });

        // Track successful installs
        window.addEventListener('appinstalled', () => {
            console.log('[FDTV] App installed');
            this.deferredPrompt = null;
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstallSuccessMessage();
        });
    }

    checkInstallStatus() {
        // Check if running as standalone
        if (window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true) {
            this.isInstalled = true;
            console.log('[FDTV] Running as installed PWA');
        }
    }

    showInstallButton() {
        const installBanner = document.getElementById('installBanner');
        const installBtn = document.getElementById('installAppBtn');

        if (installBanner) {
            installBanner.classList.add('show');
        }

        if (installBtn) {
            installBtn.style.display = 'flex';
            installBtn.addEventListener('click', () => this.promptInstall());
        }

        // Also show floating install button if it exists
        const floatingBtn = document.getElementById('floatingInstallBtn');
        if (floatingBtn) {
            setTimeout(() => {
                floatingBtn.classList.add('show');
            }, 3000);
        }
    }

    hideInstallButton() {
        const installBanner = document.getElementById('installBanner');
        const installBtn = document.getElementById('installAppBtn');
        const floatingBtn = document.getElementById('floatingInstallBtn');

        if (installBanner) installBanner.classList.remove('show');
        if (installBtn) installBtn.style.display = 'none';
        if (floatingBtn) floatingBtn.classList.remove('show');
    }

    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('[FDTV] No install prompt available');
            return;
        }

        // Show the install prompt
        this.deferredPrompt.prompt();

        // Wait for user response
        const { outcome } = await this.deferredPrompt.userChoice;
        console.log('[FDTV] Install prompt outcome:', outcome);

        this.deferredPrompt = null;

        if (outcome === 'accepted') {
            this.hideInstallButton();
        }
    }

    showInstallSuccessMessage() {
        const notification = document.createElement('div');
        notification.className = 'pwa-notification success';
        notification.innerHTML = `
            <div class="pwa-notification-content">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <span>FDTV has been installed!</span>
            </div>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-notification update';
        notification.innerHTML = `
            <div class="pwa-notification-content">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                <span>New version available!</span>
                <button onclick="window.location.reload()">Update</button>
            </div>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
    }

    setupUIEnhancements() {
        // Add loading states to buttons
        document.querySelectorAll('button[type="submit"], .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.form && !this.form.checkValidity()) return;
                if (this.classList.contains('no-loading')) return;

                this.classList.add('loading');
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add intersection observer for animations
        this.setupScrollAnimations();

        // Mobile menu toggle
        this.setupMobileMenu();
    }

    setupScrollAnimations() {
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe elements with animation classes
        document.querySelectorAll('.fade-in, .slide-up, .slide-in-left, .slide-in-right').forEach(el => {
            observer.observe(el);
        });
    }

    setupMobileMenu() {
        const menuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenu = document.getElementById('mobileMenu');

        if (menuToggle && mobileMenu) {
            menuToggle.addEventListener('click', () => {
                mobileMenu.classList.toggle('open');
                menuToggle.classList.toggle('active');
            });

            // Close menu on link click
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('open');
                    menuToggle.classList.remove('active');
                });
            });
        }
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.fdtvApp = new FDTVApp();
});

// Add PWA notification styles
const pwaStyles = document.createElement('style');
pwaStyles.textContent = `
    .pwa-notification {
        position: fixed;
        bottom: -100px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #1a1a3e, #2d2b5e);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        transition: bottom 0.3s ease;
    }

    .pwa-notification.show {
        bottom: 2rem;
    }

    .pwa-notification-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #fff;
        font-size: 0.95rem;
    }

    .pwa-notification-content svg {
        color: #22c55e;
        flex-shrink: 0;
    }

    .pwa-notification.update .pwa-notification-content svg {
        color: #6366f1;
    }

    .pwa-notification-content button {
        background: #6366f1;
        color: #fff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        margin-left: 0.5rem;
        transition: background 0.2s;
    }

    .pwa-notification-content button:hover {
        background: #4f46e5;
    }

    #floatingInstallBtn {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        border: none;
        padding: 1rem 1.5rem;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    #floatingInstallBtn.show {
        transform: translateY(0);
        opacity: 1;
    }

    #floatingInstallBtn:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(99, 102, 241, 0.5);
    }

    .btn.loading {
        pointer-events: none;
        opacity: 0.7;
    }

    .btn.loading::after {
        content: '';
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        margin-left: 0.5rem;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Scroll animations */
    .fade-in, .slide-up, .slide-in-left, .slide-in-right {
        opacity: 0;
        transition: all 0.6s ease-out;
    }

    .fade-in.animate-in {
        opacity: 1;
    }

    .slide-up {
        transform: translateY(30px);
    }

    .slide-up.animate-in {
        opacity: 1;
        transform: translateY(0);
    }

    .slide-in-left {
        transform: translateX(-30px);
    }

    .slide-in-left.animate-in {
        opacity: 1;
        transform: translateX(0);
    }

    .slide-in-right {
        transform: translateX(30px);
    }

    .slide-in-right.animate-in {
        opacity: 1;
        transform: translateX(0);
    }
`;
document.head.appendChild(pwaStyles);
