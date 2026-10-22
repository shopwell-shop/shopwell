/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type BadgeVm = { status: string; variant: string; labelKey: string };
type TypedWrapper = { vm: BadgeVm } & VueWrapper;

let activeWrapper: VueWrapper | null = null;

async function createWrapper(publishedAt: string | null): Promise<TypedWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-blog-status-badge', { sync: true }), {
        props: { publishedAt },
        global: {
            stubs: {
                'mt-badge': { template: '<div class="mt-badge-stub">{{ $t(labelKey) }}</div>', props: ['labelKey'] },
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

describe('src/module/sw-blog/component/sw-blog-status-badge', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('shows a published entry as published', async () => {
        const wrapper = await createWrapper(null);

        expect(wrapper.vm.status).toBe('published');
        expect(wrapper.vm.variant).toBe('positive');
        expect(wrapper.vm.labelKey).toBe('sw-blog.status.published');
    });

    it('shows an entry with a date in the past as published', async () => {
        const wrapper = await createWrapper('2000-01-01T00:00:00.000Z');

        expect(wrapper.vm.status).toBe('published');
    });

    it('shows an entry with a date in the future as scheduled', async () => {
        const wrapper = await createWrapper('2999-01-01T00:00:00.000Z');

        expect(wrapper.vm.status).toBe('scheduled');
        expect(wrapper.vm.variant).toBe('attention');
        expect(wrapper.vm.labelKey).toBe('sw-blog.status.scheduled');
    });
});
