/**
 * @sw-package fundamentals@framework
 */
import {
    MtButton,
    MtDropdownMenuRoot,
    MtDropdownMenuTrigger,
    MtDropdownMenuPortal,
    MtActionMenu,
    MtActionMenuItem,
} from '@shopwell-ag/meteor-component-library';
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

async function createWrapper(props = {}, realActions = false) {
    return mount(await wrapTestComponent('sw-permissions-menu-tree', { sync: true }), {
        attachTo: realActions ? document.body : undefined,
        props: {
            items: createItems(),
            expandedIds: [],
            selectedId: null,
            sortable: true,
            editable: true,
            creatable: true,
            deletable: true,
            ...props,
        },
        global: {
            mocks: {
                $t: (key) => key,
            },
            stubs: {
                'mt-dropdown-menu-root': realActions
                    ? MtDropdownMenuRoot
                    : { template: '<span class="mt-dropdown-menu-root-stub"><slot /></span>' },
                'mt-dropdown-menu-trigger': realActions ? MtDropdownMenuTrigger : { template: '<span><slot /></span>' },
                'mt-dropdown-menu-portal': realActions ? MtDropdownMenuPortal : { template: '<span><slot /></span>' },
                'mt-action-menu': realActions ? MtActionMenu : { template: '<span><slot /></span>' },
                'mt-action-menu-item': realActions
                    ? MtActionMenuItem
                    : {
                          template:
                              '<button class="mt-action-menu-item-stub" :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
                          props: ['variant'],
                      },
                'mt-icon': { template: '<span class="mt-icon-stub"></span>', props: ['name', 'size'] },
                'mt-button': MtButton,
            },
        },
    });
}

function rowByLabel(wrapper, label) {
    return wrapper.findAll('.sw-permissions-menu-tree__row').find((row) => row.text().includes(label));
}

