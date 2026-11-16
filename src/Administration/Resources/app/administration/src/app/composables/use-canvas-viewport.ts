/** @sw-package framework */
import type { Dimensions, VueFlowStore } from '@vue-flow/core';
import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';

type CanvasViewportOptions = {
    padding?: number;
    onSizesChange?: (sizes: Record<string, Dimensions>) => void;
};

/** Shared measurements and initial fit for native Vue Flow surfaces. @private */
export default function useCanvasViewport({ padding = 0.3, onSizesChange }: CanvasViewportOptions = {}) {
    const viewport = shallowRef<Pick<VueFlowStore, 'fitView' | 'zoomIn' | 'zoomOut'> | null>(null);
    const nodeSizes = ref<Record<string, Dimensions>>({});
    let stopMeasurements: (() => void) | undefined;
    let fitted = false;
    let generation = 0;

    function fit(): void {
        fitted = true;
        void viewport.value?.fitView({ padding, maxZoom: 1 });
    }

    function onInit(flow: VueFlowStore): void {
        stopMeasurements?.();
        const currentGeneration = ++generation;
        fitted = false;
        viewport.value = { fitView: flow.fitView, zoomIn: flow.zoomIn, zoomOut: flow.zoomOut };
        stopMeasurements = watch(
            () => ({
                nodes: flow.getNodes.value.map((node) => [node.id, node.dimensions.width, node.dimensions.height] as const),
                width: flow.dimensions.value.width,
                height: flow.dimensions.value.height,
            }),
            ({ nodes, width, height }) => {
                const sizes: Record<string, Dimensions> = {};

                for (const [id, nodeWidth, nodeHeight] of nodes) {
                    if (nodeWidth > 0 && nodeHeight > 0) {
                        sizes[id] = { width: nodeWidth, height: nodeHeight };
                    }
                }

                if (JSON.stringify(sizes) !== JSON.stringify(nodeSizes.value)) {
                    nodeSizes.value = sizes;
                    onSizesChange?.(sizes);
                }

                const measured = nodes.length > 0 && Object.keys(sizes).length === nodes.length;

                if (!fitted && width > 0 && height > 0 && measured) {
                    // Wait for measured dimensions to reach the owning layout before fitting.
                    fitted = true;
                    void nextTick(() => {
                        if (currentGeneration === generation) {
                            fit();
                        }
                    });
                }
            },
            { immediate: true, flush: 'post' },
        );
    }

    onScopeDispose(() => {
        generation += 1;
        stopMeasurements?.();
    });

    return {
        nodeSizes,
        onInit,
        fit,
        zoomIn: () => {
            void viewport.value?.zoomIn();
        },
        zoomOut: () => {
            void viewport.value?.zoomOut();
        },
    };
}
