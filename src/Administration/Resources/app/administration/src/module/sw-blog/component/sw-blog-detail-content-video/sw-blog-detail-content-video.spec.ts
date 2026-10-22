/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type VideoVm = {
    providerOptions: Array<{ value: string; label: string }>;
    posterSource: string | null;
    onPosterSelected: (media: Array<{ id: string }>) => void;
    onPosterRemoved: () => void;
};

type TypedWrapper = { vm: VideoVm } & VueWrapper;

type Video = { posterMediaId: string | null; videoUrl: string; provider: string | null };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(video: Video | null): Promise<TypedWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-blog-detail-content-video', { sync: true }), {
        props: { blog: { type: 'video' }, video },
        global: {
            stubs: {
                'mt-empty-state': { template: '<div class="empty"></div>' },
                'mt-text-field': true,
                'mt-select': true,
                'mt-number-field': true,
                'sw-media-compact-upload-v2': true,
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

describe('src/module/sw-blog/component/sw-blog-detail-content-video', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('says so when the entry has no video row yet', async () => {
        const wrapper = await createWrapper(null);

        expect(wrapper.find('.empty').exists()).toBe(true);
    });

    it('offers the three platforms the core knows', async () => {
        const wrapper = await createWrapper({ posterMediaId: null, videoUrl: '', provider: 'youtube' });

        expect(wrapper.vm.providerOptions.map((option) => option.value)).toEqual(['youtube', 'vimeo', 'self-hosted']);
    });

    it('writes the chosen poster into the video row', async () => {
        const video = { posterMediaId: null, videoUrl: '', provider: 'youtube' };
        const wrapper = await createWrapper(video);

        wrapper.vm.onPosterSelected([{ id: 'media-1' }]);

        expect(video.posterMediaId).toBe('media-1');
        expect(wrapper.vm.posterSource).toBe('media-1');
    });

    it('clears the poster when the image is removed', async () => {
        const video = { posterMediaId: 'media-1', videoUrl: '', provider: 'youtube' };
        const wrapper = await createWrapper(video);

        wrapper.vm.onPosterRemoved();

        expect(video.posterMediaId).toBeNull();
    });
});
