/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

const LANGUAGE_EN = 'language-en';
const LANGUAGE_ZH = 'language-zh';

function createItem(overrides = {}) {
    return {
        id: 'catalog',
        parentId: null,
        afterMenuItemId: null,
        internalKey: 'sw-catalog',
        title: null,
        groupName: null,
        translations: {},
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
        ...overrides,
    };
}

const MENU_ITEMS = [
    createItem(),
    createItem({
        id: 'products',
        parentId: 'catalog',
        internalKey: 'sw.product.index',
        type: 'menu',
        sysLocked: false,
        privilege: 'product.viewer',
        roleIds: ['role-a'],
        groupName: 'general',
        translations: {
            [LANGUAGE_ZH]: { title: '产品目录', groupTitle: '常规' },
        },
    }),
    createItem({
        id: 'categories',
        parentId: 'catalog',
        afterMenuItemId: 'products',
        internalKey: 'sw.category.index',
        type: 'menu',
        icon: null,
        active: false,
        sysLocked: false,
        configOverridden: true,
    }),
];

const ROUTE_ALLOWLIST = [
    { internalKey: 'sw-catalog', title: 'sw-catalogue.general.mainMenuItemProducts', type: 'folder', parent: null, privilege: null },
    { internalKey: 'sw.product.index', title: 'sw-product.general.mainMenuItemOverview', type: 'menu', parent: 'sw-catalog', privilege: 'product.viewer' },
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
    languages = [
        { id: LANGUAGE_EN, name: 'English' },
        { id: LANGUAGE_ZH, name: '中文' },
    ],
} = {}) {
    const store = menuStore();

    store.items = MENU_ITEMS.map((item) => ({ ...item, translations: { ...item.translations }, roleIds: [...item.roleIds] }));
    store.routeAllowlist = ROUTE_ALLOWLIST;
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
                    create: () => ({ search: () => Promise.resolve(languages) }),
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
                        'square',
                        'title',
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
                        'suggestions',
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
                        'size',
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
                        'helpText',
                    ],
                },
                'sw-system-menu-tree': {
                    template: '<div class="sw-system-menu-tree-stub"></div>',
                    props: [
                        'items',
                        'expandedIds',
                        'sortable',
                        'editable',
                        'deletable',
                    ],
                },
                'sw-modal': {
                    template: '<div class="sw-modal-stub">{{ title }}<slot></slot><slot name="modal-footer"></slot></div>',
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

    beforeEach(() => {
        Shopwell.Context.api.languageId = LANGUAGE_EN;
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }

        jest.restoreAllMocks();
    });

    it('loads the stored navigation and the languages it can be labelled in', async () => {
        wrapper = await createWrapper();

        expect(menuStore().load).toHaveBeenCalled();
        expect(wrapper.vm.languages).toHaveLength(2);
        expect(wrapper.vm.showLanguageSwitch).toBe(true);
    });

    it('renders the top level entries without expanding them', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.tree).toHaveLength(1);
        expect(wrapper.vm.expandedIds).toEqual([]);
        expect(wrapper.find('.sw-system-menu-tree-stub').exists()).toBe(true);
    });

    it('resolves the declared label of an entry that has no stored one', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.tree[0].label).toBe('sw-catalogue.general.mainMenuItemProducts');
    });

    it('shows the stored label of the language the page displays', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onLanguageChange(LANGUAGE_ZH);
        await flushPromises();

        expect(wrapper.vm.tree[0].children[0].label).toBe('产品目录');
        expect(wrapper.vm.tree[0].children[0].groupLabel).toBe('常规');
    });

    it('expands and collapses an entry', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onToggle(wrapper.vm.tree[0]);
        expect(wrapper.vm.expandedIds).toEqual(['catalog']);

        wrapper.vm.onToggle(wrapper.vm.tree[0]);
        expect(wrapper.vm.expandedIds).toEqual([]);
    });

    it('expands every entry at once and collapses them again', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onToggleAll();
        expect(wrapper.vm.expandedIds).toEqual(['catalog']);

        wrapper.vm.onToggleAll();
        expect(wrapper.vm.expandedIds).toEqual([]);
    });

    it('prefills the dialog with the label and the group of the entry', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onEdit(wrapper.vm.tree[0].children[0]);
        await flushPromises();

        expect(wrapper.vm.showEditModal).toBe(true);
        expect(wrapper.vm.draft.title).toBe('sw-product.general.mainMenuItemOverview');
        expect(wrapper.vm.draft.groupName).toBe('general');
    });

    it('saves the labels for the displayed language instead of the client language', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.onLanguageChange(LANGUAGE_ZH);
        wrapper.vm.onEdit(wrapper.vm.tree[0].children[0]);
        wrapper.vm.draft.title = 'Produkte';
        wrapper.vm.draft.groupTitle = 'Allgemein';

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).toHaveBeenCalledWith(
            expect.objectContaining({
                groupName: 'general',
                translations: [{ languageId: LANGUAGE_ZH, title: 'Produkte', groupTitle: 'Allgemein' }],
            }),
        );
    });

    it('places a renamed entry only when its level changed', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.onEdit(wrapper.vm.tree[0].children[0]);
        wrapper.vm.draft.title = 'Renamed';

        await wrapper.vm.onSave();

        expect(menuStore().moveItem).not.toHaveBeenCalled();
    });

    it('creates a new entry at the top level and places it', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();
        menuStore().saveItem.mockResolvedValue('new-id');

        wrapper.vm.onCreateNew();
        wrapper.vm.draft.title = 'New entry';
        wrapper.vm.draft.internalKey = 'sw.product.index';

        await wrapper.vm.onSave();

        expect(menuStore().saveItem).toHaveBeenCalledWith(expect.not.objectContaining({ id: expect.anything() }));
        expect(menuStore().moveItem).toHaveBeenCalledWith({
            id: 'new-id',
            parentId: null,
            afterMenuItemId: null,
        });
    });

    it('refuses to delete a locked entry', async () => {
        wrapper = await createWrapper();

        wrapper.vm.onDelete(wrapper.vm.tree[0]);

        expect(wrapper.vm.showDeleteModal).toBe(false);
    });

    it('deletes an entry after the confirmation', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationSuccess = jest.fn();

        wrapper.vm.onDelete(wrapper.vm.tree[0].children[0]);
        expect(wrapper.vm.showDeleteModal).toBe(true);

        await wrapper.vm.onConfirmDelete();

        expect(menuStore().deleteItem).toHaveBeenCalledWith('products');
        expect(wrapper.vm.showDeleteModal).toBe(false);
    });

    it('names the subtree size in the delete confirmation', async () => {
        wrapper = await createWrapper();

        // a folder an administrator created himself: it may go, and its children go with it
        menuStore().items[0].sysLocked = false;
        await flushPromises();

        wrapper.vm.onDelete(wrapper.vm.tree[0]);

        expect(wrapper.vm.deleteConfirmMessage).toBe('sw-system.menu.deleteConfirmMessageWithChildren');
    });
});
