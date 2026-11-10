import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const computed = (detailComponent as unknown as { computed: Record<string, () => unknown> }).computed;

describe('Experience Studio blog layouts', () => {
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

    it('uses the requested preview channel before loading a default channel', async () => {
        const search = jest.fn();
        const vm = {
            $route: { query: { previewSalesChannelId: 'channel-2' } },
            previewSalesChannelId: null,
            resolvedPreviewContext: null,
            salesChannelRepository: { search },
        };

        await methods.loadDefaultPreviewSalesChannel.call(vm);

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
