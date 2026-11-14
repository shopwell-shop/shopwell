/** @sw-package framework */
import { mount } from '@vue/test-utils';

describe('sw-workflow-studio-toolbar', () => {
    it('keeps draft export available without a manual validation action or node selection', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-studio-toolbar', { sync: true }), {
            props: { downloadUrl: 'data:application/json,%7B%7D' },
        });
        const actions = wrapper.findAll('.sw-workflow-studio-toolbar__right .mt-button');
        expect(actions).toHaveLength(2);
        expect(wrapper.find('.sw-workflow-studio-toolbar__export').attributes('download')).toBe('workflow.json');
        expect(wrapper.find('.sw-workflow-studio-toolbar__panel-toggle').exists()).toBe(false);
        const templates = wrapper.find('.sw-workflow-studio-toolbar__templates');
        await templates.trigger('click');
        expect(wrapper.emitted('templates')).toHaveLength(1);
        wrapper.unmount();
    });
});
