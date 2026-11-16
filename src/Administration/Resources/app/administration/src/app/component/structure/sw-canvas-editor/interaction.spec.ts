/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import type CanvasEditor from './index';

const originalResizeObserver = window.ResizeObserver;

describe('native canvas interaction ownership', () => {
    beforeEach(() => {
        window.ResizeObserver = jest.fn().mockImplementation(() => ({
            observe: jest.fn(),
            unobserve: jest.fn(),
            disconnect: jest.fn(),
        }));
        jest.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(800);
        jest.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockReturnValue(600);
    });

    afterEach(() => {
        jest.restoreAllMocks();
        window.ResizeObserver = originalResizeObserver;
    });

    it('preserves the viewport when a panel opens or node dimensions change after initialization', async () => {
        const wrapper = mount(await wrapTestComponent('sw-canvas-editor', { sync: true }), {
            props: { nodes: [{ id: 'card', position: { x: 40, y: 80 }, data: {} }] },
        });
        await flushPromises();
        const flow = useVueFlow(wrapper.getComponent(VueFlow).props('id'));
        flow.applyNodeChanges([{ id: 'card', type: 'dimensions', dimensions: { width: 240, height: 100 } }]);
        await flushPromises();
        await flow.setViewport({ x: 120, y: 90, zoom: 0.6 });

        flow.dimensions.value.width = 450;
        flow.applyNodeChanges([{ id: 'card', type: 'dimensions', dimensions: { width: 260, height: 130 } }]);
        await flushPromises();

        expect(flow.viewport.value).toEqual({ x: 120, y: 90, zoom: 0.6 });
        wrapper.unmount();
    });

    it('waits for a visible canvas before fitting measured nodes initially', async () => {
        const wrapper = mount(await wrapTestComponent('sw-canvas-editor', { sync: true }), {
            props: { nodes: [{ id: 'card', position: { x: 40, y: 80 }, data: {} }] },
        });
        await flushPromises();
        const flow = useVueFlow(wrapper.getComponent(VueFlow).props('id'));
        flow.dimensions.value = { width: 0, height: 0 };
        flow.applyNodeChanges([{ id: 'card', type: 'dimensions', dimensions: { width: 240, height: 100 } }]);
        await flushPromises();
        flow.dimensions.value = { width: 800, height: 600 };
        await flushPromises();

        expect(flow.viewport.value).toEqual({ x: 240, y: 170, zoom: 1 });
        wrapper.unmount();
    });

    it.each([true, false])('lets the owner choose native applyDefault=%s', async (applyDefault) => {
        const nodes = [{ id: 'card', position: { x: 40, y: 80 }, data: {} }];
        const onNodesChange = jest.fn();
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            { props: { nodes }, attrs: { applyDefault, onNodesChange } },
        );
        await flushPromises();
        const flow = useVueFlow(wrapper.getComponent(VueFlow).props('id'));
        const changes = [
            { id: 'card', type: 'position' as const, position: { x: 90, y: 100 }, from: { x: 40, y: 80 }, dragging: false },
        ];

        await flow.emits.nodesChange(changes);
        await flushPromises();

        expect(onNodesChange).toHaveBeenCalledWith(changes);
        expect(flow.findNode('card')?.position).toEqual(applyDefault ? { x: 90, y: 100 } : { x: 40, y: 80 });
        expect(nodes[0].position).toEqual({ x: 40, y: 80 });
        wrapper.unmount();
    });
});
