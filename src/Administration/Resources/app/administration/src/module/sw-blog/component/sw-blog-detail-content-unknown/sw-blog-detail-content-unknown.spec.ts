/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type UnknownVm = { type: string };
type TypedWrapper = { vm: UnknownVm } & VueWrapper;

let activeWrapper: VueWrapper | null = null;

async function createWrapper(): Promise<TypedWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-blog-detail-content-unknown', { sync: true }), {
        props: { blog: { id: 'blog-1', type: 'podcast', publishedAt: '2026-01-01T00:00:00.000Z' } },
        global: {
            stubs: {
                'mt-banner': {
                    template: '<div class="banner"><slot></slot></div>',
                    props: ['title', 'variant'],
                },
            },
            mocks: {
                $t: (key: string, params?: Record<string, string>) => {
                    return params ? `${key}:${JSON.stringify(params)}` : key;
                },
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return wrapper as unknown as TypedWrapper;
}

describe('src/module/sw-blog/component/sw-blog-detail-content-unknown', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('says that the type is provided by a plugin instead of white-screening', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.type).toBe('podcast');
        expect(wrapper.find('.banner').exists()).toBe(true);
    });

    it('keeps the raw fields of the entry readable', async () => {
        const wrapper = await createWrapper();
        const text = wrapper.text();

        expect(text).toContain('podcast');
        expect(text).toContain('blog-1');
        expect(text).toContain('2026-01-01T00:00:00.000Z');
    });
});
