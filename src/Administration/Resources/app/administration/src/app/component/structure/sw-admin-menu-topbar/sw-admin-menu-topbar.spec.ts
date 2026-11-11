/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';

const mockPause = jest.fn();

jest.mock('focus-trap', () => ({
    createFocusTrap: jest.fn(() => ({ activate: jest.fn(), deactivate: jest.fn(), pause: mockPause })),
}));

const entries = [
    {
        id: 'catalogue',
        label: 'Catalogue',
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
            observe() {}
            unobserve() {}
            disconnect() {}
        };
    });

    async function createWrapper(mode = 'top', sidebar = '<div>Sidebar</div>'): Promise<VueWrapper> {
        return mount(await wrapTestComponent('sw-admin-menu-topbar', { sync: true }), {
            attachTo: document.body,
            props: { enabled: true, mode, entries, hasSidebar: true },
            slots: { brand: '<span>Brand</span>', user: '<button>Account</button>', sidebar },
            global: {
                mocks: {
                    $route: route,
                    $router: {
                        getRoutes: () => [],
                        resolve: (target: object) => ({ ...target, meta: { parentPath: 'product.index' } }),
                    },
                },
                stubs: {
                    'router-link': { name: 'RouterLinkStub', props: ['to'], template: '<a href="#"><slot /></a>' },
                    'sw-admin-menu-item': { name: 'sw-admin-menu-item', props: ['entry'], template: '<li />' },
                    'mt-floating-ui': {
                        name: 'MtFloatingUi',
                        props: ['isOpened'],
                        template: '<div v-if="isOpened"><slot /></div>',
                    },
                },
            },
        });
    }

    afterEach(() => {
        wrapper?.unmount();
    });

    it('opens a top-level folder as a dropdown without navigating', async () => {
        wrapper = await createWrapper();
        await wrapper.find('[data-navigation-key="catalogue"]').trigger('click');
        expect(wrapper.find('.sw-admin-menu-topbar__panel').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-admin-menu-item' }).props('entry')).toMatchObject({ id: 'catalogue' });
        await wrapper.find('.sw-admin-menu-topbar__panel').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('.sw-admin-menu-topbar__panel').exists()).toBe(false);
    });

    it('resolves the owning module of a detail route and remembers it in mixed mode', async () => {
        wrapper = await createWrapper('mixed');
        const catalogue = wrapper.find('[data-navigation-key="catalogue"]');
        expect(catalogue.classes()).toContain('is--active');
        expect(catalogue.attributes('aria-current')).toBe('true');
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
        expect(wrapper.find('.sw-admin-menu-topbar__more-panel').text()).toContain('Sidebar');
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
        const catalogue = wrapper.find<HTMLButtonElement>('[data-navigation-key="catalogue"]');
        catalogue.element.focus();
        await catalogue.trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement).toBe(wrapper.find('[data-navigation-key="orders"]').element);
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
        expect(wrapper.findComponent({ name: 'sw-admin-menu-item' }).props('entry')).toMatchObject({ id: 'docs' });
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
        await wrapper.find('.sw-admin-menu-topbar__more').trigger('click');
        const draft = wrapper.find<HTMLInputElement>('input[aria-label="Extension draft"]');
        await draft.setValue('modal state');
        await wrapper.find('.sw-admin-menu-topbar__more-panel').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('input[aria-label="Extension draft"]').element).toBe(draft.element);
        expect(draft.element.value).toBe('modal state');
    });

    it('lets a teleported extension dropdown own focus and closes More without destroying its owner', async () => {
        wrapper = await createWrapper('top', '<button aria-haspopup="menu" data-state="closed">Extension</button>');
        await wrapper.find('.sw-admin-menu-topbar__more').trigger('click');
        await flushPromises();
        const trigger = wrapper.find('[aria-haspopup="menu"]');
        trigger.element.setAttribute('data-state', 'open');
        await flushPromises();
        expect(mockPause).toHaveBeenCalled();
        const floating = wrapper.findComponent({ name: 'MtFloatingUi' }) as VueWrapper<{ $emit: (event: string) => void }>;
        floating.vm.$emit('close');
        expect(wrapper.find('.sw-admin-menu-topbar__more').attributes('aria-expanded')).toBe('true');
        trigger.element.setAttribute('data-state', 'closed');
        await flushPromises();
        expect(wrapper.find('.sw-admin-menu-topbar__more').attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[aria-haspopup="menu"]').exists()).toBe(true);
    });
});
