/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import type { RouteLocationRaw } from 'vue-router';
import { createFocusTrap, type FocusTrap } from 'focus-trap';
import { getActiveRouteNames, isEntryOnActiveRoute } from '../sw-admin-menu-item/menu-item-active.helper';
import { firstNavigationTarget, fitNavigationEntries, type LayoutNavigationEntry } from './navigation-layout.helper';
import template from './sw-admin-menu-topbar.html.twig';
import './sw-admin-menu-topbar.scss';

type ActiveRoute = Parameters<typeof getActiveRouteNames>[0];
type ActiveRouter = Parameters<typeof getActiveRouteNames>[1];

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['acl'],

    emits: ['toolbar-target-change'],

    props: {
        enabled: { type: Boolean, default: false },
        mode: { type: String, default: 'sidebar' },
        entries: { type: Array as PropType<LayoutNavigationEntry[]>, default: () => [] },
        hasSidebar: { type: Boolean, default: false },
    },

    data() {
        return {
            visibleCount: Number.MAX_SAFE_INTEGER,
            openKey: null as string | null,
            panelKey: null as string | null,
            anchor: null as HTMLElement | null,
            observer: null as ResizeObserver | null,
            focusTrap: null as FocusTrap | null,
            dropdownObserver: null as MutationObserver | null,
            dropdownTrigger: null as HTMLElement | null,
            moreAnchor: null as HTMLElement | null,
            lastVisited: {} as Record<string, RouteLocationRaw>,
        };
    },

    computed: {
        customPanelEntries(): LayoutNavigationEntry[] {
            return this.mode === 'top' ? this.entries.filter((entry) => entry.customPanel) : [];
        },
        activeEntry(): LayoutNavigationEntry | undefined {
            return this.entries.find((entry) => this.isActive(entry));
        },
        visibleEntries(): LayoutNavigationEntry[] {
            return this.entries.slice(0, this.visibleCount);
        },
        overflowEntries(): LayoutNavigationEntry[] {
            return this.entries.slice(this.visibleCount);
        },
        activePanelEntry(): LayoutNavigationEntry | undefined {
            return this.entries.find((entry) => this.key(entry) === this.panelKey);
        },
        panelModuleColor(): string | undefined {
            return useModuleIconColors().enabled.value ? (this.activePanelEntry?.color ?? undefined) : undefined;
        },
        panelModuleColorStyle(): Record<string, string> | undefined {
            return this.panelModuleColor ? { '--sw-admin-menu-module-color': this.panelModuleColor } : undefined;
        },
        hasActiveOverflow(): boolean {
            return this.overflowEntries.some((entry) => this.isActive(entry));
        },
        userId(): string | undefined {
            return Shopwell.Store.get('session').currentUser?.id;
        },
    },

    watch: {
        entries: {
            handler() {
                this.closePanel(false);
                this.rememberRoute();
                void this.$nextTick(() => this.measureEntries());
            },
            immediate: true,
        },
        '$route.fullPath'() {
            this.closePanel(false);
            this.rememberRoute();
        },
        userId() {
            this.lastVisited = {};
            this.closePanel(false);
        },
        enabled() {
            this.closePanel(false);
            void this.$nextTick(() => this.observeNavigation());
        },
        mode() {
            this.closePanel(false);
            void this.$nextTick(() => this.measureEntries());
        },
        openKey(key: string | null) {
            if (!key) return;
            void this.$nextTick(() => {
                const panel = (key === '__more' ? this.$refs.morePanel : this.$refs.panel) as HTMLElement | undefined;
                if (!panel || this.openKey !== key) return;
                this.focusTrap = createFocusTrap(panel, {
                    fallbackFocus: panel,
                    escapeDeactivates: true,
                    clickOutsideDeactivates: false,
                    allowOutsideClick: true,
                    onDeactivate: () => {
                        this.closePanel(false);
                    },
                });
                this.focusTrap.activate();
                this.dropdownObserver = new MutationObserver(() => this.syncDropdownFocus());
                this.dropdownObserver.observe(panel, {
                    subtree: true,
                    childList: true,
                    attributes: true,
                    attributeFilter: ['data-state'],
                });
            });
        },
    },

    mounted() {
        this.observeNavigation();
    },

    beforeUnmount() {
        this.observer?.disconnect();
        this.closePanel(false);
        this.$emit('toolbar-target-change', null);
    },

    methods: {
        onSubmenuToggle({ entry, target }: { entry: LayoutNavigationEntry; target: HTMLElement }): void {
            this.togglePanel(this.key(entry), target);
        },
        key(entry: LayoutNavigationEntry): string {
            return entry.id ?? entry.path ?? entry.link ?? '';
        },
        opensPanel(entry: LayoutNavigationEntry): boolean {
            return this.mode === 'top'
                ? !!entry.customPanel || entry.children.length > 0
                : entry.children.length > 0 && firstNavigationTarget(entry) === null;
        },
        isActive(entry: LayoutNavigationEntry): boolean {
            const route = this.$route as ActiveRoute;
            return isEntryOnActiveRoute(entry, route, getActiveRouteNames(route, this.$router as ActiveRouter));
        },
        target(entry: LayoutNavigationEntry): RouteLocationRaw {
            const previous = this.lastVisited[this.key(entry)];
            if (previous) {
                const resolved = this.$router.resolve(previous);
                const activeNames = getActiveRouteNames(resolved as ActiveRoute, this.$router as ActiveRouter);
                const privilege = resolved.meta.privilege;
                if (
                    isEntryOnActiveRoute(entry, resolved as ActiveRoute, activeNames) &&
                    (typeof privilege !== 'string' || this.acl.can(privilege))
                ) {
                    return previous;
                }
            }
            return firstNavigationTarget(entry) ?? entry.link ?? this.$route.fullPath;
        },
        rememberRoute(): void {
            if (!this.activeEntry || !this.$route.name) return;
            this.lastVisited[this.key(this.activeEntry)] = {
                name: this.$route.name,
                params: { ...this.$route.params },
                query: { ...this.$route.query },
            };
        },
        togglePanel(key: string, target: HTMLElement): void {
            if (this.openKey === key) {
                this.closePanel();
                return;
            }
            this.closePanel(false);
            if (key !== '__more') {
                this.anchor = target.closest('.sw-admin-menu-topbar__more-panel') ? this.moreAnchor : target;
                this.panelKey = key;
            }
            this.openKey = key;
        },
        closePanel(returnFocus = true): void {
            this.dropdownObserver?.disconnect();
            this.dropdownObserver = null;
            this.dropdownTrigger = null;
            const trigger = this.openKey === '__more' ? this.moreAnchor : this.anchor;
            this.focusTrap?.deactivate({ returnFocus: false, onDeactivate: () => {} });
            if (returnFocus) trigger?.focus();
            this.focusTrap = null;
            this.openKey = null;
        },
        syncDropdownFocus(): void {
            const panel = (this.openKey === '__more' ? this.$refs.morePanel : this.$refs.panel) as HTMLElement | undefined;
            const trigger = panel?.querySelector<HTMLElement>('[aria-haspopup="menu"][data-state="open"]');
            if (trigger && !this.dropdownTrigger) {
                this.dropdownTrigger = trigger;
                this.focusTrap?.pause();
            } else if (!trigger && this.dropdownTrigger) {
                // A nested action may open a modal. Release the popup's focus ownership without unmounting its slot.
                this.closePanel(false);
            }
        },
        onOutsideClose(): void {
            if (this.openKey && !this.dropdownTrigger) this.closePanel(false);
        },
        observeNavigation(): void {
            this.observer?.disconnect();
            const nav = this.$refs.navigation as HTMLElement | undefined;
            this.$emit('toolbar-target-change', this.enabled ? this.$refs.toolbar : null);
            if (!this.enabled || !nav) return;
            this.moreAnchor = this.$refs.more as HTMLElement;
            this.observer = new ResizeObserver(() => this.measureEntries());
            this.observer.observe(nav);
            const measurements = this.$refs.measurements as HTMLElement | undefined;
            if (measurements) this.observer.observe(measurements);
            this.measureEntries();
        },
        measureEntries(): void {
            const nav = this.$refs.navigation as HTMLElement | undefined;
            const measurements = this.$refs.measurements as HTMLElement | undefined;
            const more = this.$refs.more as HTMLElement | undefined;
            if (!nav || !measurements || !more || nav.clientWidth === 0) return;
            const widths = Array.from(measurements.children).map((element) => element.getBoundingClientRect().width);
            const count = fitNavigationEntries(widths, nav.clientWidth, more.getBoundingClientRect().width, 8);
            if (count !== this.visibleCount) {
                this.visibleCount = count;
                this.closePanel(false);
            }
        },
        onNavigationKeydown(event: KeyboardEvent): void {
            const nav = this.$refs.navigation as HTMLElement;
            const links = Array.from(
                nav.querySelectorAll<HTMLElement>('[data-navigation-key] .sw-admin-menu__navigation-link'),
            );
            if (this.overflowEntries.length && this.moreAnchor) links.push(this.moreAnchor);
            const index = links.indexOf(document.activeElement as HTMLElement);
            const next = {
                ArrowRight: (index + 1) % links.length,
                ArrowLeft: (index - 1 + links.length) % links.length,
                Home: 0,
                End: links.length - 1,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            links[next]?.focus();
        },
        onPanelKeydown(event: KeyboardEvent): void {
            if (event.defaultPrevented) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                this.closePanel();
                return;
            }
            const panel = event.currentTarget as HTMLElement;
            const links = Array.from(panel.querySelectorAll<HTMLElement>('a, button')).filter(
                (link) => !link.closest('[hidden]'),
            );
            const index = links.indexOf(document.activeElement as HTMLElement);
            const next = {
                ArrowDown: (index + 1) % links.length,
                ArrowUp: (index - 1 + links.length) % links.length,
                Home: 0,
                End: links.length - 1,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            links[next]?.focus();
        },
    },
});
