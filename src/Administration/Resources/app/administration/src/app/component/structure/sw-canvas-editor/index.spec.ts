/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type CanvasEditor from './index';
import { VueFlow } from '@vue-flow/core';
import type { CanvasRenderGraph } from 'src/core/canvas-editor/canvas-renderer.types';

jest.mock('@vue-flow/core', () => ({
    Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
    VueFlow: {
        props: [
            'nodes',
            'edges',
            'nodesDraggable',
            'nodesConnectable',
            'elementsSelectable',
            'applyDefault',
        ],
        template: '<div><slot v-for="node in nodes" name="node-canvas" :id="node.id" :data="node.data" /></div>',
    },
    Handle: { props: ['id', 'type', 'position'], template: '<span class="port" :data-port="id" :data-side="position" />' },
    BaseEdge: { template: '<span />' },
    EdgeLabelRenderer: { template: '<div><slot /></div>' },
}));

const graph: CanvasRenderGraph = {
    nodes: [
        {
            id: 'image',
            position: { x: 40, y: 80 },
            component: 'example-image-node',
            data: { title: 'Product image' },
            outputPorts: [{ id: 'asset', side: 'right' }],
        },
        { id: 'gallery', position: { x: 320, y: 80 }, data: { title: 'Gallery' } },
    ],
    connections: [{ id: 'image-gallery', source: 'image', target: 'gallery', sourcePort: 'asset' }],
};

describe('sw-canvas-editor', () => {
    it('renders a non-workflow graph and optional shell regions without business services', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: { graph },
                slots: {
                    toolbar: '<button>Export image</button>',
                    'sidebar-end': '<div>Asset properties</div>',
                    overlay: '<div>Asset preview</div>',
                },
                global: {
                    stubs: {
                        'example-image-node': { props: ['data'], template: '<article>{{ data.title }}</article>' },
                    },
                },
            },
        );

        expect(wrapper.get('article').text()).toBe('Product image');
        expect(wrapper.get('[data-port="asset"]').attributes('data-side')).toBe('right');
        expect(wrapper.get('.sw-canvas-editor__toolbar').text()).toBe('Export image');
        expect(wrapper.get('.sw-canvas-editor__sidebar-end').text()).toBe('Asset properties');
        expect(wrapper.find('.sw-canvas-editor__sidebar-start').exists()).toBe(false);
        expect(wrapper.get('.sw-canvas-editor__overlay').text()).toBe('Asset preview');
        wrapper.unmount();
    });

    it('emits semantic editing intents while the supplied graph stays authoritative', async () => {
        const before = JSON.stringify(graph);
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            { props: { graph } },
        );
        const flow = wrapper.getComponent(VueFlow);

        flow.vm.$emit('node-click', { node: { id: 'image' } });
        flow.vm.$emit('node-drag-stop', { node: { id: 'image', position: { x: 90, y: 100 } } });
        flow.vm.$emit('connect', { source: 'gallery', target: 'image', sourceHandle: null, targetHandle: 'asset' });

        expect(wrapper.emitted('select-node')).toEqual([['image']]);
        expect(wrapper.emitted('move-node')).toEqual([[{ nodeId: 'image', position: { x: 90, y: 100 } }]]);
        expect(wrapper.emitted('connect')).toEqual([
            [{ source: 'gallery', target: 'image', sourcePort: null, targetPort: 'asset' }],
        ]);
        expect(flow.props('elementsSelectable')).toBe(false);
        expect(flow.props('applyDefault')).toBe(false);
        expect(JSON.stringify(graph)).toBe(before);
        wrapper.unmount();
    });

    it('blocks move and connection intents in a read-only canvas', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: { graph, allowEdit: false },
            },
        );
        const flow = wrapper.getComponent(VueFlow);

        flow.vm.$emit('node-drag-stop', { node: { id: 'image', position: { x: 90, y: 100 } } });
        flow.vm.$emit('connect', { source: 'gallery', target: 'image' });
        flow.vm.$emit('node-click', { node: { id: 'image' } });

        expect(flow.props('nodesDraggable')).toBe(false);
        expect(flow.props('nodesConnectable')).toBe(false);
        expect(wrapper.emitted('move-node')).toBeUndefined();
        expect(wrapper.emitted('connect')).toBeUndefined();
        expect(wrapper.emitted('select-node')).toEqual([['image']]);
        wrapper.unmount();
    });

    it('separates input and output handles on the same side and refreshes changed port geometry', async () => {
        const refresh = jest.fn();
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: {
                    graph: {
                        nodes: [
                            {
                                id: 'card',
                                position: { x: 0, y: 0 },
                                data: {},
                                inputPorts: [{ id: 'in', side: 'left' }],
                                outputPorts: [{ id: 'out', side: 'left' }],
                            },
                        ],
                        connections: [],
                    },
                },
            },
        );
        const ports = wrapper.findAll('.port');
        expect(ports[0].attributes('style')).not.toBe(ports[1].attributes('style'));

        wrapper.getComponent(VueFlow).vm.$emit('init', {
            fitView: jest.fn(),
            zoomIn: jest.fn(),
            zoomOut: jest.fn(),
            applyNodeChanges: jest.fn(),
            applyEdgeChanges: jest.fn(),
            updateNodeInternals: refresh,
            dimensions: { value: { width: 800, height: 600 } },
            getNodes: { value: [] },
        });
        await wrapper.setProps({
            graph: {
                nodes: [{ id: 'card', position: { x: 0, y: 0 }, data: {}, outputPorts: [{ id: 'out', side: 'right' }] }],
                connections: [],
            },
        });
        await flushPromises();

        expect(refresh).toHaveBeenCalledWith(['card']);
        expect(wrapper.get('[data-port="out"]').attributes('data-side')).toBe('right');
        wrapper.unmount();
    });
});
