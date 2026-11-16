/** @sw-package framework */
import { flushPromises } from '@vue/test-utils';
import { effectScope, ref } from 'vue';
import type { VueFlowStore } from '@vue-flow/core';
import useCanvasViewport from './use-canvas-viewport';

function canvas() {
    const nodes = ref([{ id: 'first', dimensions: { width: 0, height: 0 } }]);
    const dimensions = ref({ width: 0, height: 0 });
    const fitView = jest.fn();
    const flow = {
        getNodes: nodes,
        dimensions,
        fitView,
        zoomIn: jest.fn(),
        zoomOut: jest.fn(),
    } as unknown as VueFlowStore;
    return { flow, nodes, dimensions, fitView };
}

describe('canvas viewport measurements', () => {
    const scopes: ReturnType<typeof effectScope>[] = [];

    afterEach(() => {
        scopes.splice(0).forEach((scope) => scope.stop());
    });

    function observe(options: Parameters<typeof useCanvasViewport>[0] = {}) {
        const scope = effectScope();
        scopes.push(scope);
        const viewport = scope.run(() => useCanvasViewport(options));

        if (!viewport) {
            throw new Error('The viewport scope did not start.');
        }

        return { viewport, stop: () => scope.stop() };
    }

    it('fits a fully measured graph once and preserves the viewport through later changes', async () => {
        const onSizesChange = jest.fn();
        const { viewport, stop } = observe({ padding: 0.18, onSizesChange });
        const { flow, nodes, dimensions, fitView } = canvas();
        viewport.onInit(flow);
        dimensions.value = { width: 800, height: 600 };
        await flushPromises();
        expect(fitView).not.toHaveBeenCalled();

        nodes.value[0].dimensions = { width: 240, height: 120 };
        await flushPromises();
        expect(fitView).toHaveBeenCalledTimes(1);
        expect(fitView).toHaveBeenCalledWith({ padding: 0.18, maxZoom: 1 });
        expect(onSizesChange).toHaveBeenLastCalledWith({ first: { width: 240, height: 120 } });

        dimensions.value.width = 500;
        nodes.value[0].dimensions.height = 180;
        await flushPromises();
        expect(fitView).toHaveBeenCalledTimes(1);
        expect(viewport.nodeSizes.value.first.height).toBe(180);

        viewport.fit();
        expect(fitView).toHaveBeenCalledTimes(2);
        const notificationCount = onSizesChange.mock.calls.length;
        stop();
        nodes.value[0].dimensions.height = 300;
        await flushPromises();
        expect(onSizesChange).toHaveBeenCalledTimes(notificationCount);
    });

    it('stops observing a replaced canvas', async () => {
        const { viewport, stop } = observe();
        const first = canvas();
        const second = canvas();
        viewport.onInit(first.flow);
        viewport.onInit(second.flow);
        first.nodes.value[0].dimensions = { width: 300, height: 200 };
        first.dimensions.value = { width: 800, height: 600 };
        await flushPromises();
        expect(viewport.nodeSizes.value).toEqual({});
        expect(first.fitView).not.toHaveBeenCalled();
        stop();
    });
});
