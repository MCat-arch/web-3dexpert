/*
 * Alpine, CSP-safe build.
 *
 * WHY THIS FILE LOOKS LIKE THIS
 * -----------------------------
 * app/Http/Middleware/SecurityHeaders.php sends `script-src 'self'` with no
 * 'unsafe-eval'. Standard Alpine compiles every directive expression with
 * `new Function(...)` / the AsyncFunction constructor, so under that CSP every
 * expression throws and nothing on the site works.
 *
 * The CSP build swaps the evaluator for one that only resolves NAMES against
 * the component scope. It cannot evaluate expressions. So:
 *
 *   NOT ALLOWED            ->  INSTEAD
 *   x-data="{ open: false }"   x-data="navMenu"            (registered below)
 *   @click="open = !open"      wired up in init()
 *   x-show="current === 2"     a CSS class toggled by the component
 *   x-data="carousel(5)"       x-data="carousel" + data-count="5"
 *
 * Every component here wires its own listeners and toggles its own classes in
 * init(). The markup carries only `x-data="name"` plus `data-*` attributes and
 * no Alpine expressions at all, which means:
 *   - nothing depends on the CSP evaluator beyond the x-data name lookup
 *   - the server-rendered state is the no-JS state, so the page degrades
 *     sanely instead of breaking
 *
 * Parameters come from `data-*` attributes rather than x-data arguments,
 * because the CSP build resolves x-data as a plain name and cannot call it.
 */

import Alpine from '@alpinejs/csp';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const intAttr = (el, name, fallback) => {
    const value = Number.parseInt(el.dataset[name] ?? '', 10);

    return Number.isFinite(value) ? value : fallback;
};

/* ------------------------------------------------------------------ */
/* Header: mobile menu                                                */
/* ------------------------------------------------------------------ */

Alpine.data('navMenu', () => ({
    open: false,

    init() {
        this.toggleButton = this.$el.querySelector('[data-nav-toggle]');
        this.panel = this.$el.querySelector('[data-nav-panel]');

        if (!this.toggleButton || !this.panel) {
            return;
        }

        this.toggleButton.addEventListener('click', (event) => {
            event.stopPropagation();
            this.setOpen(!this.open);
        });

        // Any link inside the panel closes it, matching legacy's
        // closeMobileMenu() on nav-link click.
        this.panel.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                this.setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (this.open && !this.$el.contains(event.target)) {
                this.setOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.open) {
                this.setOpen(false);
                this.toggleButton.focus();
            }
        });

        // Close the menu when resizing past the desktop breakpoint.
        // Keep 1121 in sync with the `max-width: 1120px` media queries in app.css.
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1121 && this.open) {
                this.setOpen(false);
            }
        });

        this.setOpen(false);
    },

    setOpen(value) {
        this.open = value;
        this.panel.classList.toggle('is-open', value);
        this.toggleButton.classList.toggle('is-open', value);
        this.toggleButton.setAttribute('aria-expanded', value ? 'true' : 'false');
    },
}));

/* ------------------------------------------------------------------ */
/* Hero carousel                                                      */
/* ------------------------------------------------------------------ */

Alpine.data('carousel', () => ({
    current: 0,
    timer: null,

    init() {
        this.slides = Array.from(this.$el.querySelectorAll('[data-slide]'));
        this.dots = Array.from(this.$el.querySelectorAll('[data-dot]'));
        this.interval = intAttr(this.$el, 'interval', 4000);

        if (this.slides.length === 0) {
            return;
        }

        this.dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                this.go(index);
                this.stop();   // a manual choice should not be yanked away
            });
        });

        this.go(0);

        // Pause on hover/focus so a visitor reading the headline keeps it.
        this.$el.addEventListener('mouseenter', () => this.stop());
        this.$el.addEventListener('mouseleave', () => this.start());
        this.$el.addEventListener('focusin', () => this.stop());

        document.addEventListener('visibilitychange', () => {
            document.hidden ? this.stop() : this.start();
        });

        this.start();
    },

    destroy() {
        this.stop();
    },

    start() {
        if (this.timer || prefersReducedMotion || this.slides.length < 2) {
            return;
        }

        this.timer = setInterval(() => this.go(this.current + 1), this.interval);
    },

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    },

    go(index) {
        this.current = (index + this.slides.length) % this.slides.length;

        this.slides.forEach((slide, i) => {
            const active = i === this.current;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');

            // Slides past the first are marked loading="lazy" and only start
            // downloading once they are no longer hidden.
            if (active) {
                slide.querySelectorAll('img[loading="lazy"]').forEach((img) => {
                    img.loading = 'eager';
                });
            }
        });

        this.dots.forEach((dot, i) => {
            const active = i === this.current;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-current', active ? 'true' : 'false');
        });
    },
}));

