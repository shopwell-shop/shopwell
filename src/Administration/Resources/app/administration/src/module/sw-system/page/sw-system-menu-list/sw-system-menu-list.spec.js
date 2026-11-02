/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

const MENU_ITEMS = [
    {
        id: 'catalog',
        parentId: null,
        afterMenuItemId: null,
        internalKey: 'sw-catalog',
        title: 'Catalog',
        icon: 'regular-products',
        color: null,
        type: 'folder',
        linkTarget: null,
        privilege: null,
        active: true,
        sysLocked: true,
        archived: false,
        configOverridden: false,
        pluginId: null,
        pluginName: null,
        roleIds: [],
    },
    {
        id: 'products',
        parentId: 'catalog',
        afterMenuItemId: null,
        internalKey: 'sw.product.index',
        title: 'Products',
        icon: 'regular-products',
        color: null,
        type: 'menu',
        linkTarget: null,
        privilege: 'product.viewer',
        active: true,
        sysLocked: false,
        archived: false,
        configOverridden: false,
        pluginId: null,
        pluginName: null,
        roleIds: ['role-a'],
    },
    {
        id: 'categories',
        parentId: 'catalog',
        afterMenuItemId: 'products',
        internalKey: 'sw.category.index',
        title: 'Categories',
        icon: null,
        color: null,
        type: 'menu',
        linkTarget: null,
        privilege: null,
        active: false,
        sysLocked: false,
        archived: false,
        configOverridden: true,
        pluginId: null,
        pluginName: null,
        roleIds: [],
    },
];

function menuStore() {
    return Shopwell.Store.get('adminMenuConfig');
}

