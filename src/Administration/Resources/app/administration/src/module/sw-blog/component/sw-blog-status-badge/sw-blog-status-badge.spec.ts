/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let activeWrapper: VueWrapper | null = null;

async function createWrapper(publishedAt: string | null): Promise<VueWrapper> {
    const wrapper = shallowMount(await wrapTestComponent('sw-blog-status-badge', { sync: true }), {
        props: { publishedAt },
        global: {
            stubs: {
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot></slot></span>' },
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

describe('src/module/sw-blog/component/sw-blog-status-badge', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('shows a published entry as published', async () => {
        const wrapper = await createWrapper(null);

        expect(wrapper.text()).toBe('sw-blog.status.published');
        expect(wrapper.get('span').attributes('data-variant')).toBe('positive');
        expect(wrapper.text()).toBe('sw-blog.status.published');
    });

    it('shows an entry with a date in the past as published', async () => {
        const wrapper = await createWrapper('2000-01-01T00:00:00.000Z');

        expect(wrapper.text()).toBe('sw-blog.status.published');
    });

    it('shows an entry with a date in the future as scheduled', async () => {
        const wrapper = await createWrapper('2999-01-01T00:00:00.000Z');

        expect(wrapper.text()).toBe('sw-blog.status.scheduled');
        expect(wrapper.get('span').attributes('data-variant')).toBe('attention');
        expect(wrapper.text()).toBe('sw-blog.status.scheduled');
    });
});
