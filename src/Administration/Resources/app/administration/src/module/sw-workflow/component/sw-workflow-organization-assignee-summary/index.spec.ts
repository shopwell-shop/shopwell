/** @sw-package framework */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import { WORKFLOW_ASSIGNEE_SOURCES } from 'src/core/workflow/workflow-node.constants';
import WorkflowApprovalService from '../../service/workflow-approval.service';

function node(source: string): WorkflowNode {
    return {
        id: 'approval',
        type: 'core:approval',
        typeVersion: 1,
        config: { assignee: { source, config: {} }, approval: { mode: 'core:single', config: {} } },
    };
}

describe('sw-workflow-organization-assignee-summary', () => {
    it('updates the organizational source presentation when the selected source changes', async () => {
        const service = new WorkflowApprovalService();
        for (const source of [WORKFLOW_ASSIGNEE_SOURCES.USER, WORKFLOW_ASSIGNEE_SOURCES.DEPARTMENT_LEADERS]) {
            service.registerSource({
                name: source,
                labelKey: `source.${source}`,
                settingsComponent: 'unused',
                summaryComponent: 'unused',
                supportedModes: ['core:single'],
            });
        }
        const wrapper: VueWrapper = mount(
            await wrapTestComponent('sw-workflow-organization-assignee-summary', { sync: true }),
            {
                props: { node: node(WORKFLOW_ASSIGNEE_SOURCES.USER) },
                global: {
                    provide: { workflowApprovalService: service },
                    stubs: { 'mt-banner': { template: '<p><slot /></p>' } },
                },
            },
        );

        expect(wrapper.text()).toBe('source.core:user');
        await wrapper.setProps({ node: node(WORKFLOW_ASSIGNEE_SOURCES.DEPARTMENT_LEADERS) });
        expect(wrapper.text()).toBe('source.core:department-leaders');
        wrapper.unmount();
    });
});
