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
 * └─ categories (menu, inactive)
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
                createNode({
                    id: 'categories',
                    parentId: 'catalog',
                    afterMenuItemId: 'products',
                    label: 'Categories',
                    active: false,
                }),
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
            selectedId: null,
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
                    props: [
                        'size',
                        'variant',
                        'square',
                        'title',
                        'disabled',
                    ],
                },
                'sw-context-button': { template: '<span class="sw-context-button-stub"><slot></slot></span>' },
                'sw-context-menu-item': {
                    template:
                        '<button class="sw-context-menu-item-stub" :data-variant="variant" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                    props: ['disabled', 'variant'],
                },
                'sw-context-menu-divider': { template: '<hr class="sw-context-menu-divider-stub" />' },
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

    it('selects an entry by its label', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').find('.sw-system-menu-tree__label').trigger('click');

        expect(wrapper.emitted('select')[0][0].id).toBe('orders');
    });

    it('selects an entry with the keyboard', async () => {
        wrapper = await createWrapper();

        const row = rowByLabel(wrapper, 'Orders');

        expect(row.attributes('tabindex')).toBe('0');

        await row.trigger('keydown.enter');
        await row.trigger('keydown.space');

        expect(wrapper.emitted('select')).toHaveLength(2);
    });

    it('marks the selected entry', async () => {
        wrapper = await createWrapper({ selectedId: 'orders' });

        expect(rowByLabel(wrapper, 'Orders').classes()).toContain('is--selected');
        expect(rowByLabel(wrapper, 'Catalog').classes()).not.toContain('is--selected');
    });

    it('shows the visibility of an entry as a status dot', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(rowByLabel(wrapper, 'Orders').find('.sw-system-menu-tree__status').classes()).toContain('is--active');
        expect(rowByLabel(wrapper, 'Categories').find('.sw-system-menu-tree__status').classes()).toContain('is--inactive');
    });

    it('marks an inactive entry', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(rowByLabel(wrapper, 'Categories').classes()).toContain('is--hidden');
    });

    it('keeps the type wording and the source out of the row', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        const row = rowByLabel(wrapper, 'Products');

        expect(row.text()).not.toContain('sw-system.menu.types');
        expect(row.find('.sw-system-menu-tree__badge--group').exists()).toBe(false);
    });

    it('emits an edit intent from the row action', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').findAll('.sw-context-menu-item-stub')[0].trigger('click');

        expect(wrapper.emitted('edit')[0][0].id).toBe('orders');
    });

    it('offers the official five actions for a folder and four for a menu entry', async () => {
        wrapper = await createWrapper();

        const folderActions = rowByLabel(wrapper, 'Catalog').findAll('.sw-context-menu-item-stub');
        const menuActions = rowByLabel(wrapper, 'Orders').findAll('.sw-context-menu-item-stub');

        expect(folderActions).toHaveLength(5);
        expect(menuActions).toHaveLength(4);

        await folderActions[2].trigger('click');
        expect(wrapper.emitted('create-below')[0][0].id).toBe('catalog');

        await menuActions[1].trigger('click');
        expect(wrapper.emitted('create-above')[0][0].id).toBe('orders');
    });

    it('offers adding a child only for folders', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        await rowByLabel(wrapper, 'Catalog').findAll('.sw-context-menu-item-stub')[3].trigger('click');

        expect(wrapper.emitted('add-child')[0][0].id).toBe('catalog');
    });

    it('emits a delete intent from the danger action', async () => {
        wrapper = await createWrapper();

        const deleteAction = rowByLabel(wrapper, 'Orders').findAll('.sw-context-menu-item-stub')[3];

        expect(deleteAction.attributes('data-variant')).toBe('danger');

        await deleteAction.trigger('click');

        expect(wrapper.emitted('remove')[0][0].id).toBe('orders');
    });

    it('hides the row actions when the operator may not edit', async () => {
        wrapper = await createWrapper({ editable: false, deletable: false });

        expect(wrapper.find('.sw-system-menu-tree__context').exists()).toBe(false);
    });

    it('never offers to delete an entry of a core module', async () => {
        wrapper = await createWrapper({
            items: [createNode({ id: 'orders', label: 'Orders', sysLocked: true })],
        });

        const actions = rowByLabel(wrapper, 'Orders').findAll('.sw-context-menu-item-stub');

        expect(actions).toHaveLength(3);
        expect(actions.some((action) => action.text() === 'global.default.delete')).toBe(false);
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
