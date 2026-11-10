/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let wrapper: VueWrapper | null = null;

type Assignment = { id: string; contentLayoutId: string; salesChannelId?: string };

async function createWrapper(
    assignments: Assignment[] = [],
    isDisabled = false,
    options: {
        legacyId?: string | null;
        config?: Record<string, string>;
        category?: boolean;
        can?: (privilege: string) => boolean;
        cloneFailure?: boolean;
        configFailure?: boolean;
        layoutFailure?: string;
    } = {},
) {
    const collection = new Shopwell.Data.EntityCollection(
        '/blog_content_layout',
        'blog_content_layout',
        Shopwell.Context.api,
        new Shopwell.Data.Criteria(),
        assignments.map(
            (assignment) =>
                ({
                    blogId: 'blog-id',
                    createdAt: '2026-11-09T00:00:00.000+00:00',
                    ...assignment,
                }) as Entity<'blog_content_layout'>,
        ),
    );
    const blog = {
        id: 'blog-id',
        type: 'post',
        contentLayoutId: options.legacyId === undefined ? 'legacy-layout' : options.legacyId,
        contentLayouts: collection,
    };
    const category = { id: 'category-id', contentLayoutId: options.legacyId ?? null };
    const router = { push: jest.fn() };
    const clone = options.cloneFailure
        ? jest.fn().mockRejectedValue(new Error('Copy failed'))
        : jest.fn().mockResolvedValue({ id: 'copied-layout' });
    const getValues = options.configFailure
        ? jest.fn().mockRejectedValue(new Error('Configuration unavailable'))
        : jest.fn().mockResolvedValue(options.config ?? {});
    const get = jest
        .fn()
        .mockImplementation((id: string) =>
            id === options.layoutFailure
                ? Promise.reject(new Error('Layout unavailable'))
                : Promise.resolve({ id, name: `Name ${id}`, rootSource: id === 'legacy-layout' ? 'none' : 'blog' }),
        );
    wrapper = shallowMount(await wrapTestComponent('sw-blog-detail-layout', { sync: true }), {
        props: { ...(options.category ? { category } : { blog }), isDisabled },
        global: {
            provide: {
                repositoryFactory: { create: () => ({ create: () => ({ id: 'new-assignment' }), get, clone }) },
                systemConfigApiService: { getValues },
                acl: { can: options.can ?? (() => true) },
            },
            mocks: { $t: (key: string) => key, $router: router },
            stubs: {
                'mt-card': { props: ['isLoading'], template: '<div :data-loading="isLoading"><slot /></div>' },
                'mt-banner': { template: '<div><slot /></div>' },
                'mt-button': {
                    props: ['disabled'],
                    emits: ['click'],
                    template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                },
                'sw-entity-single-select': {
                    props: ['value', 'disabled'],
                    template: `<select :value="value" :disabled="disabled" @change="$emit('update:value', $event.target.value || null)"><option value="">Shared template</option><option value="new-layout">New layout</option><option value="global-layout">Global layout</option><option value="selected-layout">Selected layout</option></select>`,
                },
            },
        },
    });
    await flushPromises();
    return { wrapper, blog, category, router, clone, get, getValues };
}

