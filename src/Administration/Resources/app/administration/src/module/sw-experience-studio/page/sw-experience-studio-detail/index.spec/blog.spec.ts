import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const computed = (detailComponent as unknown as { computed: Record<string, () => unknown> }).computed;

describe('Experience Studio blog layouts', () => {
    it('localizes built-in names in real preview frames and preserves merchant names', () => {
        const layout = { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' };
        const frame = { id: 'frame-1', layoutId: layout.id, viewport: 'desktop' };
        const vm = {
            $t: (key: string, params?: { name?: string }) => params?.name ?? key,
            frames: [frame],
            openLayouts: [{ id: layout.id, layout }],
            activeFrameId: frame.id,
            currentViewport: 'desktop',
            selectedElementId: null,
            previewEntityId: null,
            previewLanguageContext: null,
            layoutFrames: () => [frame],
            previewFrameAriaLabel: methods.previewFrameAriaLabel,
        };

        const frames = computed.canvasFrames.call(vm) as Array<{ label: string; ariaLabel: string }>;
        expect(frames[0].label).toBe('sw-experience-studio.builtinLayouts.defaultBlogPost');
        expect(frames[0].ariaLabel).toBe('sw-experience-studio.builtinLayouts.defaultBlogPost');

        layout.name = 'My article design';
        const renamed = computed.canvasFrames.call(vm) as Array<{ label: string; ariaLabel: string }>;
        expect(renamed[0].label).toBe('My article design');
        expect(renamed[0].ariaLabel).toBe('My article design');
    });

    it('loads blog assignments with a layout', () => {
        const criteria = computed.layoutLoadCriteria.call({}) as { associations: Array<{ association: string }> };

        expect(criteria.associations.map(({ association }) => association)).toContain('blogContentLayouts');
    });

    it('previews the assigned blog and its sales channel', () => {
        const vm = {
            getFirstAssociationEntry: methods.getFirstAssociationEntry,
            getLayoutRootSource: methods.getLayoutRootSource,
            resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
        };

        expect(
            methods.resolvePreviewContext.call(vm, {
                rootSource: 'blog',
                extensions: { blogContentLayouts: [{ blogId: 'blog-1', salesChannelId: 'channel-1' }] },
            }),
        ).toEqual({ entityType: 'blog', entityId: 'blog-1', salesChannelId: 'channel-1' });
    });

    it('keeps the entry chosen on the Blog detail page as preview context', () => {
        const vm = {
            $route: { query: { previewEntityId: 'blog-2', previewSalesChannelId: 'channel-2' } },
            previewEntityId: null,
            previewSalesChannelId: null,
            resolvedPreviewContext: { entityType: 'blog', entityId: 'blog-1', salesChannelId: 'channel-1' },
        };

        methods.applyPreviewContextDefaults.call(vm);

        expect(vm.previewEntityId).toBe('blog-2');
        expect(vm.previewSalesChannelId).toBe('channel-2');
    });

    it('keeps a requested preview channel when applying frame preview defaults', () => {
        const search = jest.fn();
        const vm = {
            $route: { query: { previewSalesChannelId: 'channel-2' } },
            previewSalesChannelId: null,
            resolvedPreviewContext: null,
            salesChannelRepository: { search },
        };

        methods.applyPreviewContextDefaults.call(vm);

        expect(vm.previewSalesChannelId).toBe('channel-2');
        expect(search).not.toHaveBeenCalled();
    });

    it('ignores malformed preview query values', () => {
        const vm = {
            $route: { query: { previewEntityId: ['blog-2'], previewSalesChannelId: '' } },
            previewEntityId: null,
            previewSalesChannelId: null,
            resolvedPreviewContext: { entityType: 'blog', entityId: 'blog-1', salesChannelId: 'channel-1' },
        };

        methods.applyPreviewContextDefaults.call(vm);

        expect(vm.previewEntityId).toBe('blog-1');
        expect(vm.previewSalesChannelId).toBe('channel-1');
    });

    it('identifies blog layout options with the Blog icon', () => {
        expect(methods.getLayoutTypeIcon.call({}, 'blog')).toBe('regular-image-text');
    });
});