async function createWrapper({
    privileges = [
        'admin_menu.viewer',
        'admin_menu.editor',
        'admin_menu.creator',
        'admin_menu.deleter',
    ],
} = {}) {
    const store = menuStore();

    store.items = MENU_ITEMS.map((item) => ({ ...item, roleIds: [...item.roleIds] }));
    store.routeWhitelist = [
        { internalKey: 'sw-catalog', title: 'Catalog', type: 'folder', parent: null, privilege: null },
        {
            internalKey: 'sw.product.index',
            title: 'Products',
            type: 'menu',
            parent: 'sw-catalog',
            privilege: 'product.viewer',
        },
    ];
    store.isLoaded = true;
    store.isLoading = false;

    jest.spyOn(store, 'load').mockResolvedValue(undefined);
    jest.spyOn(store, 'saveItem').mockResolvedValue('products');
    jest.spyOn(store, 'moveItem').mockResolvedValue(undefined);
    jest.spyOn(store, 'deleteItem').mockResolvedValue(undefined);

    const wrapper = mount(await wrapTestComponent('sw-system-menu-list', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $t: (key) => key,
                // the translate-with-fallback mixin asks whether a key exists before
                // translating it; the fixture titles are plain text, so nothing resolves
                $te: () => false,
                $route: { query: {}, meta: { $module: { icon: 'regular-list' } } },
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve([]) }),
                },
                privileges: {
                    getPrivilegesMappings: () => [
                        {
                            key: 'product',
                            roles: { viewer: {}, editor: {} },
                        },
                    ],
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="side-content"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot></slot></div>', props: ['title'] },
                'mt-banner': { template: '<div class="mt-banner-stub"><slot></slot></div>', props: ['variant'] },
                'mt-button': {
                    template:
                        '<button class="mt-button-stub" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                    props: [
                        'disabled',
                        'variant',
                        'size',
                        'isLoading',
                    ],
                },
                'mt-text-field': {
                    template:
                        '<input class="mt-text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                    props: [
                        'modelValue',
                        'label',
                        'disabled',
                        'required',
                        'helpText',
                        'placeholder',
                        'name',
                    ],
                },
                'mt-select': {
                    template:
                        '<select class="mt-select-stub" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                    props: [
                        'modelValue',
                        'options',
                        'label',
                        'disabled',
                        'helpText',
                        'required',
                    ],
                },
                'mt-switch': {
                    template:
                        '<input type="checkbox" class="mt-switch-stub" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                    props: [
                        'modelValue',
                        'label',
                        'disabled',
                        'bordered',
                        'helpText',
                    ],
                },
                'mt-icon': { template: '<span class="mt-icon-stub"></span>', props: ['name', 'size'] },
                'mt-empty-state': {
                    template: '<div class="mt-empty-state-stub">{{ headline }}<slot name="button"></slot></div>',
                    props: ['headline', 'description', 'icon'],
                },
                'sw-entity-multi-id-select': {
                    template: '<div class="sw-entity-multi-id-select-stub"></div>',
                    props: [
                        'value',
                        'repository',
                        'criteria',
                        'disabled',
                        'label',
                        'name',
                    ],
                },
                'sw-system-menu-tree': {
                    template: '<div class="sw-system-menu-tree-stub"></div>',
                    props: ['items', 'activeId', 'sortable'],
                },
                'sw-modal': {
                    template: '<div class="sw-modal-stub"><slot></slot><slot name="modal-footer"></slot></div>',
                    props: ['title', 'variant'],
                },
                'sw-skeleton': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('module/sw-system/page/sw-system-menu-list', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }

        jest.restoreAllMocks();
    });

    it('asks to pick an item when nothing is selected', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-system-menu-tree-stub').exists()).toBe(true);
    });

    it('loads the stored navigation on creation', async () => {
        wrapper = await createWrapper();

        expect(menuStore().load).toHaveBeenCalled();
    });

    it('fills the form with the selected item', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');
        await flushPromises();

        expect(wrapper.vm.draft.title).toBe('Products');
        expect(wrapper.vm.draft.internalKey).toBe('sw.product.index');
        expect(wrapper.find('.mt-text-field-stub').element.value).toBe('Products');
    });

    it('creates a new item below the parent of the selected one', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');
        wrapper.vm.onCreateNew();

        expect(wrapper.vm.draft.id).toBeNull();
        expect(wrapper.vm.draft.parentId).toBe('catalog');
        expect(wrapper.vm.draft.afterMenuItemId).toBe('products');
    });

    it('saves a new item and places it afterwards', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.selectItem('products');
        wrapper.vm.onCreateNew();
        wrapper.vm.draft.title = 'New entry';
        wrapper.vm.draft.internalKey = 'sw.product.index';

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'New entry',
                type: 'menu',
                internalKey: 'sw.product.index',
                roles: [],
            }),
        );
        expect(menuStore().moveItem).toHaveBeenCalledWith({
            id: 'products',
            parentId: 'catalog',
            afterMenuItemId: 'products',
        });
    });

    it('does not move an existing item that kept its position', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.selectItem('products');
        wrapper.vm.draft.title = 'Renamed';

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).toHaveBeenCalledWith(expect.objectContaining({ id: 'products', title: 'Renamed' }));
        expect(menuStore().moveItem).not.toHaveBeenCalled();
    });

    it('refuses to save without a title', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.selectItem('products');
        wrapper.vm.draft.title = '   ';

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).not.toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-system.menu.notification.titleRequired',
        });
    });

    it('refuses to save a menu without a target', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.selectItem('products');
        wrapper.vm.draft.internalKey = null;

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).not.toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-system.menu.notification.targetRequired',
        });
    });

    it('drops the route of an item when its type becomes a link', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');
        wrapper.vm.onTypeChange('link');

        expect(wrapper.vm.draft.internalKey).toBeNull();
        expect(wrapper.vm.draft.type).toBe('link');
    });

    it('resets the position when the parent changes', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('categories');
        wrapper.vm.onParentChange('');

        expect(wrapper.vm.draft.parentId).toBeNull();
        expect(wrapper.vm.draft.afterMenuItemId).toBeNull();
    });

    it('offers only folders below the top level as parents', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');

        expect(wrapper.vm.parentOptions).toEqual([
            { value: '', label: 'sw-system.menu.parentRoot' },
            { value: 'catalog', label: '— Catalog' },
        ]);
    });

    it('does not offer the item itself as its own parent', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('catalog');

        expect(wrapper.vm.parentOptions.map((option) => option.value)).toEqual(['']);
    });

    it('moves an item when it is dropped in the tree', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.onMove({ id: 'categories', parentId: null, afterMenuItemId: null });

        expect(menuStore().moveItem).toHaveBeenCalledWith({
            id: 'categories',
            parentId: null,
            afterMenuItemId: null,
        });
    });

    it('marks a core item as not deletable', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('catalog');
        await flushPromises();

        expect(wrapper.vm.isLocked).toBe(true);
        expect(wrapper.find('.sw-system-menu-list__button-delete').attributes('disabled')).toBeDefined();

        wrapper.vm.onDelete();

        expect(wrapper.vm.showDeleteModal).toBe(false);
    });

    it('counts the subtree a delete would remove', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('catalog');

        expect(wrapper.vm.descendantCount).toBe(2);
        expect(wrapper.vm.deleteConfirmMessage).toBe('sw-system.menu.deleteConfirmMessageWithChildren');
    });

    it('deletes the selected item after the confirmation', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.selectItem('products');
        wrapper.vm.onDelete();

        expect(wrapper.vm.showDeleteModal).toBe(true);

        await wrapper.vm.onConfirmDelete();

        expect(menuStore().deleteItem).toHaveBeenCalledWith('products');
        expect(wrapper.vm.draft).toBeNull();
        expect(wrapper.vm.selectedId).toBeNull();
    });

    it('asks before dropping unsaved changes', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');
        wrapper.vm.draft.title = 'Changed';
        wrapper.vm.selectItem('categories');

        expect(wrapper.vm.showDiscardModal).toBe(true);
        expect(wrapper.vm.selectedId).toBe('products');

        wrapper.vm.onDiscardConfirmed();

        expect(wrapper.vm.showDiscardModal).toBe(false);
        expect(wrapper.vm.selectedId).toBe('categories');
        expect(wrapper.vm.draft.title).toBe('Categories');
    });

    it('keeps the draft when discarding is cancelled', async () => {
        wrapper = await createWrapper();

        wrapper.vm.selectItem('products');
        wrapper.vm.draft.title = 'Changed';
        wrapper.vm.selectItem('categories');
        wrapper.vm.onDiscardCancelled();

        expect(wrapper.vm.selectedId).toBe('products');
        expect(wrapper.vm.draft.title).toBe('Changed');
    });

    it('hides the save action without the editor privilege', async () => {
        wrapper = await createWrapper({ privileges: ['admin_menu.viewer'] });

        expect(wrapper.find('.sw-system-menu-list__button-save').exists()).toBe(false);
    });
});
