/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

function createNode(overrides) {
    return {
        id: 'node',
        parentId: null,
        afterMenuItemId: null,
        internalKey: null,
        title: 'Node',
        icon: null,
        color: null,
        type: 'menu',
        linkTarget: null,
        privilege: null,
        active: true,
        sysLocked: false,
        archived: false,
        configOverridden: false,
        pluginId: null,
        pluginName: null,
        roleIds: [],
        children: [],
        ...overrides,
    };
}

/**
 * catalog (folder)
 * ├─ products (menu)
 * └─ categories (menu)
 * orders (menu)
 */
function createItems() {
    return [
        createNode({
            id: 'catalog',
            title: 'Catalog',
            type: 'folder',
            children: [
                createNode({ id: 'products', parentId: 'catalog', afterMenuItemId: null, title: 'Products' }),
                createNode({ id: 'categories', parentId: 'catalog', afterMenuItemId: 'products', title: 'Categories' }),
            ],
        }),
        createNode({ id: 'orders', afterMenuItemId: 'catalog', title: 'Orders' }),
    ];
}

function mockHeight(element, height = 40) {
    element.getBoundingClientRect = () => {
        return { top: 0, height, bottom: height, left: 0, right: 200, width: 200, x: 0, y: 0 };
    };
}

async function createWrapper(props = {}, mocks = {}) {
    return mount(await wrapTestComponent('sw-system-menu-tree', { sync: true }), {
        props: {
            items: createItems(),
            activeId: null,
            sortable: true,
            ...props,
        },
        global: {
            mocks: {
                $t: (key) => key,
                ...mocks,
            },
            stubs: {
                'mt-icon': { template: '<span class="mt-icon-stub"></span>', props: ['name', 'size'] },
            },
        },
    });
}

function rowByLabel(wrapper, label) {
    return wrapper.findAll('.sw-system-menu-tree__row').find((row) => row.text().includes(label));
}

describe('module/sw-system/component/sw-system-menu-tree', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }

        jest.restoreAllMocks();
    });

    it('renders a stored title as written when it is not a snippet key', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.rowLabel(createNode({ title: 'Mein Menü' }))).toBe('Mein Menü');
    });

    it('falls back to the internal key and then to the untitled snippet', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.rowLabel(createNode({ title: null, internalKey: 'sw-system' }))).toBe('sw-system');
        expect(wrapper.vm.rowLabel(createNode({ title: null, internalKey: null }))).toBe(
            'sw-system.menu.labelUntitled',
        );
    });

    it('resolves a stored snippet key through the translation', async () => {
        // `$te`/`$t` cannot be spied on the instance proxy (vue-i18n exposes them read-only),
        // so the case overrides them the same way the factory already overrides `$t`.
        wrapper = await createWrapper(
            {},
            {
                $te: () => true,
                $t: (key) => `translated:${key}`,
            },
        );

        expect(wrapper.vm.rowLabel(createNode({ title: 'sw-product.general.mainMenuItemOverview' }))).toBe(
            'translated:sw-product.general.mainMenuItemOverview',
        );
    });

    it('renders every level in chain order', async () => {
        wrapper = await createWrapper();

        const labels = wrapper
            .findAll('.sw-system-menu-tree__row')
            .map((row) => row.find('.sw-system-menu-tree__label').text());

        expect(labels).toEqual([
            'Catalog',
            'Products',
            'Categories',
            'Orders',
        ]);
    });

    it('hides the children of a collapsed folder', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Catalog').find('.sw-system-menu-tree__toggle').trigger('click');

        const labels = wrapper
            .findAll('.sw-system-menu-tree__row')
            .map((row) => row.find('.sw-system-menu-tree__label').text());

        expect(labels).toEqual(['Catalog', 'Orders']);
    });

    it('selects a row on click', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').trigger('click');

        expect(wrapper.emitted('select')).toEqual([['orders']]);
    });

    it('marks the selected row as active', async () => {
        wrapper = await createWrapper({ activeId: 'products' });

        expect(rowByLabel(wrapper, 'Products').classes()).toContain('is--active');
        expect(rowByLabel(wrapper, 'Orders').classes()).not.toContain('is--active');
    });

    it('moves a dragged item behind the row it was dropped on', async () => {
        wrapper = await createWrapper();

        const orders = rowByLabel(wrapper, 'Orders');
        const catalog = rowByLabel(wrapper, 'Catalog');

        await orders.trigger('dragstart');
        mockHeight(catalog.element);

        await catalog.trigger('dragover', { clientY: 35 });
        await catalog.trigger('drop', { clientY: 35 });

        expect(wrapper.emitted('move')).toEqual([
            [{ id: 'orders', parentId: null, afterMenuItemId: 'catalog' }],
        ]);
    });

    it('appends a dragged item when it is dropped into a folder', async () => {
        wrapper = await createWrapper();

        const orders = rowByLabel(wrapper, 'Orders');
        const catalog = rowByLabel(wrapper, 'Catalog');

        await orders.trigger('dragstart');
        mockHeight(catalog.element);

        await catalog.trigger('dragover', { clientY: 20 });
        await catalog.trigger('drop', { clientY: 20 });

        expect(wrapper.emitted('move')).toEqual([
            [{ id: 'orders', parentId: 'catalog', afterMenuItemId: 'categories' }],
        ]);
    });

    it('leads the level when a folder is dropped as the first entry', async () => {
        wrapper = await createWrapper();

        const orders = rowByLabel(wrapper, 'Orders');
        const products = rowByLabel(wrapper, 'Products');

        await orders.trigger('dragstart');
        mockHeight(products.element);

        await products.trigger('dragover', { clientY: 5 });
        await products.trigger('drop', { clientY: 5 });

        expect(wrapper.emitted('move')).toEqual([
            [{ id: 'orders', parentId: 'catalog', afterMenuItemId: null }],
        ]);
    });

    it('refuses a move below the dragged item itself', async () => {
        wrapper = await createWrapper();

        const catalog = rowByLabel(wrapper, 'Catalog');

        await catalog.trigger('dragstart');
        mockHeight(catalog.element);
        await catalog.trigger('drop', { clientY: 20 });

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('refuses a move below a child of the dragged item', async () => {
        wrapper = await createWrapper();

        const catalog = rowByLabel(wrapper, 'Catalog');
        const products = rowByLabel(wrapper, 'Products');

        await catalog.trigger('dragstart');
        mockHeight(products.element);
        await products.trigger('drop', { clientY: 20 });

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('does not start a drag when sorting is disabled', async () => {
        wrapper = await createWrapper({ sortable: false });

        const orders = rowByLabel(wrapper, 'Orders');
        const catalog = rowByLabel(wrapper, 'Catalog');

        await orders.trigger('dragstart');
        mockHeight(catalog.element);
        await catalog.trigger('drop', { clientY: 35 });

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('does not offer a menu item as a drop target for children', async () => {
        // only folders hold children: the middle of a menu row is a plain reorder instead
        wrapper = await createWrapper();

        const orders = rowByLabel(wrapper, 'Orders');
        const products = rowByLabel(wrapper, 'Products');

        await orders.trigger('dragstart');
        mockHeight(products.element);

        await products.trigger('drop', { clientY: 20 });

        expect(wrapper.emitted('move')).toEqual([
            [{ id: 'orders', parentId: 'catalog', afterMenuItemId: 'products' }],
        ]);
    });
});
