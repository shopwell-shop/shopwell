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
    {
        internalKey: 'sw-catalog',
        title: 'sw-catalogue.general.mainMenuItemProducts',
        type: 'folder',
        parent: null,
        privilege: null,
    },
    {
        internalKey: 'sw.product.index',
        title: 'sw-product.general.mainMenuItemOverview',
        type: 'menu',
        parent: 'sw-catalog',
        privilege: 'product.viewer',
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
    languages = [
        { id: LANGUAGE_EN, name: 'English' },
        { id: LANGUAGE_ZH, name: '中文' },
    ],
} = {}) {
    const store = menuStore();

    store.items = MENU_ITEMS.map((item) => ({
        ...item,
        translations: { ...item.translations },
        roleIds: [...item.roleIds],
    }));
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
                $route: { params: {}, query: {}, meta: { $module: { icon: 'regular-list' } } },
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
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="side-content"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-search-bar': {
                    // The real search bar only emits `search` when it is not in container mode:
                    // with `type-search-always-in-container` it loads its own dropdown and never
                    // reaches the page. Modelling that here keeps the flag from creeping back in.
                    template: `
                        <div class="sw-search-bar-stub">
                            <input class="search-bar-input" :value="initialSearch" @input="onInput" />
                        </div>`,
                    props: {
                        initialSearch: { type: String, default: '' },
                        initialSearchType: { type: String, default: '' },
                        // declared as a Boolean on the real component as well, so the bare
                        // attribute in the template arrives as `true` instead of an empty string
                        typeSearchAlwaysInContainer: { type: Boolean, default: false },
                    },
                    methods: {
                        onInput(event) {
                            if (this.typeSearchAlwaysInContainer) {
                                return;
                            }

                            this.$emit('search', event.target.value);
                        },
                    },
                },
                'sw-language-switch': {
                    template: '<div class="sw-language-switch-stub"></div>',
                    props: [
                        'saveChangesFunction',
                        'abortChangeFunction',
                    ],
                },
                'sw-sidebar-collapse': {
                    template: `
                        <div class="sw-sidebar-collapse-stub">
                            <slot name="header"></slot>
                            <slot name="actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                    props: ['expandOnLoading'],
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': {
                    template: '<div class="mt-card-stub"><span class="mt-card-title">{{ title }}</span><slot></slot></div>',
                    props: ['title', 'positionIdentifier'],
                },
                'mt-banner': { template: '<div class="mt-banner-stub"><slot></slot></div>', props: ['variant'] },
                'mt-button': {
                    // `emits` keeps the click a component event: without it the listener falls
                    // through to the root element as well and every click runs twice
                    emits: ['click'],
                    template:
                        '<button class="mt-button-stub" :class="$attrs.class" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
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
                        '<input class="mt-text-field-stub" :class="$attrs.class" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
                        '<select class="mt-select-stub" :class="$attrs.class" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
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
                        '<input type="checkbox" class="mt-switch-stub" :class="$attrs.class" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
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
                    template: `
                        <div class="sw-system-menu-tree-stub">
                            <button class="tree-select-root" @click="$emit('select', items[0])"></button>
                            <button class="tree-select" @click="$emit('select', items[0].children[0])"></button>
                            <button class="tree-add-child" @click="$emit('add-child', items[0])"></button>
                            <button class="tree-create-below" @click="$emit('create-below', items[0].children[0])"></button>
                            <button class="tree-toggle" @click="$emit('toggle', items[0])"></button>
                            <button class="tree-remove-locked" @click="$emit('remove', items[0])"></button>
                            <button class="tree-remove-child" @click="$emit('remove', items[0].children[0])"></button>
                        </div>`,
                    props: [
                        'items',
                        'expandedIds',
                        'selectedId',
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

function treeStub(wrapper) {
    return wrapper.findComponent('.sw-system-menu-tree-stub');
}

async function typeTitle(wrapper, value) {
    await wrapper.find('.sw-system-menu-list__field-title').setValue(value);
}

async function selectFirstChild(wrapper) {
    await wrapper.find('.tree-select').trigger('click');
}

export { createWrapper, menuStore, selectFirstChild, treeStub, typeTitle };
