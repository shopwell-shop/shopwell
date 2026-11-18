/** @sw-package framework */
import type { VueWrapper } from '@vue/test-utils';
import { mount } from '@vue/test-utils';

describe('organization assignee settings', () => {
    it('edits hierarchy settings without discarding other authored configuration', async () => {
        const wrapper: VueWrapper = mount(
            await wrapTestComponent('sw-workflow-organization-assignee-settings', { sync: true }),
            {
                props: {
                    node: {
                        id: 'approval',
                        type: 'core:approval',
                        typeVersion: 1,
                        config: { assignee: { source: 'core:supervisors' } },
                    },
                    config: { level: 2, continuous: false },
                },
            },
        );
        const level = wrapper.findComponent('[data-testid="hierarchy-level"]') as VueWrapper;
        level.vm.$emit('update:modelValue', 3);
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toEqual({ level: 3, continuous: false });
        (wrapper.findComponent('[data-testid="hierarchy-continuous"]') as VueWrapper).vm.$emit('update:modelValue', true);
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toEqual({ level: 2, continuous: true });
        await wrapper.setProps({ allowEdit: false });
        level.vm.$emit('update:modelValue', 4);
        expect(wrapper.emitted('update-config')).toHaveLength(2);
        wrapper.unmount();
    });
});
