/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const comparison = { field: 'request.amount', fieldType: 'amount', operator: 'equals', value: '5000.00' };

describe('typed condition comparison', () => {
    it('keeps a decimal string exact and does not mutate the incoming rule', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-condition-comparison', { sync: true }), {
            props: { modelValue: comparison, fields: [{ path: 'request.amount', label: 'Amount', type: 'amount' }] },
        });
        const field = wrapper.findComponent('.sw-workflow-condition-settings__value') as VueWrapper;
        field.vm.$emit('update:modelValue', '999999999999.99');
        expect(wrapper.emitted('update:modelValue')!.at(-1)![0]).toMatchObject({ value: '999999999999.99' });
        expect(comparison.value).toBe('5000.00');
        wrapper.unmount();
    });
});
