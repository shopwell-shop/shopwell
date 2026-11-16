/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import type { NodeMouseEvent } from '@vue-flow/core';
import { defineComponent } from 'vue';
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
    it('pans only enough to keep the selected node visible when its settings reduce the canvas width', async () => {
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
        const flow = useVueFlow(wrapper.getComponent(VueFlow).props('id'));
        flow.applyNodeChanges([
            { id: 'start', type: 'dimensions', dimensions: { width: 160, height: 60 } },
            { id: 'end', type: 'dimensions', dimensions: { width: 160, height: 60 } },
        ]);
        await flushPromises();
        await flow.setViewport({ x: 500, y: 100, zoom: 0.6 });
        flow.dimensions.value.width = 450;
        await flushPromises();

        const start = flow.findNode('start')!;
        const right = (start.computedPosition.x + start.dimensions.width) * flow.viewport.value.zoom + flow.viewport.value.x;
        expect(right).toBeLessThanOrEqual(426);
        expect(flow.viewport.value.zoom).toBe(0.6);
        expect(flow.viewport.value.y).toBe(100);
        const position = { ...flow.viewport.value };
        flow.dimensions.value.width = 500;
        await flushPromises();
        expect(flow.viewport.value).toEqual(position);
        wrapper.unmount();
    });

    it('emits node clicks without selecting internally and preserves supplied selection on pane clicks', async () => {
        const clicked = jest.fn<void, [NodeMouseEvent]>();
        const observer = defineComponent({
            setup() {
                useVueFlow().onNodeClick(clicked);
                return {};
            },
            template: '<span />',
        });
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
            slots: { canvas: observer },
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
        expect(clicked).toHaveBeenCalledTimes(1);
        expect(clicked.mock.calls[0][0].node.id).toBe('end');
        expect(wrapper.get('.vue-flow__node[data-id="start"]').classes()).toContain('selected');
        expect(wrapper.get('.vue-flow__node[data-id="end"]').classes()).not.toContain('selected');
        await wrapper.get('.vue-flow__pane').trigger('click');
        expect(wrapper.emitted('canvas-click')).toHaveLength(1);
        expect(wrapper.get('.vue-flow__node[data-id="start"]').classes()).toContain('selected');
        wrapper.unmount();
    });
});
