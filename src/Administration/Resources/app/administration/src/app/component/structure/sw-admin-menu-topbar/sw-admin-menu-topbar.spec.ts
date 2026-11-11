/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

const mockPause = jest.fn();
let resizeNavigation = () => {};
const observed: Element[] = [];

jest.mock('focus-trap', () => ({
    createFocusTrap: jest.fn(() => ({ activate: jest.fn(), deactivate: jest.fn(), pause: mockPause })),
}));

const entries = [
    {
        id: 'catalogue',
        label: 'Catalogue',
        color: '#ff9900',
        icon: 'regular-products',
        children: [
            { id: 'products', label: 'Products', path: 'product.index', children: [] },
            { id: 'categories', label: 'Categories', path: 'category.index', children: [] },
        ],
    },
    { id: 'orders', label: 'Orders', path: 'order.index', children: [] },
];

describe('administration topbar', () => {
    let wrapper: VueWrapper;
    const route = reactive({
        name: 'product.detail',
        path: '/product/42',
        fullPath: '/product/42',
        params: { id: '42' },
        query: {},
        matched: [],
        meta: { parentPath: 'product.index' },
    });

    beforeAll(() => {
        global.ResizeObserver = class {
            constructor(callback: ResizeObserverCallback) {
                resizeNavigation = () => callback([], this);
            }
            observe(element: Element) {
                observed.push(element);
            }
            unobserve() {}
            disconnect() {}
        };
    });

    async function createWrapper(mode = 'top', sidebar = '<div>Sidebar</div>'): Promise<VueWrapper> {
        return mount(await wrapTestComponent('sw-admin-menu-topbar', { sync: true }), {
            attachTo: document.body,
            props: {
                enabled: true,
                mode,
                entries: sidebar === '<div>Sidebar</div>' ? entries : [{ ...entries[0], customPanel: true }, entries[1]],
                hasSidebar: true,
            },
            slots: { brand: '<span>Brand</span>', user: '<button>Account</button>', sidebar, 'entry-panel': sidebar },
            global: {
                provide: { acl: { can: () => true, hasActiveSettingModules: () => true } },
                mocks: {
                    $route: route,
                    $router: {
                        getRoutes: () => [],
                        resolve: (target: object) => ({ ...target, meta: { parentPath: 'product.index' } }),
                    },
                },
                stubs: {
                    'router-link': { name: 'RouterLinkStub', props: ['to'], template: '<a href="#"><slot /></a>' },
                    'sw-admin-menu-item': await wrapTestComponent('sw-admin-menu-item', { sync: true }),
                    'mt-floating-ui': {
                        name: 'MtFloatingUi',
                        props: ['isOpened', 'anchorElement'],
                        template: '<div v-if="isOpened"><slot /></div>',
                    },
                },
            },
        });
    }

    afterEach(() => {
        wrapper?.unmount();
        useModuleIconColors().enabled.value = false;
    });

    it('recalculates overflow after asynchronous menu items acquire their widths', async () => {
        wrapper = await createWrapper();
        const navigation = wrapper.get('.sw-admin-menu-topbar__navigation');
        const measurements = wrapper.get('.sw-admin-menu-topbar__measurements');
        expect(observed).toContain(measurements.element);
        Object.defineProperty(navigation.element, 'clientWidth', { value: 250 });
        measurements.findAll('li').forEach((item) => {
            jest.spyOn(item.element, 'getBoundingClientRect').mockReturnValue({ width: 150 } as DOMRect);
        });
        jest.spyOn(wrapper.get('.sw-admin-menu-topbar__more').element, 'getBoundingClientRect').mockReturnValue({
            width: 60,
        } as DOMRect);
        resizeNavigation();
        await flushPromises();
        expect(wrapper.find('[data-navigation-key="catalogue"]').exists()).toBe(true);
        expect(wrapper.find('[data-navigation-key="orders"]').exists()).toBe(false);
    });

    it('anchors an overflow submenu to the persistent More trigger and returns focus there', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ visibleCount: 0 });
        const more = wrapper.get('.sw-admin-menu-topbar__more');
        await more.trigger('click');
        await wrapper.get('.sw-admin-menu-topbar__more-panel .navigation-list-item__catalogue button').trigger('click');
        const panel = wrapper.findAllComponents({ name: 'MtFloatingUi' }).at(-1);
        expect(panel?.props('anchorElement')).toBe(more.element);
        expect(wrapper.get('.sw-admin-menu-topbar__more-panel').attributes('hidden')).toBeDefined();
        await wrapper.get('.sw-admin-menu-topbar__panel').trigger('keydown', { key: 'Escape' });
        expect(document.activeElement).toBe(more.element);
    });

    it('mounts custom panel owners before opening them and does not duplicate them in mixed layout', async () => {
        wrapper = await createWrapper('top', '<input aria-label="Channel owner">');
        expect(wrapper.findAll('input[aria-label="Channel owner"]')).toHaveLength(1);
        await wrapper.setProps({ mode: 'mixed' });
        expect(wrapper.findAll('input[aria-label="Channel owner"]')).toHaveLength(1);
    });

    it('opens a top-level folder as a dropdown without navigating', async () => {
        wrapper = await createWrapper();
        await wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').trigger('click');
        expect(wrapper.find('.sw-admin-menu-topbar__panel').exists()).toBe(true);
        expect(wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').text()).toBe('Catalogue');
        expect(wrapper.find('[data-navigation-key="products"]').exists()).toBe(false);
        expect(wrapper.find('.sw-admin-menu-topbar__panel').text()).toContain('Products');
        expect(wrapper.find('.sw-admin-menu-topbar__panel').text()).toContain('Categories');
        expect(wrapper.find('.sw-admin-menu-topbar__panel').text()).not.toContain('Catalogue');
        expect(
            wrapper
                .find('.sw-admin-menu-topbar__panel')
                .findAll('a')
                .map((link) => link.text()),
        ).toEqual([
            'Products',
            'Categories',
        ]);
        await wrapper.find('.sw-admin-menu-topbar__panel').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('.sw-admin-menu-topbar__panel').attributes('hidden')).toBeDefined();
    });

    it.each(['top', 'mixed'])('uses sidebar icon colors and active icon variants in %s mode', async (mode) => {
        wrapper = await createWrapper(mode);
        const catalogue = wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link');
        const icon = catalogue.find('.mt-icon');
        expect(catalogue.classes()).toContain('router-link-active');
        expect(icon.classes()).toContain('icon--solid-products');
        expect(catalogue.element.closest('li')?.classList.contains('is--module-colored')).toBe(false);
        useModuleIconColors().enabled.value = true;
        await flushPromises();
        expect(catalogue.element.closest('li')?.classList.contains('is--module-colored')).toBe(true);
        expect(icon.attributes('style')).toContain('color: rgb(255, 153, 0)');
        useModuleIconColors().enabled.value = false;
        await flushPromises();
        expect(catalogue.element.closest('li')?.classList.contains('is--module-colored')).toBe(false);
        expect(icon.attributes('style') ?? '').not.toContain('color');
    });

    it('preserves nested pages without duplicating their top-level folder', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({
            entries: [
                {
                    id: 'content',
                    label: 'Content',
                    children: [
                        {
                            id: 'pages',
                            label: 'Pages',
                            children: [{ id: 'drafts', path: 'draft.index', children: [] }],
                        },
                    ],
                },
            ],
        });
        await wrapper.find('[data-navigation-key="content"] .sw-admin-menu__navigation-link').trigger('click');
        const panel = wrapper.find('.sw-admin-menu-topbar__panel');
        expect(panel.find('.navigation-list-item__pages').exists()).toBe(true);
        expect(panel.find('.navigation-list-item__drafts').exists()).toBe(true);
        expect(panel.find('.navigation-list-item__content').exists()).toBe(false);
    });

    it('keeps the owning module color on the dropdown when the duplicate root is removed', async () => {
        wrapper = await createWrapper();
        await wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').trigger('click');
        const panel = wrapper.find('.sw-admin-menu-topbar__panel');
        expect(panel.classes()).not.toContain('is--module-colored');
        useModuleIconColors().enabled.value = true;
        await flushPromises();
        expect(panel.classes()).toContain('is--module-colored');
        expect(panel.attributes('style')).toContain('--sw-admin-menu-module-color: #ff9900');
        useModuleIconColors().enabled.value = false;
        await flushPromises();
        expect(panel.attributes('style')).toBeUndefined();
    });

    it('resolves the owning module of a detail route and remembers it in mixed mode', async () => {
        wrapper = await createWrapper('mixed');
        const catalogue = wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link');
        expect(catalogue.classes()).toContain('router-link-active');
        expect(catalogue.attributes('aria-current')).toBe('page');
        expect(wrapper.findAllComponents({ name: 'RouterLinkStub' })[0].props('to')).toEqual({
            name: 'product.detail',
            params: { id: '42' },
            query: {},
        });
    });

    it('keeps the active overflow module indicated on More', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ visibleCount: 0 });
        expect(wrapper.find('.sw-admin-menu-topbar__more').classes()).toContain('is--active');
        await wrapper.find('.sw-admin-menu-topbar__more').trigger('click');
        expect(wrapper.find('.sw-admin-menu-topbar__more-panel').text()).toContain('Catalogue');
        expect(wrapper.find('.sw-admin-menu-topbar__more-panel').text()).not.toContain('Sidebar');
    });

    it('restores the original sidebar slots on small screens', async () => {
        wrapper = await createWrapper('mixed');
        await wrapper.setProps({ enabled: false });
        expect(wrapper.find('.sw-admin-menu-topbar').exists()).toBe(false);
        expect(wrapper.text()).toContain('Brand');
        expect(wrapper.text()).toContain('Sidebar');
        expect(wrapper.text()).toContain('Account');
    });

    it('moves focus across top-level entries with the arrow keys', async () => {
        wrapper = await createWrapper();
        const catalogue = wrapper.find<HTMLButtonElement>(
            '[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link',
        );
        catalogue.element.focus();
        await catalogue.trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement).toBe(
            wrapper.find('[data-navigation-key="orders"] .sw-admin-menu__navigation-link').element,
        );
    });

    it('exposes external-only folders in mixed overflow through the existing menu subtree', async () => {
        wrapper = await createWrapper('mixed');
        await wrapper.setProps({
            entries: [
                {
                    id: 'docs',
                    label: 'Docs',
                    children: [
                        { id: 'help', label: 'Help', link: 'https://example.com', children: [] },
                    ],
                },
            ],
        });
        await wrapper.setData({ visibleCount: 0 });
        await wrapper.find('.sw-admin-menu-topbar__more').trigger('click');
        expect(wrapper.find('.sw-admin-menu-topbar__more-panel .navigation-list-item__docs button').text()).toBe('Docs');
    });

    it('does not handle a navigation key already consumed by the original sidebar', async () => {
        wrapper = await createWrapper();
        await wrapper.find('.sw-admin-menu-topbar__more').trigger('click');
        const event = new KeyboardEvent('keydown', { key: 'ArrowDown', cancelable: true, bubbles: true });
        event.preventDefault();
        const focus = jest.spyOn(HTMLElement.prototype, 'focus');
        wrapper.find('.sw-admin-menu-topbar__more-panel').element.dispatchEvent(event);
        expect(focus).not.toHaveBeenCalled();
        focus.mockRestore();
    });

    it('keeps extension state mounted after closing More', async () => {
        wrapper = await createWrapper('top', '<input aria-label="Extension draft">');
        await wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').trigger('click');
        const draft = wrapper.find<HTMLInputElement>('input[aria-label="Extension draft"]');
        await draft.setValue('modal state');
        await wrapper.find('.sw-admin-menu-topbar__panel').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('input[aria-label="Extension draft"]').element).toBe(draft.element);
        expect(draft.element.value).toBe('modal state');
    });

    it('lets a teleported extension dropdown own focus and closes More without destroying its owner', async () => {
        wrapper = await createWrapper('top', '<button aria-haspopup="menu" data-state="closed">Extension</button>');
        await wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').trigger('click');
        await flushPromises();
        const trigger = wrapper.find('[aria-haspopup="menu"]');
        trigger.element.setAttribute('data-state', 'open');
        await flushPromises();
        expect(mockPause).toHaveBeenCalled();
        const floating = wrapper.findAllComponents({ name: 'MtFloatingUi' }).at(-1) as VueWrapper<{
            $emit: (event: string) => void;
        }>;
        floating.vm.$emit('close');
        expect(
            wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').attributes('aria-expanded'),
        ).toBe('true');
        trigger.element.setAttribute('data-state', 'closed');
        await flushPromises();
        expect(
            wrapper.find('[data-navigation-key="catalogue"] .sw-admin-menu__navigation-link').attributes('aria-expanded'),
        ).toBe('false');
        expect(wrapper.find('[aria-haspopup="menu"]').exists()).toBe(true);
    });
});
