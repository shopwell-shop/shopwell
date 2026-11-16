/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow } from '@vue-flow/core';
import type CanvasEditor from './index';

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
        template: '<div><slot /></div>',
    },
}));

describe('sw-canvas-editor shell', () => {
    it('renders optional host regions without requiring business services', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: { nodes: [] },
                attrs: { class: 'asset-editor', style: { height: '600px' } },
                slots: {
                    toolbar: '<button>Export image</button>',
                    'sidebar-end': '<div>Asset properties</div>',
                    overlay: '<div>Asset preview</div>',
                    footer: '<div>Ready</div>',
                },
            },
        );

        expect(wrapper.classes()).toContain('asset-editor');
        expect(wrapper.attributes('style')).toContain('height: 600px');
        expect(wrapper.get('.sw-canvas-editor__toolbar').text()).toBe('Export image');
        expect(wrapper.get('.sw-canvas-editor__sidebar-end').text()).toBe('Asset properties');
        expect(wrapper.find('.sw-canvas-editor__sidebar-start').exists()).toBe(false);
        expect(wrapper.get('.sw-canvas-editor__overlay').text()).toBe('Asset preview');
        expect(wrapper.get('.sw-canvas-editor__footer').text()).toBe('Ready');
        wrapper.unmount();
    });

    it('forwards native options and original event payloads to the owner', async () => {
        const onNodeClick = jest.fn();
        const onConnect = jest.fn();
        const nodes = [{ id: 'asset', position: { x: 40, y: 80 }, data: {} }];
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: { nodes },
                attrs: {
                    nodesDraggable: false,
                    nodesConnectable: false,
                    applyDefault: false,
                    onNodeClick,
                    onConnect,
                },
            },
        );
        const flow = wrapper.getComponent(VueFlow);
        const click = { event: new MouseEvent('click'), node: nodes[0] };
        const connection = { source: 'asset', target: 'gallery', sourceHandle: 'out', targetHandle: 'in' };
        flow.vm.$emit('node-click', click);
        flow.vm.$emit('connect', connection);

        expect(onNodeClick).toHaveBeenCalledWith(click);
        expect(onConnect).toHaveBeenCalledWith(connection);
        expect(flow.props('nodes')).toEqual(nodes);
        expect(flow.props('nodesDraggable')).toBe(false);
        expect(flow.props('nodesConnectable')).toBe(false);
        expect(flow.props('applyDefault')).toBe(false);
        wrapper.unmount();
    });
});
