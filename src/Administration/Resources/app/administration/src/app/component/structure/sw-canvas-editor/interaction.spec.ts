/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type CanvasEditor from './index';
import { VueFlow, useVueFlow } from '@vue-flow/core';

const originalResizeObserver = window.ResizeObserver;

describe('canvas renderer interaction ownership', () => {
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

    it('previews drag positions without editing the owner graph and discards an uncommitted move', async () => {
        const graph = {
            nodes: [{ id: 'card', position: { x: 40, y: 80 }, data: {} }],
            connections: [],
        };
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            { props: { graph } },
        );
        await flushPromises();
        const flow = wrapper.getComponent(VueFlow);
        flow.vm.$emit('init', useVueFlow(flow.props('id')));

        flow.vm.$emit('nodesChange', [{ id: 'card', type: 'position', position: { x: 90, y: 100 }, dragging: true }]);
        await flushPromises();

        expect(wrapper.get('[data-id="card"]').attributes('style')).toContain('translate(90px,100px)');
        expect(graph.nodes[0].position).toEqual({ x: 40, y: 80 });

        flow.vm.$emit('nodeDragStop', { node: { id: 'card', position: { x: 90, y: 100 } } });
        await flushPromises();

        expect(wrapper.emitted('move-node')).toEqual([[{ nodeId: 'card', position: { x: 90, y: 100 } }]]);
        expect(wrapper.get('[data-id="card"]').attributes('style')).toContain('translate(40px,80px)');
        wrapper.unmount();
    });

    it('selects focused nodes with the keyboard without handling keys inside node controls', async () => {
        const graph = { nodes: [{ id: 'card', position: { x: 0, y: 0 }, data: {} }], connections: [] };
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: { graph },
                slots: { node: '<input aria-label="Card title" />' },
            },
        );
        await flushPromises();

        await wrapper.get('button[data-canvas-node="card"]').trigger('click');
        await wrapper.get('input').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('select-node')).toEqual([['card']]);
        wrapper.unmount();
    });

    it('discards a drag preview immediately when editing permission is revoked', async () => {
        const graph = { nodes: [{ id: 'card', position: { x: 40, y: 80 }, data: {} }], connections: [] };
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            { props: { graph } },
        );
        await flushPromises();
        const flow = wrapper.getComponent(VueFlow);
        flow.vm.$emit('init', useVueFlow(flow.props('id')));
        flow.vm.$emit('nodesChange', [{ id: 'card', type: 'position', position: { x: 90, y: 100 }, dragging: true }]);
        await flushPromises();

        await wrapper.setProps({ allowEdit: false });
        flow.vm.$emit('nodeDragStop', { node: { id: 'card', position: { x: 90, y: 100 } } });
        await flushPromises();

        expect(wrapper.emitted('move-node')).toBeUndefined();
        expect(wrapper.get('[data-id="card"]').attributes('style')).toContain('translate(40px,80px)');
        wrapper.unmount();
    });
});
