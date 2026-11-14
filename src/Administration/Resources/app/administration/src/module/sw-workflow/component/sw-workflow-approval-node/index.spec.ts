/** @sw-package framework */
import '../../service/extension/approval-workflow.registration';
import { mount } from '@vue/test-utils';
import '../../service';
import { toCanvasGraph } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';
describe('approval node presentation', () => {
    it('uses the source summary component even when a description is configured', async () => {
        const document = {
            schemaVersion: 1 as const,
            policy: 'core:structured',
            nodes: [
                {
                    id: 'a',
                    type: 'core:approval',
                    typeVersion: 1,
                    config: {
                        description: 'Extra details',
                        assignee: { source: 'core:user', config: { userId: 'selected-person' } },
                    },
                },
            ],
            connections: [],
        };
        const wrapper = mount(await wrapTestComponent('sw-workflow-approval-node', { sync: true }), {
            props: { data: toCanvasGraph(document, [], {}).nodes[0].data },
            global: {
                provide: { workflowApprovalService: Shopwell.Service('workflowApprovalService') },
                stubs: {
                    'sw-workflow-user-assignee-summary': {
                        props: ['config'],
                        template: '<span>Approver: {{ config.userId }}</span>',
                    },
                },
            },
        });
        expect(wrapper.text()).toContain('Approver: selected-person');
        expect(wrapper.text()).not.toContain('Extra details');
        wrapper.unmount();
    });
});
