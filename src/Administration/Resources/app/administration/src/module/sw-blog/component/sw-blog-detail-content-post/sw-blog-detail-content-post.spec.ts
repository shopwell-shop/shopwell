/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let activeWrapper: VueWrapper | null = null;

async function createWrapper(): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-blog-detail-content-post', { sync: true }), {
        props: { blog: { id: 'blog-1', type: 'post', content: '<p>Hello</p>' }, isDisabled: false },
        global: {
            stubs: {
                'sw-text-editor': {
                    template: '<textarea class="editor"></textarea>',
                    props: ['value', 'disabled', 'label'],
                },
            },
            mocks: {
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return wrapper;
}

describe('src/module/sw-blog/component/sw-blog-detail-content-post', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('renders the body editor of an article', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.editor').exists()).toBe(true);
    });
});
