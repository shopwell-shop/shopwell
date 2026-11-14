/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

describe('workflow approval decisions', () => {
    it('requires a rejection reason and locks both actions while submitting', async () => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            global: {
                stubs: {
                    'mt-textarea': {
                        props: ['modelValue'],
                        template:
                            '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                    },
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                },
            },
        });
        const reject = wrapper.findAll('button').filter((button) => button.text().endsWith('.reject'))[0];
        expect(reject.attributes('disabled')).toBeDefined();
        await wrapper.get('textarea').setValue('  Invalid account  ');
        await reject.trigger('click');
        expect(wrapper.emitted('reject')).toEqual([['Invalid account']]);
        await wrapper.setProps({ isLoading: true });
        expect(wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true);
        wrapper.unmount();
    });
});
