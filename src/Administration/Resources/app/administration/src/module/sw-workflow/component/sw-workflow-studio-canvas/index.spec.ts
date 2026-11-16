/** @sw-package framework */
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';
import WorkflowLayoutService from '../../service/workflow-layout.service';
import { mount } from '@vue/test-utils';
import type WorkflowCanvas from './index';
import { createStructuredWorkflowDraft as createWorkflowDraft } from 'src/module/sw-workflow/util/workflow-template.util';

const mockFitView = jest.fn();
const mockUpdateNodeInternals = jest.fn();

jest.mock('@vue-flow/core', () => ({
    Position: { Top: 'top', Bottom: 'bottom' },
    VueFlow: {
        props: ['nodes', 'edges'],
        emits: [
            'node-click',
            'edge-click',
            'init',
            'nodes-initialized',
        ],
        methods: {
            initialize(this: { $emit: (name: string, payload?: unknown) => void }) {
                this.$emit('nodes-initialized');
                this.$emit('init', {
                    fitView: mockFitView,
                    zoomIn: jest.fn(),
                    zoomOut: jest.fn(),
                    updateNodeInternals: mockUpdateNodeInternals,
                    dimensions: { value: { width: 1000, height: 800 } },
                    getNodes: {
                        value: [
                            { id: 'start', dimensions: { width: 600, height: 240 } },
                            { id: 'end', dimensions: { width: 160, height: 44 } },
                        ],
                    },
                });
            },
        },
        template:
            '<div><slot v-for="edge in edges" name="edge-workflow" :id="edge.id" :source-x="0" :source-y="0" :target-x="300" :target-y="100" :data="edge.data" :label="edge.label"/><button class="initialize" @click="initialize">Initialize</button><span v-for="node in nodes" :key="node.id" class="projected" :data-node="node.id" :data-top="node.position.y">{{ node.position.x }}:{{ node.data.outputPorts.join(",") }}</span><button class="node" @click="$emit(\'node-click\', { node: { id: \'start\' } })">Node</button><button class="edge" @click="$emit(\'edge-click\', { edge: { id: \'start-end\' } })">Edge</button></div>',
    },
    Handle: { template: '<div />' },
    BaseEdge: { props: ['path'], template: '<span class="routed-path" :data-path="path" />' },
    EdgeLabelRenderer: { template: '<div><slot /></div>' },
    getSmoothStepPath: jest.fn(),
}));

