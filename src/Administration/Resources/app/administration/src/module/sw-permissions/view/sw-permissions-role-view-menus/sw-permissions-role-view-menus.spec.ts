/** @sw-package fundamentals@framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';
import PrivilegesService from 'src/app/service/privileges.service';
import type component from './index';

function page(id: string, parentId: string | null = 'catalogues'): MenuTreeNode {
    return {
        id,
        parentId,
        title: id,
        type: 'menu',
        routeKey: 'product',
        internalKey: id,
        groupName: null,
        icon: null,
        color: null,
        linkTarget: null,
        privilege: null,
        sysLocked: false,
        archived: false,
        configOverridden: false,
        pluginId: null,
        pluginName: null,
        afterMenuItemId: null,
        translations: {},
        active: true,
        children: [],
    };
}

async function createWrapper({
    selected = [] as string[],
    menuIds = [] as string[],
    disabled = false,
    duplicate = false,
    subresources = false,
    profile = false,
} = {}) {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntries([
        {
            category: 'permissions',
            key: 'product',
            parent: 'catalogues',
            roles: {
                viewer: { privileges: ['product:read'], dependencies: [] },
                editor: { privileges: ['product:update'], dependencies: ['product.viewer'] },
                creator: { privileges: ['product:create'], dependencies: ['product.editor'] },
                deleter: { privileges: ['product:delete'], dependencies: ['product.viewer'] },
            },
        },
        {
            category: 'additional_permissions',
            key: 'product_actions',
            parent: null,
            roles: {
                approve: { resource: 'product', privileges: ['product:approve'], dependencies: ['product.viewer'] },
            },
        },
        {
            category: 'additional_permissions',
            key: 'system',
            parent: null,
            roles: {
                clear_cache: { privileges: ['system:clear:cache'], dependencies: [] },
            },
        },
    ]);
    if (subresources) {
        privileges.addPrivilegeMappingEntry({
            category: 'permissions',
            key: 'product_subfeature',
            resource: 'product',
            parent: 'catalogues',
            roles: {
                viewer: { privileges: ['subfeature:read'], dependencies: [] },
                editor: { privileges: ['subfeature:update'], dependencies: ['product_subfeature.viewer'] },
            },
        });
    }
    if (profile) {
        privileges.addPrivilegeMappingEntry({
            category: 'additional_permissions',
            key: 'profile',
            parent: null,
            roles: {
                edit: { resource: 'product', menuIndependent: true, privileges: ['user_change_me'], dependencies: [] },
            },
        });
    }
    const store = Shopwell.Store.get('adminMenuConfig');
    store.items = [
        { ...page('catalogues', null), type: 'folder', routeKey: null },
        page('products'),
        ...(duplicate ? [page('products-copy')] : []),
    ];
    store.routeAllowlist = [
        { internalKey: 'product', type: 'menu', parent: null, title: 'Products', privilege: 'product.viewer' },
    ];
    store.isLoaded = true;
    store.isLoadFailed = false;
    const role = reactive({ privileges: selected, menuIds }) as Entity<'acl_role'> & { menuIds: string[] };
    const wrapper = shallowMount(
        (await wrapTestComponent('sw-permissions-role-view-menus', { sync: true })) as typeof component,
        {
            props: { role, 'onUpdate-role': (patch: Partial<typeof role>) => Object.assign(role, patch) },
            global: {
                provide: { acl: { can: () => !disabled }, privileges },
                renderStubDefaultSlot: true,
                stubs: {
                    'mt-checkbox': {
                        props: [
                            'modelValue',
                            'disabled',
                            'label',
                            'indeterminate',
                        ],
                        emits: ['update:model-value'],
                        template:
                            '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:model-value\', $event.target.checked)" />{{ label }}</label>',
                    },
                    'mt-button': { template: '<button><slot /></button>' },
                    'sw-permissions-role-view-general': true,
                    'sw-loader': true,
                },
            },
        },
    );
    await flushPromises();
    return { wrapper, role, store };
}

const menu = '[data-id="products"] .sw-permissions-role-view-menus__menu-checkbox input';
const operation = (id: string, row = 'products') => `[data-id="${row}"] [data-permission="${id}"] input`;

describe('simple menu and operation assignment', () => {
    it('assigns self-service access without granting the containing management menu', async () => {
        const { wrapper, role } = await createWrapper({ profile: true });
        expect(wrapper.get(operation('profile.edit')).attributes('disabled')).toBeUndefined();
        await wrapper.get(operation('profile.edit')).setValue(true);
        expect(role.privileges).toEqual(['profile.edit']);
        expect(role.menuIds).toEqual([]);
        await wrapper.get(menu).setValue(true);
        await wrapper.get(menu).setValue(false);
        expect(role.privileges).toEqual(['profile.edit']);
        expect(role.menuIds).toEqual([]);
        wrapper.unmount();
    });

    it('requires the owning page for child operations and removes them when that page is unchecked', async () => {
        const { wrapper, role } = await createWrapper({ subresources: true });
        await wrapper.get('[data-id="products"] .sw-permissions-role-view-menus__toggle').trigger('click');
        const selector = operation('product_subfeature.editor', 'products:permission:permissions:product_subfeature');
        expect(wrapper.get(selector).attributes('disabled')).toBeDefined();
        await wrapper.get(menu).setValue(true);
        expect(wrapper.get(selector).attributes('disabled')).toBeUndefined();
        expect(role.privileges).toEqual(['product.viewer']);
        await wrapper.get(selector).setValue(true);
        expect(role.privileges).toEqual(expect.arrayContaining(['product_subfeature.editor', 'product_subfeature.viewer']));
        await wrapper.get(menu).setValue(false);
        expect(role.privileges).toEqual([]);
        expect(role.menuIds).toEqual([]);
        wrapper.unmount();
    });

    it('places the page and its registered special operations in the same row', async () => {
        const { wrapper } = await createWrapper();
        const row = wrapper.get('[data-id="products"]');
        expect(row.find('[data-permission="product.viewer"]').exists()).toBe(true);
        expect(row.find('[data-permission="product_actions.approve"]').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-role-view-menus__presets').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-role-view-menus__dependencies').exists()).toBe(false);
        expect(wrapper.find('mt-search-stub').exists()).toBe(false);
        wrapper.unmount();
    });

    it('grants page access only when a menu is checked, without granting editing or approval', async () => {
        const { wrapper, role } = await createWrapper();
        await wrapper.get(menu).setValue(true);
        expect(role.menuIds).toEqual(['products']);
        expect(role.privileges).toEqual(['product.viewer']);
        expect(wrapper.get(operation('product_actions.approve')).element).toHaveProperty('checked', false);
        wrapper.unmount();
    });

    it('resolves dependencies when editing is selected and keeps query when editing is cancelled', async () => {
        const { wrapper, role } = await createWrapper({ selected: ['product.viewer'], menuIds: ['products'] });
        await wrapper.get(operation('product.editor')).setValue(true);
        expect(role.privileges).toEqual(expect.arrayContaining(['product.viewer', 'product.editor']));
        expect(wrapper.get(operation('product.viewer')).attributes('disabled')).toBeDefined();
        await wrapper.get(operation('product.editor')).setValue(false);
        expect(role.privileges).toEqual(['product.viewer']);
        wrapper.unmount();
    });

    it('assigns approval separately and removes it without revoking menu access', async () => {
        const { wrapper, role } = await createWrapper({ selected: ['product.viewer'], menuIds: ['products'] });
        await wrapper.get(operation('product_actions.approve')).setValue(true);
        expect(role.privileges).toEqual(expect.arrayContaining(['product.viewer', 'product_actions.approve']));
        expect(role.privileges).not.toContain('product.editor');
        await wrapper.get(operation('product_actions.approve')).setValue(false);
        expect(role.privileges).toEqual(['product.viewer']);
        wrapper.unmount();
    });

    it('recalculates explicit grants when another role has the same effective privileges', async () => {
        const { wrapper } = await createWrapper({ selected: ['product.viewer'], menuIds: ['products'] });
        await wrapper.get(operation('product.editor')).setValue(true);
        const nextRole = reactive({
            privileges: ['product.viewer', 'product.editor'],
            menuIds: [],
        }) as unknown as Entity<'acl_role'> & { menuIds: string[] };
        await wrapper.setProps({ role: nextRole });
        await wrapper.get(operation('product.editor')).setValue(false);
        expect(wrapper.emitted('update-role')?.at(-1)).toEqual([{ privileges: [], menuIds: [] }]);
        wrapper.unmount();
    });

    it('revokes the unchecked menu operations and retains unrelated permissions', async () => {
        const { wrapper, role } = await createWrapper({
            selected: ['product.viewer', 'product_actions.approve', 'system.clear_cache'],
            menuIds: ['products'],
        });
        await wrapper.get(menu).setValue(false);
        expect(role.menuIds).toEqual([]);
        expect(role.privileges).toEqual(['system.clear_cache']);
        wrapper.unmount();
    });

    it('keeps operations shared by another checked menu', async () => {
        const { wrapper, role } = await createWrapper({
            selected: ['product.viewer', 'product_actions.approve'],
            menuIds: ['products', 'products-copy'],
            duplicate: true,
        });
        await wrapper.get(menu).setValue(false);
        expect(role.menuIds).toEqual(['products-copy']);
        expect(role.privileges).toEqual(expect.arrayContaining(['product_actions.approve', 'product.viewer']));
        wrapper.unmount();
    });

    it('applies a folder checkbox to its entire branch even while searching', async () => {
        const { wrapper, role } = await createWrapper({ duplicate: true });
        await wrapper.setProps({ searchTerm: 'products-copy' });
        await wrapper.get('[data-id="catalogues"] .sw-permissions-role-view-menus__menu-checkbox input').setValue(true);
        expect(role.menuIds).toEqual(['products', 'products-copy']);
        expect(role.privileges).toEqual(['product.viewer']);
        wrapper.unmount();
    });

    it('keeps unassociated operations available in the collapsed other-permissions group', async () => {
        const { wrapper, role } = await createWrapper();
        expect(wrapper.find('[data-permission="system.clear_cache"]').exists()).toBe(false);
        await wrapper.get('[data-id="other-permissions"] button').trigger('click');
        await wrapper.get(operation('system.clear_cache', 'permission:additional_permissions:system')).setValue(true);
        expect(role.privileges).toEqual(['system.clear_cache']);
        wrapper.unmount();
    });

    it('does not alter hidden selections when searching or collapsing', async () => {
        const { wrapper, role } = await createWrapper({ selected: ['product.viewer'], menuIds: ['products'] });
        await wrapper.setProps({ searchTerm: 'missing' });
        expect(wrapper.find('[data-id="products"]').exists()).toBe(false);
        expect(role.privileges).toEqual(['product.viewer']);
        expect(role.menuIds).toEqual(['products']);
        await wrapper.setProps({ searchTerm: '' });
        const toggle = wrapper.get('.sw-permissions-role-view-menus__toggle-all');
        expect(wrapper.findAll('.sw-permissions-role-view-menus__toolbar button')).toHaveLength(1);
        const expandLabel = toggle.text();
        await toggle.trigger('click');
        expect(toggle.text()).not.toBe(expandLabel);
        await toggle.trigger('click');
        expect(toggle.text()).toBe(expandLabel);
        expect(wrapper.find('[data-id="products"]').exists()).toBe(false);
        await toggle.trigger('click');
        expect(wrapper.find('[data-id="products"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('prevents viewers and loading pages from modifying menus or operations', async () => {
        const { wrapper, role } = await createWrapper({
            disabled: true,
            selected: ['product.viewer'],
            menuIds: ['products'],
        });
        expect(wrapper.get(menu).attributes('disabled')).toBeDefined();
        expect(wrapper.get(operation('product.editor')).attributes('disabled')).toBeDefined();
        await wrapper.get(menu).setValue(false);
        expect(role.menuIds).toEqual(['products']);
        expect(wrapper.emitted('update-role')).toBeUndefined();
        wrapper.unmount();
        const loading = await createWrapper();
        await loading.wrapper.setProps({ isLoading: true });
        expect(loading.wrapper.get(menu).attributes('disabled')).toBeDefined();
        loading.wrapper.unmount();
    });

    it('retains the existing MCP reference view below the permission table', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.get('sw-permissions-role-view-general-stub').attributes('mcp-only')).toBeDefined();
        wrapper.unmount();
    });
});
