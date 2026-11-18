/** @sw-package framework */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';

describe('automatic approval settings', () => {
    it('preserves other policy choices and refuses edits in read-only mode', async () => {
        const wrapper: VueWrapper = mount(
            await wrapTestComponent('sw-workflow-approval-automation-settings', { sync: true }),
            {
                props: { config: { decision: 'manual', approveInitiator: true, deduplication: 'all' } },
                global: {
                    stubs: { 'mt-select': { name: 'mt-select', props: ['modelValue', 'options'], template: '<div />' } },
                },
            },
        );
        const select = wrapper.findComponent({ name: 'mt-select' }) as VueWrapper<ComponentPublicInstance>;
        select.vm.$emit('update:model-value', 'approve');
        expect(wrapper.emitted('update-config')?.at(-1)).toEqual([
            { decision: 'approve', approveInitiator: true, deduplication: 'all' },
        ]);
        await wrapper.setProps({ allowEdit: false });
        select.vm.$emit('update:model-value', 'reject');
        expect(wrapper.emitted('update-config')).toHaveLength(1);
        wrapper.unmount();
    });
});
