/** @sw-package fundamentals@framework */
import { shallowMount } from '@vue/test-utils';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';
import type component from './index';
import type operations from '../../components/sw-permissions-additional';
import PrivilegesService from 'src/app/service/privileges.service';

const item: MenuTreeNode = {
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
    id: 'article',
    parentId: null,
    afterMenuItemId: null,
    type: 'menu',
    internalKey: 'blog',
    routeKey: 'blog',
    title: 'Articles',
    translations: {},
    active: true,
    children: [],
};

async function createWrapper(disabled = false, pagePrivilege = 'blog.viewer') {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntry({
        category: 'permissions',
        key: 'blog',
        parent: 'content',
        roles: {
            viewer: { privileges: ['blog:read'], dependencies: [] },
            editor: { privileges: ['blog:update'], dependencies: ['blog.viewer'] },
        },
    });
    privileges.addPrivilegeMappingEntry({
        category: 'additional_permissions',
        key: 'system',
        parent: null,
        roles: { system_config: { privileges: [], dependencies: [] } },
    });
    privileges.addPrivilegeMappingEntry({
        category: 'additional_permissions',
        key: 'blog_actions',
        parent: null,
        roles: { publish: { resource: 'blog', privileges: [], dependencies: ['blog.viewer'] } },
    });
    privileges.addPrivilegeMappingEntry({
        category: 'additional_permissions',
        key: 'global',
        parent: null,
        roles: { run: { privileges: [], dependencies: [] } },
    });
    const store = Shopwell.Store.get('adminMenuConfig');
    store.items = [item];
    store.routeAllowlist = [
        { internalKey: 'blog', type: 'menu', parent: null, title: 'Articles', privilege: pagePrivilege },
    ];
    store.isLoaded = true;
    return shallowMount((await wrapTestComponent('sw-permissions-role-view-menus', { sync: true })) as typeof component, {
        props: {
            role: {
                privileges: ['blog.editor', 'blog.viewer'],
                menuIds: ['article'],
            } as Entity<'acl_role'> & { menuIds: string[] },
        },
        global: {
            provide: { acl: { can: () => !disabled }, privileges },
            renderStubDefaultSlot: true,
            stubs: {
                'sw-permissions-menu-tree': {
                    props: ['items'],
                    template:
                        '<div class="menu-tree"><div v-for="item in items" :key="item.id"><slot name="selection" :item="item" /><button @click="$emit(\'select\', item)">{{ item.label }}</button></div></div>',
                },
                'mt-checkbox': {
                    props: ['modelValue', 'disabled'],
                    emits: ['update:model-value'],
                    template:
                        '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:model-value\', $event.target.checked)" />',
                },
                'sw-permissions-grid': true,
                'sw-permissions-additional': { props: ['privilegeIds'], template: '<div class="additional-permissions" />' },
            },
        },
    });
}

describe('role menus and permissions workspace', () => {
    it('shows the additional permission required by a settings page', async () => {
        const wrapper = await createWrapper(false, 'system.system_config');
        await wrapper.get('.menu-tree button').trigger('click');
        expect(wrapper.getComponent<typeof operations>('.additional-permissions').props('privilegeIds')).toEqual([
            'system.system_config',
        ]);
        wrapper.unmount();
    });
    it('keeps page actions together and leaves unassociated operations in the global view', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.getComponent<typeof operations>('.additional-permissions').props('privilegeIds')).toEqual(
            expect.arrayContaining(['blog_actions.publish', 'global.run']),
        );
        await wrapper.get('.menu-tree button').trigger('click');
        expect(wrapper.getComponent<typeof operations>('.additional-permissions').props('privilegeIds')).toEqual([
            'blog_actions.publish',
        ]);
        await wrapper.get('.sw-permissions-role-view-menus__other').trigger('click');
        expect(wrapper.getComponent<typeof operations>('.additional-permissions').props('privilegeIds')).toEqual([
            'system.system_config',
            'global.run',
        ]);
        expect(wrapper.props('role')!.privileges).toEqual(['blog.editor', 'blog.viewer']);
        wrapper.unmount();
    });
    it('offers menu selection directly without a navigation mode', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.mt-select').exists()).toBe(false);
        expect((wrapper.get('input[type=checkbox]').element as HTMLInputElement).checked).toBe(true);
        wrapper.unmount();
    });
    it('removes a navigation entry without revoking shared business privileges', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('input[type=checkbox]').setValue(false);
        expect(wrapper.emitted('update-role')).toEqual([[{ menuIds: [] }]]);
        expect(wrapper.props('role')!.privileges).toEqual(['blog.editor', 'blog.viewer']);
        wrapper.unmount();
    });
    it('does not change hidden selections when the tree is filtered', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ term: 'missing' });
        expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
        expect(wrapper.props('role')!.menuIds).toEqual(['article']);
        wrapper.unmount();
    });
    it('prevents read-only users from changing navigation', async () => {
        const wrapper = await createWrapper(true);
        expect(wrapper.get('input[type=checkbox]').attributes('disabled')).toBeDefined();
        await wrapper.get('input[type=checkbox]').setValue(false);
        expect(wrapper.emitted('update-role')).toBeUndefined();
        wrapper.unmount();
    });
});