describe('sw-blog-detail-layout', () => {
    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('assigns a Blog layout without changing the legacy fallback', async () => {
        const { wrapper: mounted, blog } = await createWrapper();
        await mounted.find('select').setValue('new-layout');
        expect(blog.contentLayouts.first()).toMatchObject({ blogId: 'blog-id', contentLayoutId: 'new-layout' });
        expect(blog.contentLayoutId).toBe('legacy-layout');
    });

    it('removes only the global override when clearing the selector', async () => {
        const { wrapper: mounted, blog } = await createWrapper([
            { id: 'global', contentLayoutId: 'global-layout' },
            { id: 'channel', salesChannelId: 'channel-id', contentLayoutId: 'channel-layout' },
        ]);
        await mounted.find('select').setValue('');
        expect(Array.from(blog.contentLayouts.getIds())).toEqual(['channel']);
        expect(blog.contentLayoutId).toBe('legacy-layout');
    });

    it('opens the selected layout with the current Blog preview context', async () => {
        const { wrapper: mounted, router } = await createWrapper([{ id: 'global', contentLayoutId: 'selected-layout' }]);
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.experience.studio.detail',
            params: { id: 'selected-layout' },
            query: { previewEntityId: 'blog-id' },
        });
    });

    it('warns that editing an assigned layout can change other pages using it', async () => {
        const { wrapper: mounted } = await createWrapper([{ id: 'global', contentLayoutId: 'selected-layout' }]);
        expect(mounted.text()).toContain('sw-blog.layout.sharedEditHint');
    });

    it('keeps assignments unchanged for read-only editors', async () => {
        const { wrapper: mounted, blog } = await createWrapper([], true);
        await mounted.find('select').setValue('new-layout');
        expect(blog.contentLayouts).toHaveLength(0);
    });

    it('shows the effective shared default by name and opens it for preview in Studio', async () => {
        const {
            wrapper: mounted,
            getValues,
            router,
        } = await createWrapper([], false, {
            legacyId: null,
            config: { 'core.content_system.default_blog_post_content_layout': 'post-default' },
        });
        expect(getValues).toHaveBeenCalledWith('core.content_system', null);
        expect(mounted.get('.sw-blog-detail-layout__name').text()).toBe('Name post-default');
        expect(mounted.get('.sw-blog-detail-layout__source').text()).toBe('sw-blog.layout.sourceDefault');
        expect(mounted.text()).toContain('sw-blog.layout.sharedEditHint');
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).toHaveBeenCalledWith(expect.objectContaining({ params: { id: 'post-default' } }));
    });

    it('represents the legacy layout before a configured shared default', async () => {
        const { wrapper: mounted, router } = await createWrapper([], false, {
            config: { 'core.content_system.default_blog_post_content_layout': 'post-default' },
        });
        expect(mounted.get('.sw-blog-detail-layout__name').text()).toBe('Name legacy-layout');
        expect(mounted.get('.sw-blog-detail-layout__source').text()).toBe('sw-blog.layout.sourceLegacy');
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).toHaveBeenCalledWith({ name: 'sw.experience.studio.detail', params: { id: 'legacy-layout' } });
    });

    it('restores the actual default while preserving sales-channel assignments', async () => {
        const { wrapper: mounted, blog } = await createWrapper(
            [
                { id: 'global', contentLayoutId: 'global-layout' },
                { id: 'channel', salesChannelId: 'channel-id', contentLayoutId: 'channel-layout' },
            ],
            false,
            { config: { 'core.content_system.default_blog_post_content_layout': 'post-default' } },
        );
        await mounted.get('.sw-blog-detail-layout__restore').trigger('click');
        await flushPromises();
        expect(Array.from(blog.contentLayouts.getIds())).toEqual(['channel']);
        expect(blog.contentLayoutId).toBeUndefined();
        expect(mounted.get('.sw-blog-detail-layout__name').text()).toBe('Name post-default');
    });

    it('copies a shared layout without its assignments and assigns the copy locally', async () => {
        const {
            wrapper: mounted,
            blog,
            clone,
            router,
        } = await createWrapper([], false, {
            legacyId: null,
            config: { 'core.content_system.default_blog_post_content_layout': 'post-default' },
        });
        await mounted.get('.sw-blog-detail-layout__copy').trigger('click');
        await flushPromises();
        expect(clone).toHaveBeenCalledWith(
            'post-default',
            { cloneChildren: false, overwrites: { name: 'sw-blog.layout.copyName' } },
            Shopwell.Context.api,
        );
        expect(blog.contentLayouts.first()?.contentLayoutId).toBe('copied-layout');
        expect(mounted.text()).toContain('sw-blog.layout.copySaveHint');
        expect(router.push).not.toHaveBeenCalled();
    });

    it('shows a copy failure and retains the inherited layout', async () => {
        const { wrapper: mounted, blog } = await createWrapper([], false, {
            legacyId: null,
            config: { 'core.content_system.default_blog_post_content_layout': 'post-default' },
            cloneFailure: true,
        });
        await mounted.get('.sw-blog-detail-layout__copy').trigger('click');
        await flushPromises();
        expect(blog.contentLayouts).toHaveLength(0);
        expect(mounted.text()).toContain('sw-blog.layout.copyError');
    });

    it('hides copy and edit actions without Experience Studio privileges', async () => {
        const { wrapper: mounted } = await createWrapper([], false, { can: () => false });
        expect(mounted.find('.sw-blog-detail-layout__copy').exists()).toBe(false);
        expect(mounted.find('.sw-blog-detail-layout__edit').exists()).toBe(false);
    });

    it('shows a configuration error instead of claiming that no default is configured', async () => {
        const { wrapper: mounted } = await createWrapper([], false, { legacyId: null, configFailure: true });
        expect(mounted.text()).toContain('sw-blog.layout.loadError');
        expect(mounted.find('.sw-blog-detail-layout__edit').exists()).toBe(false);
    });

    it('recovers from an unavailable layout after choosing another one', async () => {
        const { wrapper: mounted } = await createWrapper([{ id: 'global', contentLayoutId: 'missing-layout' }], false, {
            legacyId: null,
            layoutFailure: 'missing-layout',
        });
        expect(mounted.text()).toContain('sw-blog.layout.loadError');
        await mounted.find('select').setValue('new-layout');
        await flushPromises();
        expect(mounted.get('.sw-blog-detail-layout__name').text()).toBe('Name new-layout');
        expect(mounted.text()).not.toContain('sw-blog.layout.loadError');
    });

    it('clears loading when an assignment is removed during a layout request', async () => {
        const { wrapper: mounted, get } = await createWrapper([], false, { legacyId: null });
        let resolveLayout: (layout: { id: string; name: string }) => void = () => {};
        get.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveLayout = resolve;
                }),
        );
        await mounted.find('select').setValue('new-layout');
        expect(mounted.attributes('data-loading')).toBe('true');
        await mounted.get('.sw-blog-detail-layout__restore').trigger('click');
        resolveLayout({ id: 'new-layout', name: 'Old response' });
        await flushPromises();
        expect(mounted.attributes('data-loading')).toBe('false');
        expect(mounted.text()).not.toContain('Old response');
    });

    it('copies a legacy layout as a body layout without creating a Blog assignment', async () => {
        const { wrapper: mounted, blog } = await createWrapper();
        await mounted.get('.sw-blog-detail-layout__copy').trigger('click');
        await flushPromises();
        expect(blog.contentLayoutId).toBe('copied-layout');
        expect(blog.contentLayouts).toHaveLength(0);
    });

    it('labels the assignment field for a category page', async () => {
        const { wrapper: mounted } = await createWrapper([], false, { category: true });
        expect(mounted.find('select').attributes('label')).toBe('sw-blog.layout.categoryAssignment');
    });

    it('labels the assignment field for an article page', async () => {
        const { wrapper: mounted } = await createWrapper([], false, { legacyId: null });
        expect(mounted.find('select').attributes('label')).toBe('sw-blog.layout.articleAssignment');
    });

    it('uses the listing default for categories and copies it into the category override', async () => {
        const {
            wrapper: mounted,
            category,
            router,
        } = await createWrapper([], false, {
            category: true,
            config: { 'core.content_system.default_blog_listing_content_layout': 'listing-default' },
        });
        expect(mounted.get('.sw-blog-detail-layout__name').text()).toBe('Name listing-default');
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).toHaveBeenCalledWith({ name: 'sw.experience.studio.detail', params: { id: 'listing-default' } });
        await mounted.get('.sw-blog-detail-layout__copy').trigger('click');
        await flushPromises();
        expect(category.contentLayoutId).toBe('copied-layout');
        await mounted.get('.sw-blog-detail-layout__restore').trigger('click');
        expect(category.contentLayoutId).toBeUndefined();
    });
    it('keeps layout navigation locked while the parent is busy', async () => {
        const { wrapper: mounted, router } = await createWrapper([], true);
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).not.toHaveBeenCalled();
        await mounted.setProps({ isDisabled: false });
        await mounted.get('.sw-blog-detail-layout__edit').trigger('click');
        expect(router.push).toHaveBeenCalledTimes(1);
    });
    it('does not apply a late layout copy after the editor becomes busy', async () => {
        const { wrapper: mounted, clone, blog } = await createWrapper();
        let finishCopy: (value: { id: string }) => void = () => {};
        clone.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    finishCopy = resolve;
                }),
        );
        await mounted.get('.sw-blog-detail-layout__copy').trigger('click');
        await mounted.setProps({ isDisabled: true });
        finishCopy({ id: 'late-copy' });
        await flushPromises();
        expect(blog.contentLayoutId).toBe('legacy-layout');
        expect(mounted.find('.sw-blog-detail-layout__status').exists()).toBe(false);
    });
});
