/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow } from '@vue-flow/core';
import WorkflowLayoutService from '../../service/workflow-layout.service';
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';

const originalResizeObserver = window.ResizeObserver;

describe('workflow renderer selection ownership', () => {
    beforeEach(() => {
        window.ResizeObserver = jest
            .fn()
            .mockImplementation(() => ({ observe: jest.fn(), unobserve: jest.fn(), disconnect: jest.fn() }));
        jest.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(800);
        jest.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockReturnValue(600);
    });
    afterEach(() => {
        jest.restoreAllMocks();
        window.ResizeObserver = originalResizeObserver;
    });
    it('emits node clicks without selecting internally and preserves supplied selection on pane clicks', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }), {
            props: {
                document: createStructuredWorkflowDraft(),
                labels: {},
                selectedNodeId: 'start',
                nodeTypes: [
                    { name: 'core:start', version: 1, kind: 'start', inputPorts: [], outputPorts: ['out'] },
                    { name: 'core:end', version: 1, kind: 'end', inputPorts: ['in'], outputPorts: [] },
                ],
            },
            global: {
                provide: {
                    workflowLayoutService: new WorkflowLayoutService(),
                    workflowNodePresentationService: new WorkflowNodePresentationService(),
                },
                stubs: {
                    'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }),
                    'sw-workflow-studio-node': { props: ['data'], template: '<span>{{ data.node.id }}</span>' },
                },
            },
        });
        await flushPromises();
        await new Promise((resolve) => setTimeout(resolve, 10));
        const flow = wrapper.getComponent(VueFlow);
        expect(flow.props('elementsSelectable')).toBe(false);
        expect(flow.props('applyDefault')).toBe(false);
        await wrapper.get('.vue-flow__node[data-id="end"]').trigger('click');
        expect(wrapper.emitted('select-node')).toEqual([['end']]);
        expect(wrapper.get('.vue-flow__node[data-id="start"]').classes()).toContain('selected');
        expect(wrapper.get('.vue-flow__node[data-id="end"]').classes()).not.toContain('selected');
        await wrapper.get('.vue-flow__pane').trigger('click');
        expect(wrapper.emitted('canvas-click')).toHaveLength(1);
        expect(wrapper.get('.vue-flow__node[data-id="start"]').classes()).toContain('selected');
        wrapper.unmount();
    });
});
