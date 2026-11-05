/**
 * SportsHub navigation, receipt preview/upload and form feedback.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Enable instantaneous :active state on iOS Safari & mobile devices
    document.addEventListener('touchstart', () => {}, { passive: true });

    // Toast Notification System
    window.showToast = function(message, type = 'info', duration = 3000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast-pill toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('visible'));

        setTimeout(() => {
            toast.classList.remove('visible');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, duration);
    };

    // User dropdown menu toggle & keyboard accessibility
    const userMenuPill = document.querySelector('.user-menu-pill');
    const userDropdown = document.querySelector('.user-dropdown');
    const setAccountMenuOpen = (open) => {
        if (!userDropdown || !userMenuPill) return;
        userDropdown.inert = !open;
        userDropdown.classList.toggle('show', open);
        userMenuPill.setAttribute('aria-expanded', String(open));
    };
    if (userMenuPill && userDropdown) {
        userMenuPill.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = userDropdown.classList.contains('show');
            setAccountMenuOpen(!isOpen);
        });
        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target)) setAccountMenuOpen(false);
        });
        document.addEventListener('focusin', (e) => {
            if (e.target !== userMenuPill && !userDropdown.contains(e.target)) setAccountMenuOpen(false);
        });
    }

    // Mobile Menu Drawer Navigation
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mobileDrawer = document.querySelector('.mobile-drawer');
    const mobileDrawerClose = document.querySelector('.mobile-drawer-close');
    const mobileDrawerBackdrop = document.querySelector('.mobile-drawer-backdrop');
    let closeDrawer = () => {};
    const setBackgroundInert = (active) => {
        document.querySelectorAll('main, .site-footer').forEach(el => el.inert = active);
    };
    const trapFocus = (event, surface) => {
        if (event.key !== 'Tab') return;
        const items = [...surface.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),textarea,[tabindex="0"]')].filter(el => el.getClientRects().length);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };

    if (mobileMenuBtn && mobileDrawer) {
        const toggleDrawer = (open) => {
            mobileDrawer.inert = !open;
            mobileDrawer.classList.toggle('open', open);
            mobileDrawerBackdrop?.classList.toggle('open', open);
            mobileMenuBtn.setAttribute('aria-expanded', String(open));
            document.body.style.overflow = open ? 'hidden' : '';
            setBackgroundInert(open);
            if (open) mobileDrawerClose?.focus(); else mobileMenuBtn.focus();
        };
        closeDrawer = () => toggleDrawer(false);
        mobileDrawer.addEventListener('keydown', event => trapFocus(event, mobileDrawer));
        window.matchMedia('(min-width: 821px)').addEventListener('change', event => {
            if (event.matches && mobileDrawer.classList.contains('open')) toggleDrawer(false);
        });

        mobileMenuBtn.addEventListener('click', () => toggleDrawer(!mobileDrawer.classList.contains('open')));
        if (mobileDrawerClose) mobileDrawerClose.addEventListener('click', () => toggleDrawer(false));
        if (mobileDrawerBackdrop) mobileDrawerBackdrop.addEventListener('click', () => toggleDrawer(false));
    }

    // Close navigation with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (userDropdown && userDropdown.classList.contains('show')) {
                setAccountMenuOpen(false);
                userMenuPill.focus();
            }
            if (mobileDrawer && mobileDrawer.classList.contains('open')) {
                closeDrawer();
            }
        }
    });

    // Floating Back-to-Top Button Handler
    const backToTopBtn = document.getElementById('back-to-top');
    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            backToTopBtn.classList.toggle('visible', window.scrollY > 300);
        }, { passive: true });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'
            });
        });
    }

    // Receipt Modal Zoom & Preview Controller (Apple-calibrated Lightbox)
    let receiptModal = document.getElementById('receipt-lightbox');
    let currentZoom = 1.0;
    let receiptTrigger = null;

    function initReceiptLightbox() {
        if (!receiptModal) {
            receiptModal = document.createElement('div');
            receiptModal.id = 'receipt-lightbox';
            receiptModal.className = 'receipt-lightbox-modal';
            receiptModal.setAttribute('role', 'dialog');
            receiptModal.setAttribute('aria-modal', 'true');
            receiptModal.setAttribute('aria-labelledby', 'receipt-modal-title');
            receiptModal.innerHTML = `
                <div class="receipt-lightbox-box">
                    <div class="receipt-lightbox-header">
                        <div>
                            <h4 id="receipt-modal-title">Payment Receipt Preview</h4>
                            <div id="receipt-modal-meta" style="font-size: 0.8rem; color: #86868b; margin-top: 2px;">Click image to toggle 2x zoom or use controls</div>
                        </div>
                        <div class="receipt-lightbox-toolbar">
                            <button type="button" class="receipt-tool-btn" id="receipt-zoom-out" title="Zoom Out">−</button>
                            <span id="receipt-zoom-level" style="font-size: 0.82rem; font-weight: 600; color: #1d1d1f; min-width: 44px; text-align: center;">100%</span>
                            <button type="button" class="receipt-tool-btn" id="receipt-zoom-in" title="Zoom In">+</button>
                            <button type="button" class="receipt-tool-btn" id="receipt-zoom-reset" title="Reset Zoom" style="width: auto; padding: 0 10px; border-radius: 980px; font-size: 0.78rem;">Reset</button>
                            <a href="#" id="receipt-open-raw" target="_blank" class="receipt-tool-btn" title="Open in new tab" style="text-decoration: none;">↗</a>
                            <button type="button" class="receipt-tool-btn receipt-close-btn" id="receipt-modal-close" title="Close preview"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                    </div>
                    <div class="receipt-lightbox-viewport" id="receipt-viewport">
                        <img id="receipt-lightbox-img" src="" alt="Payment Receipt" draggable="false">
                    </div>
                    <div class="receipt-lightbox-footer">
                        <span id="receipt-lightbox-caption">Official transfer verification evidence</span>
                        <button type="button" id="receipt-modal-done" style="padding: 6px 16px; background: #1d1d1f; color: #fff; border: none; border-radius: 980px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Done</button>
                    </div>
                </div>
            `;
            document.body.appendChild(receiptModal);

            const img = document.getElementById('receipt-lightbox-img');
            const zoomInBtn = document.getElementById('receipt-zoom-in');
            const zoomOutBtn = document.getElementById('receipt-zoom-out');
            const zoomResetBtn = document.getElementById('receipt-zoom-reset');
            const zoomLevel = document.getElementById('receipt-zoom-level');
            const closeBtn = document.getElementById('receipt-modal-close');
            const doneBtn = document.getElementById('receipt-modal-done');

            const setZoom = (z) => {
                currentZoom = Math.min(3.0, Math.max(0.5, Math.round(z * 100) / 100));
                if (img) {
                    img.style.transform = `scale(${currentZoom})`;
                    img.classList.toggle('zoomed', currentZoom > 1.0);
                }
                if (zoomLevel) zoomLevel.textContent = `${Math.round(currentZoom * 100)}%`;
            };

            zoomInBtn?.addEventListener('click', () => setZoom(currentZoom + 0.25));
            zoomOutBtn?.addEventListener('click', () => setZoom(currentZoom - 0.25));
            zoomResetBtn?.addEventListener('click', () => setZoom(1.0));

            img?.addEventListener('click', () => {
                setZoom(currentZoom > 1.0 ? 1.0 : 2.0);
            });

            const closeModal = () => {
                receiptModal.classList.remove('show');
                setZoom(1.0);
                document.body.style.overflow = '';
                setBackgroundInert(false);
                receiptTrigger?.focus();
            };

            closeBtn?.addEventListener('click', closeModal);
            doneBtn?.addEventListener('click', closeModal);
            receiptModal.addEventListener('click', (e) => {
                if (e.target === receiptModal) closeModal();
            });

            document.addEventListener('keydown', (e) => {
                if (!receiptModal.classList.contains('show')) return;
                trapFocus(e, receiptModal);
                if (e.key === 'Escape') closeModal();
                if (e.key === '+' || e.key === '=') setZoom(currentZoom + 0.25);
                if (e.key === '-' || e.key === '_') setZoom(currentZoom - 0.25);
                if (e.key === '0') setZoom(1.0);
            });
        }
    }

    window.openReceiptZoom = function(url, title = 'Payment Receipt Preview', caption = 'Official transfer verification evidence') {
        receiptTrigger = document.activeElement;
        initReceiptLightbox();
        const img = document.getElementById('receipt-lightbox-img');
        const titleEl = document.getElementById('receipt-modal-title');
        const captionEl = document.getElementById('receipt-lightbox-caption');
        const rawLink = document.getElementById('receipt-open-raw');

        if (img) img.src = url;
        if (titleEl) titleEl.textContent = title;
        if (captionEl) captionEl.textContent = caption;
        if (rawLink) rawLink.href = url;

        currentZoom = 1.0;
        if (img) {
            img.style.transform = 'scale(1)';
            img.classList.remove('zoomed');
        }
        const zoomLevel = document.getElementById('receipt-zoom-level');
        if (zoomLevel) zoomLevel.textContent = '100%';

        receiptModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        setBackgroundInert(true);
        document.getElementById('receipt-modal-close')?.focus();
    };

    // Attach listeners to any trigger element with [data-receipt-zoom]
    document.querySelectorAll('[data-receipt-zoom]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const url = trigger.getAttribute('data-receipt-zoom');
            const title = trigger.getAttribute('data-receipt-title') || 'Payment Receipt Preview';
            const caption = trigger.getAttribute('data-receipt-caption') || 'Official transfer verification evidence';
            if (url) window.openReceiptZoom(url, title, caption);
        });
    });

    // Student Receipt Upload File Drag/Drop & Live Client Preview
    const fileInput = document.getElementById('receipt');
    const dropzone = document.getElementById('receipt-dropzone');
    const previewContainer = document.getElementById('receipt-upload-preview');

    if (fileInput) {
        const fieldError = document.getElementById('receipt-error');
        const handleFileSelected = (file) => {
            if (!file) return;
            if (fieldError) fieldError.textContent = '';
            fileInput.removeAttribute('aria-invalid');
            const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                if (fieldError) fieldError.textContent = 'Choose a JPG, PNG, WebP or PDF file, then retry.';
                fileInput.setAttribute('aria-invalid', 'true');
                fileInput.value = '';
                if (previewContainer) { previewContainer.hidden = true; previewContainer.style.display = 'none'; }
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                if (fieldError) fieldError.textContent = 'File exceeds 5 MB. Choose a smaller file, then retry.';
                fileInput.setAttribute('aria-invalid', 'true');
                fileInput.value = '';
                if (previewContainer) { previewContainer.hidden = true; previewContainer.style.display = 'none'; }
                return;
            }

            if (previewContainer) {
                const nameEl = previewContainer.querySelector('.preview-name');
                const sizeEl = previewContainer.querySelector('.preview-size');
                const isPdf = file.type === 'application/pdf';

                if (nameEl) nameEl.textContent = file.name;
                if (sizeEl) sizeEl.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB · ${isPdf ? 'PDF Document' : 'Image Receipt'}`;

                previewContainer.style.display = 'flex';
                previewContainer.hidden = false;
            }
        };

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                handleFileSelected(fileInput.files[0]);
            }
        });

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('drag-over');
                });
            });
            ['dragleave', 'drop'].forEach(name => {
                dropzone.addEventListener(name, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('drag-over');
                });
            });
            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelected(e.dataTransfer.files[0]);
                }
            });
        }
    }

    // Preserve button name/value when disabling submits (review decisions depend on it).
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            const button = event.submitter;
            if (!button?.dataset.pendingLabel) return;
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            if (button.name) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = button.name; hidden.value = button.value;
                form.appendChild(hidden);
            }
            button.textContent = button.dataset.pendingLabel;
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"]').forEach(item => item.disabled = true);
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    document.querySelector('[data-focus-error]')?.focus();

    // Clipboard Copy Button
    document.querySelectorAll('[data-copy-link]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const textToCopy = btn.getAttribute('data-copy-link') || window.location.href;
            navigator.clipboard.writeText(textToCopy).then(() => {
                if (window.showToast) window.showToast('Link copied to clipboard!', 'success');
            }).catch(() => {
                if (window.showToast) window.showToast('Failed to copy link.', 'error');
            });
        });
    });
});
