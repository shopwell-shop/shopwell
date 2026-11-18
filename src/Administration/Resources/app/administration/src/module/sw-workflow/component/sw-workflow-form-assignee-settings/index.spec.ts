/** @sw-package framework */
import type { ComponentPublicInstance } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';

describe('request people assignment settings', () => {
    it('offers only people fields and retains a removed field reference', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-form-assignee-settings', { sync: true }), {
            props: {
                config: { fieldKey: 'removed' },
                document: {
                    nodes: [
                        {
                            type: 'core:start',
                            typeVersion: 1,
                            config: {
                                formFields: [
                                    { key: 'people', label: 'Reviewers', type: 'users' },
                                    { key: 'text', label: 'Reason', type: 'text' },
                                ],
                            },
                        },
                    ],
                },
            },
            global: { stubs: { 'mt-select': { name: 'mt-select', props: ['options', 'modelValue'], template: '<div />' } } },
        });
        const select = wrapper.findComponent({ name: 'mt-select' }) as VueWrapper<
            ComponentPublicInstance<{ options: { value: string }[]; modelValue: string }>
        >;
        const options = select.props('options') as { value: string }[];
        expect(options.map((item) => item.value)).toEqual(['people', 'removed']);
        expect(select.props('modelValue')).toBe('removed');
        select.vm.$emit('update:model-value', 'text');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        select.vm.$emit('update:model-value', 'people');
        expect(wrapper.emitted('update-config')?.at(-1)).toEqual([{ fieldKey: 'people' }]);
        wrapper.unmount();
    });
});
