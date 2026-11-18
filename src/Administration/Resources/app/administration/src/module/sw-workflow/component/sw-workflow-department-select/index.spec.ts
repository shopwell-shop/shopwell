/** @sw-package framework */
import type { ComponentPublicInstance } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';

describe('workflow department selection', () => {
    it('keeps unavailable selected departments and enforces candidates on changes', async () => {
        const id = '0123456789abcdef0123456789abcdef';
        const other = 'abcdef0123456789abcdef0123456789';
        const repository = {
            route: '/organization-unit',
            search: jest.fn().mockResolvedValue([]),
            create: jest.fn((_context: unknown, selectedId: string) => ({ id: selectedId })),
        };
        const wrapper = mount(await wrapTestComponent('sw-workflow-department-select', { sync: true }), {
            props: { modelValue: id, candidateIds: [id], label: 'Department' },
            global: {
                provide: { acl: { can: () => true }, repositoryFactory: { create: () => repository } },
                stubs: {
                    'sw-entity-single-select': {
                        name: 'sw-entity-single-select',
                        props: ['value', 'criteria'],
                        template: '<div />',
                    },
                },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('sw-workflow.form.unavailableSelection');
        const select = wrapper.findComponent({ name: 'sw-entity-single-select' }) as VueWrapper<
            ComponentPublicInstance<{ value: string }>
        >;
        expect(select.props('value')).toBe(id);
        select.vm.$emit('update:value', other, { id: other, active: true, organization: { active: true } });
        expect(wrapper.emitted('update:model-value')).toBeUndefined();
        select.vm.$emit('update:value', id, { id, active: true, organization: { active: true } });
        expect(wrapper.emitted('update:model-value')?.at(-1)).toEqual([id]);
        wrapper.unmount();
    });
});
