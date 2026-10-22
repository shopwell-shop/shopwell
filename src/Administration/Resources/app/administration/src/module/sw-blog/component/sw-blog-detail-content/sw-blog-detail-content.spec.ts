/**
 * @sw-package discovery
 */
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { registerBlogType, resetBlogTypes } from '../../service/blog-type.registry';

type ContentVm = {
    contentType: unknown;
    isUnknownType: boolean;
    isLoading: boolean;
    type: string;
    loadTypeComponent: () => Promise<void>;
};

type TypedWrapper = { vm: ContentVm } & VueWrapper;

const loadedComponent = { template: '<div class="loaded-type">post editor</div>' };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(blog: { type: string }): Promise<TypedWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-blog-detail-content', { sync: true }), {
        props: { blog },
        global: {
            stubs: {
                'sw-blog-detail-content-unknown': {
                    template: '<div class="unknown-type">unknown</div>',
                },
                'sw-skeleton': { template: '<div class="skeleton"></div>' },
            },
            mocks: {
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return wrapper as unknown as TypedWrapper;
}

describe('src/module/sw-blog/component/sw-blog-detail-content', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        resetBlogTypes();
    });

    it('renders the component a type registered', async () => {
        registerBlogType('post', () => Promise.resolve({ default: loadedComponent }));

        const wrapper = await createWrapper({ type: 'post' });
        await flushPromises();

        expect(wrapper.vm.isUnknownType).toBe(false);
        expect(wrapper.find('.loaded-type').exists()).toBe(true);
    });

    it('falls back to the unknown component when nobody registered the type', async () => {
        const wrapper = await createWrapper({ type: 'podcast' });
        await flushPromises();

        expect(wrapper.vm.isUnknownType).toBe(true);
        expect(wrapper.vm.contentType).toBeNull();
        expect(wrapper.find('.unknown-type').exists()).toBe(true);
    });

    it('drops a downloaded component when the type changed while it was loading', async () => {
        registerBlogType('post', () => Promise.resolve({ default: loadedComponent }));

        const wrapper = await createWrapper({ type: 'post' });

        wrapper.vm.contentType = null;
        await wrapper.setProps({ blog: { type: 'video' } });
        await flushPromises();

        expect(wrapper.vm.contentType).toBeNull();
        expect(wrapper.vm.isUnknownType).toBe(true);
    });
});
