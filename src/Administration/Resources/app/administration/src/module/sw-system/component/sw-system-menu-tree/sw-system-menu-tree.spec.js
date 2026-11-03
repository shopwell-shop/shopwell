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
        label: 'Node',
        groupLabel: null,
        icon: null,
        type: 'menu',
        active: true,
        sysLocked: false,
        archived: false,
        pluginName: null,
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
            label: 'Catalog',
            type: 'folder',
            children: [
                createNode({ id: 'products', parentId: 'catalog', afterMenuItemId: null, label: 'Products' }),
                createNode({ id: 'categories', parentId: 'catalog', afterMenuItemId: 'products', label: 'Categories' }),
            ],
        }),
        createNode({ id: 'orders', afterMenuItemId: 'catalog', label: 'Orders' }),
    ];
}

function mockHeight(element, height = 40) {
    element.getBoundingClientRect = () => {
        return { top: 0, height, bottom: height, left: 0, right: 200, width: 200, x: 0, y: 0 };
    };
}

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-system-menu-tree', { sync: true }), {
        props: {
            items: createItems(),
            expandedIds: [],
            sortable: true,
            editable: true,
            deletable: true,
            ...props,
        },
        global: {
            mocks: {
                $t: (key) => key,
            },
            stubs: {
                'mt-icon': { template: '<span class="mt-icon-stub"></span>', props: ['name', 'size'] },
                'mt-button': {
                    template: '<button class="mt-button-stub" @click="$emit(\'click\')"><slot></slot></button>',
                    props: ['size', 'variant', 'square', 'title', 'disabled'],
                },
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

    it('renders the top level entries only while nothing is expanded', async () => {
        wrapper = await createWrapper();

        expect(wrapper.findAll('.sw-system-menu-tree__row')).toHaveLength(2);
        expect(rowByLabel(wrapper, 'Products')).toBeUndefined();
    });

    it('renders the children of an expanded entry', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(wrapper.findAll('.sw-system-menu-tree__row')).toHaveLength(4);
        expect(rowByLabel(wrapper, 'Products')).toBeDefined();
    });

    it('reports a toggle instead of changing its own state', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.sw-system-menu-tree__toggle').trigger('click');

        expect(wrapper.emitted('toggle')).toHaveLength(1);
        expect(wrapper.emitted('toggle')[0][0].id).toBe('catalog');
    });

    it('does not offer a toggle for an entry without children', async () => {
        wrapper = await createWrapper();

        expect(rowByLabel(wrapper, 'Orders').find('.sw-system-menu-tree__toggle').exists()).toBe(false);
    });

    it('shows the group of an entry next to its label', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        const row = wrapper.findAll('.sw-system-menu-tree__row')[1];
        expect(row.text()).toContain('Products');
    });

    it('marks an inactive entry', async () => {
        wrapper = await createWrapper({
            items: [createNode({ id: 'orders', label: 'Orders', active: false })],
        });

        expect(wrapper.find('.sw-system-menu-tree__row').classes()).toContain('is--hidden');
    });

    it('emits an edit intent from the row action', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').findAll('.sw-system-menu-tree__action')[0].trigger('click');

        expect(wrapper.emitted('edit')[0][0].id).toBe('orders');
    });

    it('emits a delete intent from the row action', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').findAll('.sw-system-menu-tree__action')[1].trigger('click');

        expect(wrapper.emitted('remove')[0][0].id).toBe('orders');
    });

    it('hides the row actions when the operator may not edit', async () => {
        wrapper = await createWrapper({ editable: false, deletable: false });

        expect(wrapper.find('.sw-system-menu-tree__action').exists()).toBe(false);
    });

    it('never offers to delete an entry of a core module', async () => {
        wrapper = await createWrapper({
            items: [createNode({ id: 'orders', label: 'Orders', sysLocked: true })],
        });

        expect(wrapper.findAll('.sw-system-menu-tree__action')).toHaveLength(1);
    });

    it('appends a dropped item to the last child of a folder', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        const products = rowByLabel(wrapper, 'Products');
        await products.trigger('dragstart');
        mockHeight(rowByLabel(wrapper, 'Catalog').element);
        await rowByLabel(wrapper, 'Catalog').trigger('dragover');
        await rowByLabel(wrapper, 'Catalog').trigger('drop');

        expect(wrapper.emitted('move')[0][0]).toEqual({
            id: 'products',
            parentId: 'catalog',
            afterMenuItemId: 'categories',
        });
    });

    it('places a dropped item after the row it was dropped on', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').trigger('dragstart');
        mockHeight(rowByLabel(wrapper, 'Catalog').element);

        // the lower quarter of a folder row means "after it", the middle means "into it"
        const target = rowByLabel(wrapper, 'Catalog');
        await target.trigger('dragover', { clientY: 39 });
        await target.trigger('drop', { clientY: 39 });

        expect(wrapper.emitted('move')[0][0]).toEqual({
            id: 'orders',
            parentId: null,
            afterMenuItemId: 'catalog',
        });
    });

    it('refuses to drop an entry into its own subtree', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        await rowByLabel(wrapper, 'Catalog').trigger('dragstart');
        await rowByLabel(wrapper, 'Products').trigger('dragover');
        await rowByLabel(wrapper, 'Products').trigger('drop');

        expect(wrapper.emitted('move')).toBeUndefined();
    });
});
