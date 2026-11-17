/** @sw-package framework */
import { mount } from '@vue/test-utils';

const comparison = { field: 'request.amount', fieldType: 'amount', operator: 'greaterThan', value: '5000.00' };

describe('recursive condition rules', () => {
    it('wraps an existing comparison in a group without mutating the comparison', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-condition-rule', { sync: true }), {
            props: { modelValue: comparison, fields: [] },
            global: { stubs: { 'sw-workflow-condition-comparison': true } },
        });
        await wrapper.find('.sw-workflow-condition-rule__add-condition').trigger('click');
        expect(wrapper.emitted('update:modelValue')![0][0]).toEqual({
            operator: 'and',
            conditions: [comparison, { field: '', fieldType: '', operator: 'equals', value: '' }],
        });
        expect(comparison).toEqual({
            field: 'request.amount',
            fieldType: 'amount',
            operator: 'greaterThan',
            value: '5000.00',
        });
        wrapper.unmount();
    });

    it('prevents extending a comparison beyond the supported nesting depth', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-condition-rule', { sync: true }), {
            props: { modelValue: comparison, fields: [], depth: 6 },
            global: { stubs: { 'sw-workflow-condition-comparison': true } },
        });
        await wrapper.find('.sw-workflow-condition-rule__add-condition').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        wrapper.unmount();
    });
});

describe('branch condition quota', () => {
    async function createRule(modelValue: unknown, props = {}) {
        return mount(await wrapTestComponent('sw-workflow-condition-rule', { sync: true }), {
            props: { modelValue, fields: [], ...props },
            global: { stubs: { 'sw-workflow-condition-comparison': true, 'sw-workflow-condition-rule': true } },
        });
    }

    it('counts nested groups and comparisons toward the branch limit', async () => {
        const wrapper = await createRule({
            operator: 'and',
            conditions: [comparison, { operator: 'or', conditions: [comparison] }],
        });
        expect(wrapper.get('.sw-workflow-condition-rule__quota').text()).toBe('sw-workflow.condition.groups.quota');
        const children = wrapper.findAll('sw-workflow-condition-rule-stub');
        expect(children).toHaveLength(2);
        children.forEach((child) => expect(child.attributes('branch-size')).toBe('4'));
        wrapper.unmount();
    });

    it('allows one comparison at 99 but blocks a group requiring two slots', async () => {
        const wrapper = await createRule({
            operator: 'and',
            conditions: Array.from({ length: 98 }, () => ({ ...comparison })),
        });
        expect(wrapper.get('.sw-workflow-condition-rule__add-condition').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('.sw-workflow-condition-rule__add-group').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-workflow-condition-rule__add-condition').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toHaveLength(1);
        wrapper.unmount();
    });

    it.each([99, 100])(
        'reserves the wrapper group when extending a comparison in a branch of %s nodes',
        async (branchSize) => {
            const wrapper = await createRule(comparison, { depth: 1, branchSize });
            expect(wrapper.get('.sw-workflow-condition-rule__add-condition').attributes('disabled')).toBeDefined();
            await wrapper.get('.sw-workflow-condition-rule__add-condition').trigger('click');
            expect(wrapper.emitted('update:modelValue')).toBeUndefined();
            expect(wrapper.find('.sw-workflow-condition-rule__quota').exists()).toBe(false);
            wrapper.unmount();
        },
    );

    it('allows wrapping a comparison with a nested group when exactly three slots remain', async () => {
        const wrapper = await createRule(comparison, { depth: 1, branchSize: 97 });
        await wrapper.get('.sw-workflow-condition-rule__add-group').trigger('click');
        expect(wrapper.emitted('update:modelValue')![0][0]).toMatchObject({
            operator: 'and',
            conditions: [comparison, { operator: 'or' }],
        });
        wrapper.unmount();
    });

    it('keeps an oversized draft removable and reports the exceeded limit', async () => {
        const wrapper = await createRule({
            operator: 'and',
            conditions: Array.from({ length: 100 }, () => ({ ...comparison })),
        });
        expect(wrapper.get('.sw-workflow-condition-rule__quota').attributes('role')).toBe('alert');
        expect(wrapper.get('.sw-workflow-condition-rule__add-condition').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-workflow-condition-rule__remove').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toHaveLength(1);
        wrapper.unmount();
    });

    it('disables every mutation in read-only mode even when capacity remains', async () => {
        const wrapper = await createRule({ operator: 'and', conditions: [comparison] }, { allowEdit: false });
        for (const button of wrapper.findAll('button')) {
            expect(button.attributes('disabled')).toBeDefined();
            await button.trigger('click');
        }
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        wrapper.unmount();
    });
});