/* ------------------------------------------------------------------ */
/* Portfolio detail gallery (manual, arrow-key navigable)             */
/* ------------------------------------------------------------------ */

Alpine.data('gallery', () => ({
    current: 0,

    init() {
        this.items = Array.from(this.$el.querySelectorAll('[data-gallery-item]'));
        this.thumbs = Array.from(this.$el.querySelectorAll('[data-gallery-thumb]'));

        if (this.items.length === 0) {
            return;
        }

        this.thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', () => this.go(index));
        });

        const prev = this.$el.querySelector('[data-gallery-prev]');
        const next = this.$el.querySelector('[data-gallery-next]');

        prev?.addEventListener('click', () => this.go(this.current - 1));
        next?.addEventListener('click', () => this.go(this.current + 1));

        this.$el.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight') {
                this.go(this.current + 1);
            } else if (event.key === 'ArrowLeft') {
                this.go(this.current - 1);
            }
        });

        this.go(0);
    },

    go(index) {
        this.current = (index + this.items.length) % this.items.length;

        this.items.forEach((item, i) => {
            const active = i === this.current;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        this.thumbs.forEach((thumb, i) => {
            const active = i === this.current;
            thumb.classList.toggle('is-active', active);
            thumb.setAttribute('aria-current', active ? 'true' : 'false');
        });
    },
}));

/* ------------------------------------------------------------------ */
/* Click-to-load YouTube embed                                        */
/* ------------------------------------------------------------------ */

/*
 * Legacy rendered 15 live iframes on the homepage (10 testimonials, 3 design
 * reviews, 1 location, 1 "mengapa"), none of them lazy. Each embed is its own
 * browsing context with its own JS runtime, so the page arrived with 15 of them
 * competing for the main thread and for the ~6-connections-per-host budget.
 *
 * Only the thumbnail loads up front; the iframe is created on click.
 */
