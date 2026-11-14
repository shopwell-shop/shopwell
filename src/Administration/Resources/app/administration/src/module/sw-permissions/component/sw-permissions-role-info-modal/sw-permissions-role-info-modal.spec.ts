/** @sw-package fundamentals@framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import type component from './index';

async function createWrapper({
    role = null as Entity<'acl_role'> | null,
    canEdit = true,
    isSso = true,
    copyFromId = null as string | null,
} = {}) {
    const newRole = { id: 'created-role', name: '', description: '', privileges: [], menuIds: [], isNew: () => true };
    const repository = {
        create: jest.fn(() => newRole),
        save: jest.fn().mockResolvedValue(undefined),
        get: jest.fn().mockResolvedValue({
            name: 'Source',
            description: 'Copied description',
            privileges: ['product:read'],
            menuIds: ['products'],
        }),
    };
    const wrapper = shallowMount(
        (await wrapTestComponent('sw-permissions-role-info-modal', { sync: true })) as typeof component,
        {
            props: { role, copyFromId: copyFromId ?? undefined },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: { can: () => canEdit },
                    repositoryFactory: { create: () => repository },
                    privileges: { getDefaultUserPrivileges: () => ['user:read'] },
                    ssoSettingsService: { isSso: jest.fn().mockResolvedValue({ isSso }) },
                },
                stubs: {
                    'mt-modal-root': { template: '<div><slot /></div>' },
                    'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                    'sw-permissions-role-view-general': {
                        props: ['role', 'editable'],
                        template:
                            '<div><input class="role-name" v-model="role.name" :disabled="!editable" /><textarea class="role-description" v-model="role.description" :disabled="!editable" /></div>',
                    },
                    'sw-verify-user-modal': {
                        template: '<button class="verify" @click="$emit(\'verified\', { verified: true })" />',
                    },
                },
            },
        },
    );
    await flushPromises();
    return { wrapper, repository, newRole };
}

describe('role information dialog', () => {
    it('requires a non-empty name before creating a role', async () => {
        const { wrapper, repository } = await createWrapper();
        await wrapper.get('.role-name').setValue('   ');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        expect(repository.save).not.toHaveBeenCalled();
        expect(wrapper.emitted('created')).toBeUndefined();
        wrapper.unmount();
    });

    it('creates with only default privileges and emits the persisted id', async () => {
        const { wrapper, repository, newRole } = await createWrapper();
        await wrapper.get('.role-name').setValue('  Order operator  ');
        await wrapper.get('.role-description').setValue('Handles orders');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        await flushPromises();
        expect(repository.save).toHaveBeenCalledWith(newRole, expect.any(Object));
        expect(newRole).toMatchObject({
            name: 'Order operator',
            description: 'Handles orders',
            privileges: ['user:read'],
            menuIds: [],
        });
        expect(wrapper.emitted('created')).toEqual([['created-role']]);
        wrapper.unmount();
    });

    it('waits for password verification before creating outside SSO', async () => {
        const { wrapper, repository } = await createWrapper({ isSso: false });
        await wrapper.get('.role-name').setValue('Operator');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        await flushPromises();
        expect(repository.save).not.toHaveBeenCalled();
        expect(wrapper.emitted('created')).toBeUndefined();
        await wrapper.get('.verify').trigger('click');
        await flushPromises();
        expect(repository.save).toHaveBeenCalledWith(expect.any(Object), { verified: true });
        expect(wrapper.emitted('created')).toEqual([['created-role']]);
        wrapper.unmount();
    });

    it('retains entered information after a failed creation and allows retry', async () => {
        const { wrapper, repository } = await createWrapper();
        repository.save.mockRejectedValueOnce(new Error('Unavailable'));
        await wrapper.get('.role-name').setValue('Operator');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-permissions-role-info-modal__error').exists()).toBe(true);
        expect(wrapper.get<HTMLInputElement>('.role-name').element.value).toBe('Operator');
        expect(wrapper.emitted('created')).toBeUndefined();
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('created')).toEqual([['created-role']]);
        wrapper.unmount();
    });

    it('edits local information without mutating or persisting the existing role', async () => {
        const role = {
            id: 'existing-role',
            name: 'Original',
            description: '',
            privileges: ['product.viewer'],
            isNew: () => false,
        } as unknown as Entity<'acl_role'>;
        const { wrapper, repository } = await createWrapper({ role });
        await wrapper.get('.role-name').setValue('Updated');
        expect(role.name).toBe('Original');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        expect(wrapper.emitted('confirm')).toEqual([[{ name: 'Updated', description: '' }]]);
        expect(repository.save).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('does not allow viewers to create or apply edits', async () => {
        const { wrapper, repository } = await createWrapper({ canEdit: false });
        expect(wrapper.get('.sw-permissions-role-info-modal__submit').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        expect(repository.save).not.toHaveBeenCalled();
        expect(wrapper.emitted('confirm')).toBeUndefined();
        wrapper.unmount();
    });

    it('preserves copied privileges and menu selection when duplicating a role', async () => {
        const { wrapper, repository, newRole } = await createWrapper({ copyFromId: 'source-role' });
        expect(repository.get).toHaveBeenCalledWith('source-role', expect.any(Object), expect.any(Object));
        await wrapper.get('.role-name').setValue('Source copy');
        await wrapper.get('.sw-permissions-role-info-modal__submit').trigger('click');
        await flushPromises();
        expect(newRole).toMatchObject({
            name: 'Source copy',
            description: 'Copied description',
            privileges: ['product:read'],
            menuIds: ['products'],
        });
        expect(wrapper.emitted('created')).toEqual([['created-role']]);
        wrapper.unmount();
    });
});