describe('sw-workflow-studio-canvas', () => {
    it('activates registered per-node ports and a policy layout in the actual canvas', async () => {
        const presentation = new WorkflowNodePresentationService();
        let outputPorts = ['dynamic'];
        presentation.register('core:start', 1, {
            icon: 'regular-play',
            tone: 'neutral',
            resolvePorts: () => ({ inputPorts: [], outputPorts }),
        });
        const layout = new WorkflowLayoutService();
        layout.register('example:horizontal', (document) =>
            Object.fromEntries(document.nodes.map((node) => [node.id, { x: 42, y: 0 }])),
        );
        layout.registerPolicyLayout('example:custom', 'example:horizontal');
        const document = { ...createWorkflowDraft(), policy: 'example:custom' };
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof WorkflowCanvas>(
            await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }),
            {
                props: {
                    document,
                    nodeTypes: [{ name: 'core:start', version: 1, kind: 'start', inputPorts: [], outputPorts: ['out'] }],
                    labels: {},
                },
                global: {
                    stubs: { 'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }) },
                    provide: { workflowLayoutService: layout, workflowNodePresentationService: presentation },
                },
            },
        );
        expect(wrapper.find('.projected').text()).toBe('42:dynamic');
        await wrapper.find('.initialize').trigger('click');
        expect(mockFitView).toHaveBeenCalledWith({ padding: 0.3, maxZoom: 1 });

        outputPorts = ['updated', 'fallback'];
        await wrapper.setProps({ document: { ...document, nodes: [...document.nodes] } });
        await flushPromises();
        expect(mockUpdateNodeInternals).toHaveBeenCalledWith(['start', 'end']);
        expect(wrapper.find('.projected').text()).toBe('42:updated,fallback');
        wrapper.unmount();
    });

    it('relayouts from actual renderer dimensions before fitting the canvas', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof WorkflowCanvas>(
            await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }),
            {
                props: {
                    document: createWorkflowDraft(),
                    nodeTypes: [
                        { name: 'core:start', version: 1, kind: 'start', inputPorts: [], outputPorts: ['out'] },
                        { name: 'core:end', version: 1, kind: 'end', inputPorts: ['in'], outputPorts: [] },
                    ],
                    labels: {},
                },
                global: {
                    stubs: { 'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }) },
                    provide: {
                        workflowLayoutService: new WorkflowLayoutService(),
                        workflowNodePresentationService: new WorkflowNodePresentationService(),
                    },
                },
            },
        );
        await wrapper.get('.initialize').trigger('click');
        await flushPromises();
        expect(Number(wrapper.get('[data-node="end"]').attributes('data-top'))).toBeGreaterThanOrEqual(240 + 144);
        wrapper.unmount();
    });

    it('reveals the insertion control on connection focus and emits its anchor', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof WorkflowCanvas>(
            await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }),
            {
                props: { document: createWorkflowDraft(), nodeTypes: [], labels: {} },
                global: {
                    stubs: { 'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }) },
                    provide: {
                        workflowLayoutService: new WorkflowLayoutService(),
                        workflowNodePresentationService: new WorkflowNodePresentationService(),
                    },
                },
            },
        );
        const insert = wrapper.get('.sw-workflow-studio-canvas__edge-insert');
        expect(insert.classes()).not.toContain('is-visible');

        await wrapper.get('[data-canvas-connection="start-end"]').trigger('focus');
        expect(insert.classes()).toContain('is-visible');
        await insert.trigger('click');
        expect(wrapper.emitted('insert-node')).toEqual([['start-end', insert.element]]);

        await wrapper.setProps({ allowEdit: false });
        expect(wrapper.find('.sw-workflow-studio-canvas__edge-insert').exists()).toBe(false);
        wrapper.unmount();
    });

    it('translates canvas selections to definition identifiers without changing the document', async () => {
        const document = createWorkflowDraft();
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof WorkflowCanvas>(
            await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }),
            {
                props: { document, nodeTypes: [], labels: {} },
                global: {
                    stubs: { 'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }) },
                    provide: {
                        workflowLayoutService: new WorkflowLayoutService(),
                        workflowNodePresentationService: new WorkflowNodePresentationService(),
                    },
                },
            },
        );
        await wrapper.find('.node').trigger('click');
        await wrapper.find('.edge').trigger('click');
        expect(wrapper.emitted('select-node')).toEqual([['start']]);
        expect(wrapper.emitted('select-connection')).toEqual([['start-end']]);
        expect(document).toEqual(createWorkflowDraft());
        wrapper.unmount();
    });
});

describe('workflow extension scenarios', () => {
    it('uses an extension router in the rendered canvas without exposing Vue Flow stores', async () => {
        const layout = new WorkflowLayoutService();
        layout.register(
            'example:custom',
            (document) => Object.fromEntries(document.nodes.map((node) => [node.id, { x: 0, y: 0 }])),
            (source, target) => ({ path: 'M0,0L300,100', points: [source, target], label: source, insert: target }),
        );
        layout.registerPolicyLayout('example:policy', 'example:custom');
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof WorkflowCanvas>(
            await wrapTestComponent('sw-workflow-studio-canvas', { sync: true }),
            {
                props: { document: { ...createWorkflowDraft(), policy: 'example:policy' }, nodeTypes: [], labels: {} },
                global: {
                    stubs: { 'sw-canvas-editor': await wrapTestComponent('sw-canvas-editor', { sync: true }) },
                    provide: {
                        workflowLayoutService: layout,
                        workflowNodePresentationService: new WorkflowNodePresentationService(),
                    },
                },
            },
        );

        expect(wrapper.get('.routed-path').attributes('data-path')).toBe('M0,0L300,100');
        wrapper.unmount();
    });
});