Alpine.data('ytFacade', () => ({
    loaded: false,

    init() {
        this.videoId = this.$el.dataset.videoId || '';

        if (!this.videoId) {
            return;
        }

        const activate = (event) => {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.load();
        };

        this.$el.addEventListener('click', activate);
        this.$el.addEventListener('keydown', activate);
    },

    load() {
        if (this.loaded) {
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.src =
            `https://www.youtube-nocookie.com/embed/${this.videoId}` +
            '?autoplay=1&rel=0&modestbranding=1';
        iframe.title = this.$el.dataset.videoTitle || 'YouTube';
        iframe.className = 'absolute inset-0 h-full w-full';
        iframe.allow =
            'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allowFullscreen = true;
        iframe.setAttribute('frameborder', '0');

        this.$el.replaceChildren(iframe);
        this.loaded = true;

        this.$el.removeAttribute('role');
        this.$el.removeAttribute('tabindex');
        this.$el.removeAttribute('aria-label');
        this.$el.classList.remove('cursor-pointer');
    },
}));

/* ------------------------------------------------------------------ */
/* FAQ: close the open <details> when clicking outside the list       */
/* ------------------------------------------------------------------ */

/*
 * The accordion itself is native <details name="faq">, so "one open at a time"
 * and keyboard support come from the browser and work with JS disabled. This
 * component only adds legacy's extra behaviour: a click inside the section but
 * outside an item closes everything. It also enforces the exclusive behaviour
 * on browsers that do not yet support the name attribute on <details>.
 */
Alpine.data('faqAccordion', () => ({
    init() {
        this.items = Array.from(this.$el.querySelectorAll('details'));

        const supportsExclusive = 'name' in document.createElement('details');

        if (!supportsExclusive) {
            this.items.forEach((item) => {
                item.addEventListener('toggle', () => {
                    if (!item.open) {
                        return;
                    }

                    this.items.forEach((other) => {
                        if (other !== item) {
                            other.open = false;
                        }
                    });
                });
            });
        }

        document.addEventListener('click', (event) => {
            if (this.$el.contains(event.target) && !event.target.closest('details')) {
                this.closeAll();
            }
        });
    },

    closeAll() {
        this.items.forEach((item) => {
            item.open = false;
        });
    },
}));

/* ------------------------------------------------------------------ */
/* "Selengkapnya": reveal portfolio items past the visible limit       */
/* ------------------------------------------------------------------ */

Alpine.data('revealMore', () => ({
    init() {
        this.hidden = Array.from(this.$el.querySelectorAll('[data-reveal-item]'));
        this.button = this.$el.querySelector('[data-reveal-button]');

        if (!this.button || this.hidden.length === 0) {
            return;
        }

        // Hidden until JS confirms the button can work, so no-JS visitors do
        // not see a dead control.
        this.button.hidden = false;

        this.button.addEventListener('click', () => {
            this.hidden.forEach((item) => item.classList.remove('is-hidden'));
            this.button.hidden = true;
        });
    },
}));

/* ------------------------------------------------------------------ */
/* Testimonials: expand/collapse toggle with the arrow button          */
/* ------------------------------------------------------------------ */

/*
 * Unlike revealMore (one-way reveal), testimonials must toggle both ways:
 * chevron-down expands the hidden grid items, chevron-up (the same button
 * rotated via .is-open) collapses them back and scrolls the viewport to the
 * top of the grid so the visitor is not left lost below the fold.
 *
 * Markup contract (CSP-safe): no Alpine expressions, only
 * `x-data="testimonialToggle"` plus data-* attributes.
 */
Alpine.data('testimonialToggle', () => ({
    open: false,

    init() {
        this.items = Array.from(this.$el.querySelectorAll('[data-reveal-item]'));
        this.button = this.$el.querySelector('[data-reveal-button]');
        this.grid = this.$el.querySelector('[data-testimonial-grid]');

        // No surplus data, or no button rendered: nothing to do. The arrow
        // stays hidden so visitors never see a dead control.
        if (!this.button || this.items.length === 0) {
            return;
        }

        // Hidden until JS confirms the button can work, so no-JS visitors do
        // not see a dead control.
        this.button.hidden = false;

        this.button.addEventListener('click', () => {
            this.setOpen(!this.open);
        });
    },

    setOpen(value) {
        this.open = value;

        this.items.forEach((item) => {
            item.classList.toggle('is-hidden', !value);
        });

        this.button.classList.toggle('is-open', value);
        this.button.setAttribute('aria-expanded', value ? 'true' : 'false');
        this.button.setAttribute('aria-label', value
            ? this.button.dataset.labelCollapse
            : this.button.dataset.labelExpand);

        // On collapse, bring the viewport back to the top of the grid
        // (scroll-margin-top on the grid keeps clear of the sticky header).
        if (!value && this.grid) {
            this.grid.scrollIntoView({
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
                block: 'start',
            });
        }
    },
}));

/* ------------------------------------------------------------------ */
/* Event registration confirmation dialog                             */
/* ------------------------------------------------------------------ */

/*
 * Native <dialog> plus plain listeners, for the same CSP reason as the rest
 * of this file: no Alpine expressions, only `x-data="eventRegister"`.
 *
 * The server renders the attendee defaults (name, phone, locked email), so
 * this component only opens and closes the dialog and gives the submit button
 * a pending state. If JS is unavailable, the <noscript> form beside the
 * dialog still allows registration.
 */
Alpine.data('eventRegister', () => ({
    init() {
        this.dialog = this.$el.querySelector('[data-event-register-dialog]');

        if (!this.dialog) {
            return;
        }

        this.$el.querySelectorAll('[data-event-register-open]').forEach((button) => {
            button.addEventListener('click', () => this.open());
        });

        this.dialog.querySelectorAll('[data-event-register-close]').forEach((button) => {
            button.addEventListener('click', () => this.close());
        });

        // Clicking the backdrop is a close gesture; clicks inside the panel
        // bubble from a child, so checking the target is the dialog itself is
        // enough to tell them apart.
        this.dialog.addEventListener('click', (event) => {
            if (event.target === this.dialog) {
                this.close();
            }
        });

        this.dialog.addEventListener('close', () => {
            document.body.classList.remove('has-event-dialog');
        });

        const form = this.dialog.querySelector('form');

        form?.addEventListener('submit', () => {
            const submit = form.querySelector('[type="submit"]');

            if (! submit) {
                return;
            }

            submit.disabled = true;
            submit.textContent = submit.dataset.loadingLabel || submit.textContent;
        });

        // After a failed validation the controller redirects back; reopen the
        // dialog so the errors are not hidden behind a closed modal.
        if (this.dialog.dataset.open === 'true') {
            this.open();
        }
    },

    open() {
        if (! this.dialog.open) {
            this.dialog.showModal();
            document.body.classList.add('has-event-dialog');
        }

        this.dialog.querySelector('input:not([readonly])')?.focus();
    },

    close() {
        this.dialog.close();
    },
}));

window.Alpine = Alpine;
Alpine.start();

// /*
//  * Alpine, CSP-safe build.
//  *
//  * WHY THIS FILE LOOKS LIKE THIS
//  * -----------------------------
//  * app/Http/Middleware/SecurityHeaders.php sends `script-src 'self'` with no
//  * 'unsafe-eval'. Standard Alpine compiles every directive expression with
//  * `new Function(...)` / the AsyncFunction constructor, so under that CSP every
//  * expression throws and nothing on the site works.
//  *
//  * The CSP build swaps the evaluator for one that only resolves NAMES against
//  * the component scope. It cannot evaluate expressions. So:
//  *
//  *   NOT ALLOWED            ->  INSTEAD
//  *   x-data="{ open: false }"   x-data="navMenu"            (registered below)
//  *   @click="open = !open"      wired up in init()
//  *   x-show="current === 2"     a CSS class toggled by the component
//  *   x-data="carousel(5)"       x-data="carousel" + data-count="5"
//  *
//  * Every component here wires its own listeners and toggles its own classes in
//  * init(). The markup carries only `x-data="name"` plus `data-*` attributes and
//  * no Alpine expressions at all, which means:
//  *   - nothing depends on the CSP evaluator beyond the x-data name lookup
//  *   - the server-rendered state is the no-JS state, so the page degrades
//  *     sanely instead of breaking
//  *
//  * Parameters come from `data-*` attributes rather than x-data arguments,
//  * because the CSP build resolves x-data as a plain name and cannot call it.
//  */

// import Alpine from '@alpinejs/csp';

// const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// const intAttr = (el, name, fallback) => {
//     const value = Number.parseInt(el.dataset[name] ?? '', 10);

//     return Number.isFinite(value) ? value : fallback;
// };

// /* ------------------------------------------------------------------ */
// /* Header: mobile menu                                                */
// /* ------------------------------------------------------------------ */

// Alpine.data('navMenu', () => ({
//     open: false,

//     init() {
//         this.toggleButton = this.$el.querySelector('[data-nav-toggle]');
//         this.panel = this.$el.querySelector('[data-nav-panel]');

//         if (!this.toggleButton || !this.panel) {
//             return;
//         }

//         this.toggleButton.addEventListener('click', (event) => {
//             event.stopPropagation();
//             this.setOpen(!this.open);
//         });

//         // Any link inside the panel closes it, matching legacy's
//         // closeMobileMenu() on nav-link click.
//         this.panel.addEventListener('click', (event) => {
//             if (event.target.closest('a')) {
//                 this.setOpen(false);
//             }
//         });

//         document.addEventListener('click', (event) => {
//             if (this.open && !this.$el.contains(event.target)) {
//                 this.setOpen(false);
//             }
//         });

//         document.addEventListener('keydown', (event) => {
//             if (event.key === 'Escape' && this.open) {
//                 this.setOpen(false);
//                 this.toggleButton.focus();
//             }
//         });

//         // Legacy closed the menu on resize past the desktop breakpoint.
//         window.addEventListener('resize', () => {
//             if (window.innerWidth >= 1024 && this.open) {
//                 this.setOpen(false);
//             }
//         });

//         this.setOpen(false);
//     },

//     setOpen(value) {
//         this.open = value;
//         this.panel.classList.toggle('is-open', value);
//         this.toggleButton.classList.toggle('is-open', value);
//         this.toggleButton.setAttribute('aria-expanded', value ? 'true' : 'false');
//     },
// }));

// /* ------------------------------------------------------------------ */
// /* Hero carousel                                                      */
// /* ------------------------------------------------------------------ */

// Alpine.data('carousel', () => ({
//     current: 0,
//     timer: null,

//     init() {
//         this.slides = Array.from(this.$el.querySelectorAll('[data-slide]'));
//         this.dots = Array.from(this.$el.querySelectorAll('[data-dot]'));
//         this.interval = intAttr(this.$el, 'interval', 4000);

//         if (this.slides.length === 0) {
//             return;
//         }

//         this.dots.forEach((dot, index) => {
//             dot.addEventListener('click', () => {
//                 this.go(index);
//                 this.stop();   // a manual choice should not be yanked away
//             });
//         });

//         this.go(0);

//         // Pause on hover/focus so a visitor reading the headline keeps it.
//         this.$el.addEventListener('mouseenter', () => this.stop());
//         this.$el.addEventListener('mouseleave', () => this.start());
//         this.$el.addEventListener('focusin', () => this.stop());

//         document.addEventListener('visibilitychange', () => {
//             document.hidden ? this.stop() : this.start();
//         });

//         this.start();
//     },

//     destroy() {
//         this.stop();
//     },

//     start() {
//         if (this.timer || prefersReducedMotion || this.slides.length < 2) {
//             return;
//         }

//         this.timer = setInterval(() => this.go(this.current + 1), this.interval);
//     },

//     stop() {
//         clearInterval(this.timer);
//         this.timer = null;
//     },

//     go(index) {
//         this.current = (index + this.slides.length) % this.slides.length;

//         this.slides.forEach((slide, i) => {
//             const active = i === this.current;
//             slide.classList.toggle('is-active', active);
//             slide.setAttribute('aria-hidden', active ? 'false' : 'true');

//             // Slides past the first are marked loading="lazy" and only start
//             // downloading once they are no longer hidden.
//             if (active) {
//                 slide.querySelectorAll('img[loading="lazy"]').forEach((img) => {
//                     img.loading = 'eager';
//                 });
//             }
//         });

//         this.dots.forEach((dot, i) => {
//             const active = i === this.current;
//             dot.classList.toggle('is-active', active);
//             dot.setAttribute('aria-current', active ? 'true' : 'false');
//         });
//     },
// }));

// /* ------------------------------------------------------------------ */
// /* Portfolio detail gallery (manual, arrow-key navigable)             */
// /* ------------------------------------------------------------------ */

// Alpine.data('gallery', () => ({
//     current: 0,

//     init() {
//         this.items = Array.from(this.$el.querySelectorAll('[data-gallery-item]'));
//         this.thumbs = Array.from(this.$el.querySelectorAll('[data-gallery-thumb]'));

//         if (this.items.length === 0) {
//             return;
//         }

//         this.thumbs.forEach((thumb, index) => {
//             thumb.addEventListener('click', () => this.go(index));
//         });

//         const prev = this.$el.querySelector('[data-gallery-prev]');
//         const next = this.$el.querySelector('[data-gallery-next]');

//         prev?.addEventListener('click', () => this.go(this.current - 1));
//         next?.addEventListener('click', () => this.go(this.current + 1));

//         this.$el.addEventListener('keydown', (event) => {
//             if (event.key === 'ArrowRight') {
//                 this.go(this.current + 1);
//             } else if (event.key === 'ArrowLeft') {
//                 this.go(this.current - 1);
//             }
//         });

//         this.go(0);
//     },

//     go(index) {
//         this.current = (index + this.items.length) % this.items.length;

//         this.items.forEach((item, i) => {
//             const active = i === this.current;
//             item.classList.toggle('is-active', active);
//             item.setAttribute('aria-hidden', active ? 'false' : 'true');
//         });

//         this.thumbs.forEach((thumb, i) => {
//             const active = i === this.current;
//             thumb.classList.toggle('is-active', active);
//             thumb.setAttribute('aria-current', active ? 'true' : 'false');
//         });
//     },
// }));

// /* ------------------------------------------------------------------ */
// /* Click-to-load YouTube embed                                        */
// /* ------------------------------------------------------------------ */

// /*
//  * Legacy rendered 15 live iframes on the homepage (10 testimonials, 3 design
//  * reviews, 1 location, 1 "mengapa"), none of them lazy. Each embed is its own
//  * browsing context with its own JS runtime, so the page arrived with 15 of them
//  * competing for the main thread and for the ~6-connections-per-host budget.
//  *
//  * Only the thumbnail loads up front; the iframe is created on click.
//  */
// Alpine.data('ytFacade', () => ({
//     loaded: false,

//     init() {
//         this.videoId = this.$el.dataset.videoId || '';

//         if (!this.videoId) {
//             return;
//         }

//         const activate = (event) => {
//             if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
//                 return;
//             }

//             event.preventDefault();
//             this.load();
//         };

//         this.$el.addEventListener('click', activate);
//         this.$el.addEventListener('keydown', activate);
//     },

//     load() {
//         if (this.loaded) {
//             return;
//         }

//         const iframe = document.createElement('iframe');
//         iframe.src =
//             `https://www.youtube-nocookie.com/embed/${this.videoId}` +
//             '?autoplay=1&rel=0&modestbranding=1';
//         iframe.title = this.$el.dataset.videoTitle || 'YouTube';
//         iframe.className = 'absolute inset-0 h-full w-full';
//         iframe.allow =
//             'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
//         iframe.referrerPolicy = 'strict-origin-when-cross-origin';
//         iframe.allowFullscreen = true;
//         iframe.setAttribute('frameborder', '0');

//         this.$el.replaceChildren(iframe);
//         this.loaded = true;

//         this.$el.removeAttribute('role');
//         this.$el.removeAttribute('tabindex');
//         this.$el.removeAttribute('aria-label');
//         this.$el.classList.remove('cursor-pointer');
//     },
// }));

// /* ------------------------------------------------------------------ */
// /* FAQ: close the open <details> when clicking outside the list       */
// /* ------------------------------------------------------------------ */

// /*
//  * The accordion itself is native <details name="faq">, so "one open at a time"
//  * and keyboard support come from the browser and work with JS disabled. This
//  * component only adds legacy's extra behaviour: a click inside the section but
//  * outside an item closes everything. It also enforces the exclusive behaviour
//  * on browsers that do not yet support the name attribute on <details>.
//  */
// Alpine.data('faqAccordion', () => ({
//     init() {
//         this.items = Array.from(this.$el.querySelectorAll('details'));

//         const supportsExclusive = 'name' in document.createElement('details');

//         if (!supportsExclusive) {
//             this.items.forEach((item) => {
//                 item.addEventListener('toggle', () => {
//                     if (!item.open) {
//                         return;
//                     }

//                     this.items.forEach((other) => {
//                         if (other !== item) {
//                             other.open = false;
//                         }
//                     });
//                 });
//             });
//         }

//         document.addEventListener('click', (event) => {
//             if (this.$el.contains(event.target) && !event.target.closest('details')) {
//                 this.closeAll();
//             }
//         });
//     },

//     closeAll() {
//         this.items.forEach((item) => {
//             item.open = false;
//         });
//     },
// }));

// /* ------------------------------------------------------------------ */
// /* "Selengkapnya": reveal portfolio items past the visible limit       */
// /* ------------------------------------------------------------------ */

// Alpine.data('revealMore', () => ({
//     init() {
//         this.hidden = Array.from(this.$el.querySelectorAll('[data-reveal-item]'));
//         this.button = this.$el.querySelector('[data-reveal-button]');

//         if (!this.button || this.hidden.length === 0) {
//             return;
//         }

//         // Hidden until JS confirms the button can work, so no-JS visitors do
//         // not see a dead control.
//         this.button.hidden = false;

//         this.button.addEventListener('click', () => {
//             this.hidden.forEach((item) => item.classList.remove('is-hidden'));
//             this.button.hidden = true;
//         });
//     },
// }));

// window.Alpine = Alpine;
// Alpine.start();
