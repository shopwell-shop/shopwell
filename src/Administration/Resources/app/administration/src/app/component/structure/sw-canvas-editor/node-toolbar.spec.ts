/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { nextTick } from 'vue';
import type CanvasEditor from './index';

const originalResizeObserver = window.ResizeObserver;
const flowStyles = document.createElement('style');
flowStyles.textContent = '.vue-flow__pane { z-index: 1; }';

async function createWrapper() {
    const wrapper = mount<Awaited<ReturnType<typeof wrapTestComponent>>, typeof CanvasEditor>(
        await wrapTestComponent('sw-canvas-editor', { sync: true }),
        {
            attachTo: document.body,
            props: {
                nodes: [{ id: 'card', position: { x: 40, y: 80 }, data: {} }],
            },
            slots: { 'node-tools': '<button class="edit-card">Edit card</button>' },
        },
    );
    await flushPromises();

    return wrapper;
}

describe('canvas node toolbar', () => {
    beforeAll(() => {
        document.head.append(flowStyles);
    });

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
        jest.useRealTimers();
        jest.restoreAllMocks();
        window.ResizeObserver = originalResizeObserver;
    });

    afterAll(() => {
        flowStyles.remove();
    });

    it('shows tools on hover and keeps them reachable while crossing from the node', async () => {
        const wrapper = await createWrapper();
        const flow = wrapper.getComponent(VueFlow);
        expect(wrapper.find('.edit-card').exists()).toBe(false);

        flow.vm.$emit('nodeMouseEnter', { node: { id: 'card' } });
        await nextTick();
        expect(wrapper.get('.edit-card').text()).toBe('Edit card');

        jest.useFakeTimers();
        flow.vm.$emit('nodeMouseLeave', { node: { id: 'card' } });
        await nextTick();
        await wrapper.get('.sw-canvas-editor__node-tools').trigger('mouseenter');
        jest.advanceTimersByTime(200);
        await nextTick();
        expect(wrapper.find('.edit-card').exists()).toBe(true);

        await wrapper.get('.sw-canvas-editor__node-tools').trigger('mouseleave');
        jest.advanceTimersByTime(200);
        await nextTick();
        expect(wrapper.find('.edit-card').exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps tools open when focus moves from the node to a toolbar button', async () => {
        const wrapper = await createWrapper();
        wrapper.get<HTMLButtonElement>('.vue-flow__node[data-id="card"]').element.focus();
        await nextTick();
        expect(wrapper.find('.edit-card').exists()).toBe(true);

        wrapper.get<HTMLButtonElement>('.edit-card').element.focus();
        await nextTick();
        expect(document.activeElement).toBe(wrapper.get('.edit-card').element);

        wrapper.get<HTMLButtonElement>('.edit-card').element.blur();
        await nextTick();
        expect(wrapper.find('.edit-card').exists()).toBe(false);
        wrapper.unmount();
    });

    it('anchors tools through the official toolbar outside the scaled graph', async () => {
        const wrapper = await createWrapper();
        const flow = wrapper.getComponent(VueFlow);
        const instance = useVueFlow(flow.props('id'));
        instance.applyNodeChanges([{ id: 'card', type: 'select', selected: true }]);
        await nextTick();
        const toolbar = wrapper.get('.vue-flow__node-toolbar');
        expect(toolbar.element.closest('.vue-flow__transformationpane')).toBeNull();

        instance.applyNodeChanges([{ id: 'card', type: 'dimensions', dimensions: { width: 800, height: 600 } }]);
        await instance.setViewport({ x: 20, y: 30, zoom: 0.5 });
        await nextTick();

        expect(toolbar.attributes('style')).toContain('translate(450px, 70px)');
        expect(toolbar.attributes('style')).not.toContain('scale(');
        wrapper.unmount();
    });

    it('keeps toolbars isolated when canvases contain matching node identifiers', async () => {
        const first = await createWrapper();
        const second = await createWrapper();

        first.getComponent(VueFlow).vm.$emit('nodeMouseEnter', { node: { id: 'card' } });
        await nextTick();

        expect(first.find('.edit-card').exists()).toBe(true);
        expect(second.find('.edit-card').exists()).toBe(false);
        first.unmount();
        second.unmount();
    });
});