describe('module/sw-permissions/component/sw-permissions-menu-tree', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }

        jest.restoreAllMocks();
    });

    it('renders the top level entries only while nothing is expanded', async () => {
        wrapper = await createWrapper();

        expect(wrapper.findAll('.sw-permissions-menu-tree__row')).toHaveLength(2);
        expect(rowByLabel(wrapper, 'Products')).toBeUndefined();
    });

    it('renders the children of an expanded entry', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(wrapper.findAll('.sw-permissions-menu-tree__row')).toHaveLength(4);
        expect(rowByLabel(wrapper, 'Products')).toBeDefined();
    });

    it('reports a toggle instead of changing its own state', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.sw-permissions-menu-tree__toggle').trigger('click');

        expect(wrapper.emitted('toggle')).toHaveLength(1);
        expect(wrapper.emitted('toggle')[0][0].id).toBe('catalog');
    });

    it('does not offer a toggle for an entry without children', async () => {
        wrapper = await createWrapper();

        expect(rowByLabel(wrapper, 'Orders').find('.sw-permissions-menu-tree__toggle').exists()).toBe(false);
    });

    it('selects an entry by its label', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').find('.sw-permissions-menu-tree__label').trigger('click');

        expect(wrapper.emitted('select')[0][0].id).toBe('orders');
    });

    it('selects an entry with the keyboard', async () => {
        wrapper = await createWrapper();

        const row = rowByLabel(wrapper, 'Orders');

        expect(row.attributes('tabindex')).toBe('-1');

        await row.trigger('keydown', { key: 'Enter' });
        await row.trigger('keydown', { key: ' ' });

        expect(wrapper.emitted('select')).toHaveLength(2);
    });

    it('marks the selected entry', async () => {
        wrapper = await createWrapper({ selectedId: 'orders' });

        expect(rowByLabel(wrapper, 'Orders').classes()).toContain('is--selected');
        expect(rowByLabel(wrapper, 'Catalog').classes()).not.toContain('is--selected');
    });

    it('shows the visibility of an entry as a status dot', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(rowByLabel(wrapper, 'Orders').find('.sw-permissions-menu-tree__status').classes()).toContain('is--active');
        expect(rowByLabel(wrapper, 'Categories').find('.sw-permissions-menu-tree__status').classes()).toContain(
            'is--inactive',
        );
    });

    it('marks an inactive entry', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        expect(rowByLabel(wrapper, 'Categories').classes()).toContain('is--hidden');
    });

    it('keeps the type wording and the source out of the row', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        const row = rowByLabel(wrapper, 'Products');

        expect(row.text()).not.toContain('sw-permissions.menu.types');
        expect(row.find('.sw-permissions-menu-tree__badge--group').exists()).toBe(false);
    });

    it('opens the registered Meteor row menu and emits its action', async () => {
        wrapper = await createWrapper({}, true);
        await rowByLabel(wrapper, 'Catalog')
            .get('.sw-permissions-menu-tree__action-trigger')
            .trigger('keydown', { key: 'Enter' });
        await flushPromises();
        const action = [...document.querySelectorAll('[role="menuitem"]')].find((item) =>
            item.textContent.includes('sw-permissions.menu.addChild'),
        );
        expect(action).toBeDefined();
        action.click();
        await flushPromises();
        expect(wrapper.emitted('add-child')[0][0].id).toBe('catalog');
    });

    it('emits an edit intent from the row action', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').findAll('.mt-action-menu-item-stub')[0].trigger('click');

        expect(wrapper.emitted('edit')[0][0].id).toBe('orders');
    });

    it('offers the official five actions for a folder and four for a menu entry', async () => {
        wrapper = await createWrapper();

        const folderActions = rowByLabel(wrapper, 'Catalog').findAll('.mt-action-menu-item-stub');
        const menuActions = rowByLabel(wrapper, 'Orders').findAll('.mt-action-menu-item-stub');

        expect(folderActions).toHaveLength(5);
        expect(menuActions).toHaveLength(4);

        await folderActions[2].trigger('click');
        expect(wrapper.emitted('create-below')[0][0].id).toBe('catalog');

        await menuActions[1].trigger('click');
        expect(wrapper.emitted('create-above')[0][0].id).toBe('orders');
    });

    it('offers adding a child only for folders', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        await rowByLabel(wrapper, 'Catalog').findAll('.mt-action-menu-item-stub')[3].trigger('click');

        expect(wrapper.emitted('add-child')[0][0].id).toBe('catalog');
    });

    it('emits a delete intent from the danger action', async () => {
        wrapper = await createWrapper();

        const deleteAction = rowByLabel(wrapper, 'Orders').findAll('.mt-action-menu-item-stub')[3];

        expect(deleteAction.attributes('data-variant')).toBe('critical');

        await deleteAction.trigger('click');

        expect(wrapper.emitted('remove')[0][0].id).toBe('orders');
    });

    it('hides the row actions when the operator may not edit', async () => {
        wrapper = await createWrapper({ editable: false, creatable: false, deletable: false });

        expect(wrapper.find('.mt-dropdown-menu-root-stub').exists()).toBe(false);
    });

    it('never offers to delete an entry of a core module', async () => {
        wrapper = await createWrapper({
            items: [createNode({ id: 'orders', label: 'Orders', sysLocked: true })],
        });

        const actions = rowByLabel(wrapper, 'Orders').findAll('.mt-action-menu-item-stub');

        expect(actions).toHaveLength(3);
        expect(actions.some((action) => action.text() === 'global.default.delete')).toBe(false);
    });

    it('only starts a drag from the dedicated handle', async () => {
        wrapper = await createWrapper();
        const row = rowByLabel(wrapper, 'Orders');
        expect(row.attributes('draggable')).not.toBe('true');
        expect(row.get('.sw-permissions-menu-tree__drag-handle').attributes('draggable')).toBe('true');
    });

    it('moves keyboard focus through the visible tree', async () => {
        wrapper = await createWrapper();
        await rowByLabel(wrapper, 'Catalog').trigger('keydown', { key: 'ArrowDown' });
        expect(rowByLabel(wrapper, 'Orders').attributes('tabindex')).toBe('0');
        expect(rowByLabel(wrapper, 'Catalog').attributes('tabindex')).toBe('-1');
    });

    it('does not offer creation actions to editors without create permission', async () => {
        wrapper = await createWrapper({ creatable: false, deletable: false });
        expect(rowByLabel(wrapper, 'Catalog').findAll('.mt-action-menu-item-stub')).toHaveLength(1);
    });

    it('appends a dropped item to the last child of a folder', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        const products = rowByLabel(wrapper, 'Products');
        await products.get('.sw-permissions-menu-tree__drag-handle').trigger('dragstart');
        mockHeight(rowByLabel(wrapper, 'Catalog').element);
        await rowByLabel(wrapper, 'Catalog').trigger('dragover');
        await rowByLabel(wrapper, 'Catalog').trigger('drop');

        expect(wrapper.emitted('move')[0][0]).toEqual({
            id: 'products',
            parentId: 'catalog',
            afterMenuItemId: 'categories',
        });
    });

    it('ignores a drop at the existing position', async () => {
        wrapper = await createWrapper();

        await rowByLabel(wrapper, 'Orders').get('.sw-permissions-menu-tree__drag-handle').trigger('dragstart');
        mockHeight(rowByLabel(wrapper, 'Catalog').element);

        // the lower quarter of a folder row means "after it", the middle means "into it"
        const target = rowByLabel(wrapper, 'Catalog');
        await target.trigger('dragover', { clientY: 39 });
        await target.trigger('drop', { clientY: 39 });

        expect(wrapper.emitted('move')).toBeUndefined();
    });

    it('refuses to drop an entry into its own subtree', async () => {
        wrapper = await createWrapper({ expandedIds: ['catalog'] });

        await rowByLabel(wrapper, 'Catalog').get('.sw-permissions-menu-tree__drag-handle').trigger('dragstart');
        await rowByLabel(wrapper, 'Products').trigger('dragover');
        await rowByLabel(wrapper, 'Products').trigger('drop');

        expect(wrapper.emitted('move')).toBeUndefined();
    });
    it('allows reordering explicit settings groups with different legacy names', async () => {
        const wrapper = await createWrapper({
            items: [
                createNode({
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [
                        createNode({
                            id: 'general',
                            parentId: 'settings',
                            type: 'group',
                            groupName: 'general',
                            label: 'General',
                        }),
                        createNode({
                            id: 'customer',
                            parentId: 'settings',
                            afterMenuItemId: 'general',
                            type: 'group',
                            groupName: 'customer',
                            label: 'Customer',
                        }),
                    ],
                }),
            ],
            expandedIds: ['settings'],
        });
        await rowByLabel(wrapper, 'Customer').get('.sw-permissions-menu-tree__drag-handle').trigger('dragstart');
        mockHeight(rowByLabel(wrapper, 'General').element);
        await rowByLabel(wrapper, 'General').trigger('drop', { clientY: 1 });
        expect(wrapper.emitted('move')[0][0]).toEqual({ id: 'customer', parentId: 'settings', afterMenuItemId: null });
        wrapper.unmount();
    });

    it('does not mislabel explicit settings groups as ungrouped', async () => {
        const wrapper = await createWrapper({
            items: [
                createNode({
                    id: 'settings',
                    internalKey: 'sw-settings',
                    children: [createNode({ id: 'general', type: 'group', groupName: 'general', label: 'General' })],
                }),
            ],
            expandedIds: ['settings'],
        });
        expect(wrapper.text()).toContain('General');
        expect(wrapper.find('.sw-permissions-menu-tree__group').exists()).toBe(false);
        wrapper.unmount();
    });
});
