/** @sw-package framework */
import { mount } from '@vue/test-utils';
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';
import { toCanvasGraph } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';

describe('sw-workflow-studio-node', () => {
    it('passes instance identity and opaque configuration to custom presentations', async () => {
        const service = new WorkflowNodePresentationService();
        service.register('example:task', 1, { icon: 'regular-user', tone: 'blue', nodeComponent: 'example-workflow-node' });
        const document = {
            schemaVersion: 1 as const,
            policy: 'core:structured',
            nodes: [{ id: 'task-a', type: 'example:task', typeVersion: 1, config: { assignee: 'Finance' } }],
            connections: [],
        };
        const graph = toCanvasGraph(document, [], {});
        const wrapper = mount(await wrapTestComponent('sw-workflow-studio-node', { sync: true }), {
            props: { data: graph.nodes[0].data },
            global: {
                provide: { workflowNodePresentationService: service },
                stubs: {
                    'example-workflow-node': {
                        props: ['node'],
                        template: '<div>{{ node.id }}: {{ node.config.assignee }}</div>',
                    },
                },
            },
        });
        expect(wrapper.text()).toBe('task-a: Finance');
        wrapper.unmount();
    });
});
