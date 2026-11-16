/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import type { NodeMouseEvent } from '@vue-flow/core';
import { defineComponent, h, markRaw, ref } from 'vue';
import type CanvasEditor from './index';

const originalResizeObserver = window.ResizeObserver;

describe('native canvas extensions', () => {
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

    it('renders independent native node and edge slots without a projection registry', async () => {
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: {
                    nodes: [
                        { id: 'image', type: 'asset', position: { x: 0, y: 0 }, data: { title: 'Product image' } },
                        { id: 'note', type: 'note', position: { x: 300, y: 0 }, data: { title: 'Caption' } },
                    ],
                    edges: [{ id: 'link', type: 'asset-link', source: 'image', target: 'note', data: { title: 'Uses' } }],
                },
                slots: {
                    'node-asset': '<article class="asset">{{ params.data.title }}</article>',
                    'node-note': '<aside class="note">{{ params.data.title }}</aside>',
                    'edge-asset-link': '<path class="asset-link" :data-label="params.data.title" />',
                },
            },
        );
        await flushPromises();

        expect(wrapper.get('.asset').text()).toBe('Product image');
        expect(wrapper.get('.note').text()).toBe('Caption');
        expect(wrapper.get('.asset-link').attributes('data-label')).toBe('Uses');
        expect(
            wrapper
                .getComponent(VueFlow)
                .props('nodes')
                ?.map((node) => node.type),
        ).toEqual(['asset', 'note']);
        wrapper.unmount();
    });

    it('accepts native component maps and exposes native hooks to child extensions', async () => {
        const clicked = jest.fn<void, [NodeMouseEvent]>();
        const showExtension = ref(true);
        const extension = defineComponent({
            setup() {
                useVueFlow().onNodeClick(clicked);
                return {};
            },
            template: '<span class="native-extension" />',
        });
        const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
            await wrapTestComponent('sw-canvas-editor', { sync: true }),
            {
                props: {
                    nodes: [{ id: 'asset', type: 'asset', position: { x: 0, y: 0 }, data: {} }],
                    edges: [],
                },
                attrs: { nodeTypes: { asset: markRaw(defineComponent({ template: '<article>Asset</article>' })) } },
                slots: { default: () => (showExtension.value ? h(extension) : null) },
            },
        );
        await flushPromises();

        await wrapper.get('.vue-flow__node[data-id="asset"]').trigger('click');

        expect(wrapper.get('article').text()).toBe('Asset');
        expect(clicked).toHaveBeenCalledTimes(1);
        expect(clicked.mock.calls[0][0].node.id).toBe('asset');

        showExtension.value = false;
        await flushPromises();
        await wrapper.get('.vue-flow__node[data-id="asset"]').trigger('click');
        expect(clicked).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
