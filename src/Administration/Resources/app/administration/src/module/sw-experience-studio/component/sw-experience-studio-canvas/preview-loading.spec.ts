import { mount } from '@vue/test-utils';
import { h } from 'vue';
import type { Component } from 'vue';
import debounce from 'lodash/debounce';
import ContentSystemPreviewApiService from 'src/core/service/api/content-system-preview.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

const request = jest.fn<Promise<string>, Parameters<ContentSystemPreviewApiService['previewEntityUrl']>>();
const previewCreated = jest.fn();

async function createWrapper() {
    const preview = {
        extends: await wrapTestComponent('sw-experience-studio-preview', { sync: true }),
        created: previewCreated,
    } as Component;
    const frames = [0, 1600, 3200].map((x, index) => ({
        id: `layout-${index}`,
        layoutId: `layout-${index}`,
        x,
        y: 0,
        width: 500,
        height: 400,
        viewport: 'desktop',
        previewEntityId: 'product',
    }));
    const wrapper = mount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        props: {
            frames: frames.map((frame, index) => ({
                id: frame.layoutId,
                layout: {
                    id: frame.layoutId,
                    name: frame.layoutId,
                    layout: [{ id: `element-${index}`, component: 'Sw:Content:Text' }],
                },
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: 'product',
                isActive: false,
            })),
            workspaceConfiguration: {
                frames,
                previewSalesChannelId: 'channel',
                canvas: { panX: 0, panY: 0, zoom: 1 },
            },
        },
        slots: {
            preview: ({ frame }: { frame: { layout: unknown } }) =>
                h(preview, { layout: frame.layout, salesChannelId: 'channel', entityType: 'product', entityId: 'product' }),
        },
    });
    expect(previewCreated).not.toHaveBeenCalled();
    await flushPromises();
    const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
    Object.defineProperties(stage, {
        clientWidth: { value: 1000 },
        clientHeight: { value: 800 },
    });
    jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width: 1000, height: 800 } as DOMRect);
    await wrapper.setData({ canvasZoom: 0.99 });
    await flushPromises();

    return wrapper;
}

describe('Experience Studio canvas loads only visible previews', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeAll(() => {
        const client = createHTTPClient(Shopwell.Context.api);
        const service = new ContentSystemPreviewApiService(client, createLoginService(client, Shopwell.Context.api));
        service.previewEntityUrl = request;

        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- Register the local preview service stub for this suite.
        Shopwell.Service().register('contentSystemPreviewService', () => service);
    });

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['nextTick', 'setImmediate'] });
        jest.spyOn(Shopwell.Utils, 'debounce').mockImplementation(debounce);
        request.mockReset().mockResolvedValue('https://storefront.local/preview');
        previewCreated.mockClear();
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it('restores geometry before mounting previews and requests only the initially visible layout', async () => {
        wrapper = await createWrapper();
        expect(wrapper.findAll('[data-frame-id]')).toHaveLength(3);
        expect(previewCreated).toHaveBeenCalledTimes(1);
        expect(request).not.toHaveBeenCalled();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(request).toHaveBeenCalledTimes(1);
        expect(request.mock.calls[0][0].layout[0]).toEqual(expect.objectContaining({ id: 'element-0' }));
    });

    it('cancels the old visible preview request when panning hides it before its debounce expires', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ canvasPanX: -1600 });
        await flushPromises();
        expect(previewCreated).toHaveBeenCalledTimes(2);
        expect(request).not.toHaveBeenCalled();
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(wrapper.findAll('[data-frame-id]')).toHaveLength(3);
        expect(request).toHaveBeenCalledTimes(1);
        expect(request.mock.calls[0][0].layout[0]).toEqual(expect.objectContaining({ id: 'element-1' }));
    });
});
