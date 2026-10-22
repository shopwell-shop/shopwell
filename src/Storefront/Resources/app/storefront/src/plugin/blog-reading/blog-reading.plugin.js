import Plugin from 'src/plugin-system/plugin.class';

/**
 * The two reading aids of an article: a progress bar at the top of the viewport and a table of
 * contents that marks the section the reader is currently in.
 *
 * Both are enhancements. The table of contents is rendered and linked on the server, so a visitor
 * without javascript still navigates with it - the script only adds the highlight. The progress bar is
 * purely decorative and reports itself as such with `aria-hidden` in the markup.
 *
 * Two performance decisions matter here, because this runs on every scroll event of the longest pages
 * of the shop:
 *
 * - the scroll listener is passive and does nothing but record the position and ask for a frame. The
 *   measuring and the writing happen once per animation frame, never per event;
 * - the section detection uses an IntersectionObserver instead of a scroll handler with twelve
 *   `getBoundingClientRect` calls in it.
 *
 * `prefers-reduced-motion` removes the transition of the bar and the smooth scroll of the links. It
 * does not remove the features: a reader who asked for less motion still wants to know how far they
 * are.
 *
 * @sw-package discovery
 */
export default class BlogReadingPlugin extends Plugin {
    static options = {
        progressSelector: '[data-blog-reading-progress]',
        tocLinkSelector: '[data-blog-toc-link]',
        sectionSelector: '[id^="section-"]',
        activeClass: 'is-active',
        reducedClass: 'blog-detail--reduced-motion',
    };

    init() {
        this._progressElement = this.el.querySelector(this.options.progressSelector);
        this._tocLinks = Array.from(this.el.querySelectorAll(this.options.tocLinkSelector));

        this._reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this._ticking = false;
        this._documentHeight = 0;

        if (this._reducedMotion) {
            this.el.classList.add(this.options.reducedClass);
        }

        this._registerScrolling();
        this._registerSections();
        this._measure();
    }

    _registerScrolling() {
        if (!this._progressElement) {
            return;
        }

        window.addEventListener('scroll', this._onScroll.bind(this), { passive: true });
        window.addEventListener('resize', this._measure.bind(this), { passive: true });
    }

    /**
     * Records that a frame is needed and returns. A scroll event may fire a hundred times a second;
     * reading the layout in the handler is what makes a page feel heavy.
     */
    _onScroll() {
        if (this._ticking) {
            return;
        }

        this._ticking = true;

        window.requestAnimationFrame(() => {
            this._ticking = false;
            this._paint();
        });
    }

    /**
     * Measures once per resize instead of once per frame.
     */
    _measure() {
        const rect = this.el.getBoundingClientRect();
        const scrolled = window.scrollY || window.pageYOffset || 0;

        this._start = rect.top + scrolled;
        this._height = this.el.offsetHeight;

        this._paint();
    }

    _paint() {
        if (!this._progressElement) {
            return;
        }

        const scrolled = window.scrollY || window.pageYOffset || 0;
        const viewport = window.innerHeight;
        const travelled = scrolled + viewport - this._start;
        const ratio = this._height > 0 ? travelled / this._height : 0;

        this._progressElement.style.transform = `scaleX(${Math.min(1, Math.max(0, ratio))})`;
    }

    /**
     * Marks the last heading that entered the upper half of the viewport. The root margin keeps a
     * heading from flickering between active and inactive while it sits exactly on the edge.
     */
    _registerSections() {
        if (this._tocLinks.length === 0 || typeof window.IntersectionObserver === 'undefined') {
            return;
        }

        const sections = Array.from(this.el.querySelectorAll(this.options.sectionSelector));

        if (sections.length === 0) {
            return;
        }

        this._observer = new window.IntersectionObserver(this._onSectionsChanged.bind(this), {
            rootMargin: '-20% 0px -70% 0px',
            threshold: 0,
        });

        sections.forEach((section) => this._observer.observe(section));
    }

    _onSectionsChanged(entries) {
        entries
            .filter((entry) => entry.isIntersecting)
            .forEach((entry) => this._activate(entry.target.id));
    }

    _activate(id) {
        this._tocLinks.forEach((link) => {
            const active = link.getAttribute('data-blog-toc-link') === id;
            link.classList.toggle(this.options.activeClass, active);

            if (active) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    destroy() {
        super.destroy();

        window.removeEventListener('scroll', this._onScroll);
        window.removeEventListener('resize', this._measure);

        if (this._observer) {
            this._observer.disconnect();
        }
    }
}
