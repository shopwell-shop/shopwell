/** @sw-package fundamentals@framework */
import { shallowMount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';
import type component from './index';

async function createWrapper(selected: string[] = [], props: Record<string, unknown> = {}) {
    const privileges = new PrivilegesService();
    privileges.addPrivilegeMappingEntries([
        { category: 'additional_permissions', key: 'empty', parent: null, roles: {} },
        {
            category: 'additional_permissions',
            key: 'system',
            parent: null,
            roles: {
                clear_cache: { privileges: [], dependencies: [] },
                plugin_maintain: { privileges: [], dependencies: ['system.clear_cache'] },
                extension_store: { privileges: [], dependencies: ['system.plugin_maintain'] },
                use_app: { privileges: [], dependencies: ['app.example'] },
            },
        },
        {
            category: 'additional_permissions',
            key: 'app',
            parent: null,
            roles: {
                all: { privileges: [], dependencies: ['app.example', 'app.other'] },
                example: { privileges: [], dependencies: [] },
                other: { privileges: [], dependencies: [] },
            },
        },
    ]);
    return shallowMount((await wrapTestComponent('sw-permissions-additional', { sync: true })) as typeof component, {
        props: { role: { privileges: selected }, ...props },
        global: {
            provide: { privileges },
            renderStubDefaultSlot: true,
            mocks: {
                $t: (key: string, values?: Record<string, unknown>) =>
                    values ? `${key}: ${Object.values(values).join(', ')}` : key,
            },
            stubs: {
                'mt-card': { template: '<section><slot /></section>' },
                'mt-search': {
                    props: ['modelValue', 'size'],
                    emits: ['update:modelValue'],
                    template:
                        '<input type="search" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled', 'label'],
                    emits: ['update:modelValue'],
                    template:
                        '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>',
                },
            },
        },
    });
}

describe('additional permission presentation and dependencies', () => {
    it('hides groups without any permissions', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.sw-permissions-additional_empty').exists()).toBe(false);
        wrapper.unmount();
    });
    it('shows only permissions needed by the selected page', async () => {
        const wrapper = await createWrapper([], { privilegeIds: ['system.clear_cache'] });
        expect(wrapper.findAll('input[type=checkbox]')).toHaveLength(1);
        expect(wrapper.text()).toContain('system.clear_cache');
        expect(wrapper.find('.sw-permissions-additional-app').exists()).toBe(false);
        wrapper.unmount();
    });
    it('filters by permission name without changing the selection', async () => {
        const wrapper = await createWrapper(['system.clear_cache']);
        await wrapper.get('input[type=search]').setValue('extension_store');
        expect(wrapper.findAll('input[type=checkbox]')).toHaveLength(1);
        expect(wrapper.props('role')!.privileges).toEqual(['system.clear_cache']);
        await wrapper.get('input[type=search]').setValue('not-a-permission');
        expect(wrapper.find('[role=status]').exists()).toBe(true);
        wrapper.unmount();
    });
    it('restores transitive dependencies of an already selected permission', async () => {
        const wrapper = await createWrapper(['system.extension_store']);
        expect(wrapper.props('role')!.privileges).toEqual(
            expect.arrayContaining([
                'system.extension_store',
                'system.plugin_maintain',
                'system.clear_cache',
            ]),
        );
        expect(wrapper.get('.sw_permissions_additional_system_clear_cache input').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('sw-privileges.additional_permissions.system.plugin_maintain');
        expect(wrapper.text()).not.toContain('sw-privileges.roles.plugin_maintain');
        wrapper.unmount();
    });
    it('clears hidden app grants too when all apps is disabled during a search', async () => {
        const wrapper = await createWrapper(['app.all']);
        await wrapper.get('input[type=search]').setValue('app.all');
        await wrapper.get('.sw_permissions_additional_app_all input').setValue(false);
        expect(wrapper.props('role')!.privileges).toEqual([]);
        wrapper.unmount();
    });
    it('clears the previous search when switching to a page with a compact permission list', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('input[type=search]').setValue('extension_store');
        await wrapper.setProps({ privilegeIds: ['system.clear_cache'] });
        expect(wrapper.find('input[type=search]').exists()).toBe(false);
        expect(wrapper.find('.sw_permissions_additional_system_clear_cache').exists()).toBe(true);
        expect(wrapper.find('[role=status]').exists()).toBe(false);
        wrapper.unmount();
    });
    it('expands the shared page scope without changing the role grants', async () => {
        const wrapper = await createWrapper([], {
            privilegeIds: ['system.clear_cache'],
            relatedPages: { 'system.clear_cache': ['Cache', 'Maintenance'] },
        });
        expect(wrapper.find('input[type=search]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Maintenance');
        await wrapper.get('.sw-permissions-additional__scope-toggle').trigger('click');
        expect(wrapper.text()).toContain('Cache、Maintenance');
        expect(wrapper.props('role')!.privileges).toEqual([]);
        wrapper.unmount();
    });
    it('keeps app access required by another permission when all apps is unchecked', async () => {
        const wrapper = await createWrapper([
            'system.use_app',
            'app.all',
            'app.example',
            'app.other',
        ]);
        await wrapper.get('.sw_permissions_additional_app_all input').setValue(false);
        expect(wrapper.props('role')!.privileges).toContain('app.example');
        expect(wrapper.props('role')!.privileges).not.toContain('app.all');
        expect(wrapper.props('role')!.privileges).not.toContain('app.other');
        wrapper.unmount();
    });
});
