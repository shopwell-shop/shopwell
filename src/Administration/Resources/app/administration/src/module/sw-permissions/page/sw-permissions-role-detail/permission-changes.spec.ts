/** @sw-package fundamentals@framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import { defineComponent, markRaw } from 'vue';
import PrivilegesService from 'src/app/service/privileges.service';

const searchBarStub = defineComponent({
    name: 'sw-search-bar',
    emits: ['search'],
    template: '<div />',
});

async function createWrapper(canEdit = true) {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntry({
        category: 'permissions',
        key: 'product',
        parent: null,
        roles: {
            viewer: { privileges: ['product:read'], dependencies: [] },
            editor: { privileges: ['product:update'], dependencies: ['product.viewer'] },
        },
    });
    const role = {
        id: 'role',
        name: 'Operator',
        description: '',
        privileges: ['product.viewer'],
        menuIds: ['products'],
        isNew: () => false,
    };
    const save = jest.fn().mockResolvedValue(undefined);
    const wrapper = shallowMount(await wrapTestComponent('sw-permissions-role-detail', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="search-bar" /><slot name="smart-bar-header" /><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'sw-card-view': { template: '<div><slot /></div>' },
                'mt-tabs': true,
                'router-view': {
                    setup: () => ({
                        view: markRaw({ name: 'PermissionSearchTarget', props: ['searchTerm'], template: '<div />' }),
                    }),
                    template: '<slot :Component="view" />',
                },
                'sw-search-bar': searchBarStub,
                'sw-button-process': { template: '<button><slot /></button>' },
                'sw-verify-user-modal': true,
            },
            mocks: {
                $t: (key: string, params = {}) => `${key} ${JSON.stringify(params)}`,
                $route: { name: 'sw.permissions.role.detail.menus', params: { id: 'role' } },
                $router: { push: jest.fn() },
            },
            provide: {
                privileges,
                acl: { can: () => canEdit },
                feature: { isActive: () => false },
                repositoryFactory: { create: () => ({ get: jest.fn().mockResolvedValue(role), save }) },
                appAclService: { addAppPermissions: jest.fn().mockResolvedValue(undefined) },
                loginService: {},
                userService: {},
                ssoSettingsService: { isSso: jest.fn().mockResolvedValue({ isSso: false }) },
            },
        },
    });
    await flushPromises();
    return { wrapper, save, role };
}

describe('permission draft and save', () => {
    it('filters the permission view through the shared page-header search bar', async () => {
        const { wrapper, save } = await createWrapper();
        const search = wrapper.getComponent(searchBarStub);
        expect(search.attributes('initial-search-type')).toBe('admin_menu_item');
        expect(search.attributes('type-search-always-in-container')).toBe('false');
        search.vm.$emit('search', 'Products');
        await flushPromises();
        expect(wrapper.getComponent({ name: 'PermissionSearchTarget' }).props('searchTerm')).toBe('Products');
        search.vm.$emit('search', '');
        await flushPromises();
        expect(wrapper.getComponent({ name: 'PermissionSearchTarget' }).props('searchTerm')).toBe('');
        expect(save).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('keeps changes in the draft without a change-summary panel or duplicate save action', async () => {
        const { wrapper, role, save } = await createWrapper();
        await wrapper.setData({ role: { ...role, privileges: ['product.viewer', 'product.editor'], menuIds: [] } });
        expect(wrapper.find('.sw-permissions-role-detail__assignment-bar').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-role-detail__change-review').exists()).toBe(false);
        expect(wrapper.findAll('.sw-permissions-role-detail__button-save')).toHaveLength(1);
        expect(save).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('keeps role information editing out of the permission assignment header', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-permissions-role-detail__edit-info').exists()).toBe(false);
        expect(wrapper.find('sw-permissions-role-info-modal-stub').exists()).toBe(false);
        wrapper.unmount();
    });

    it('uses the existing password verification before saving from the page header', async () => {
        const { wrapper, save } = await createWrapper();
        await wrapper.get('.sw-permissions-role-detail__button-save').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-verify-user-modal-stub').exists()).toBe(true);
        expect(save).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('does not allow a viewer to save from the page header', async () => {
        const { wrapper, save } = await createWrapper(false);
        expect(wrapper.get('.sw-permissions-role-detail__button-save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-permissions-role-detail__button-save').trigger('click');
        await flushPromises();

        expect(save).not.toHaveBeenCalled();
        expect(wrapper.find('sw-verify-user-modal-stub').exists()).toBe(false);
        wrapper.unmount();
    });
});
