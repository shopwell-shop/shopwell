/** @sw-package framework */
import { mount } from '@vue/test-utils';

describe('sw-workflow-organization-assignee-settings', () => {
    it('explains department-based resolution without collecting fixed user assignments', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-organization-assignee-settings', { sync: true }), {
            props: { node: { id: 'approval', type: 'core:approval', typeVersion: 1, config: {} } },
            global: { stubs: { 'mt-banner': { template: '<p><slot /></p>' } } },
        });

        expect(wrapper.text()).toContain('sw-workflow.approval.departmentLeadersHint');
        expect(wrapper.find('input').exists()).toBe(false);
        expect(wrapper.emitted('update:config')).toBeUndefined();
        wrapper.unmount();
    });
});
