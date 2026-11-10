/**
 * @sw-package discovery
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';

type Video = { posterMediaId: string | null; videoUrl: string; provider: string | null };

let activeWrapper: VueWrapper | null = null;

async function createWrapper(video: Video | null): Promise<VueWrapper> {
    const wrapper = shallowMount(await wrapTestComponent('sw-blog-detail-content-video', { sync: true }), {
        props: { blog: { type: 'video' }, video: video === null ? null : reactive(video) },
        global: {
            stubs: {
                'mt-empty-state': { template: '<div class="empty"></div>' },
                'mt-text-field': true,
                'mt-select': {
                    props: ['options', 'modelValue'],
                    template:
                        '<select :value="modelValue"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                },
                'mt-number-field': true,
                'sw-media-compact-upload-v2': {
                    props: ['source'],
                    template:
                        '<section><output>{{ source }}</output><button class="select-poster" @click="$emit(\'selection-change\', [{ id: \'media-1\' }])">Select</button><button class="remove-poster" @click="$emit(\'delete-item\')">Remove</button></section>',
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

        expect(wrapper.findAll('option').map((option) => option.attributes('value'))).toEqual([
            'youtube',
            'vimeo',
            'self-hosted',
        ]);
    });

    it('writes the chosen poster into the video row', async () => {
        const video = { posterMediaId: null, videoUrl: '', provider: 'youtube' };
        const wrapper = await createWrapper(video);

        await wrapper.get('.select-poster').trigger('click');

        expect(video.posterMediaId).toBe('media-1');
        expect(wrapper.get('output').text()).toBe('media-1');
    });

    it('clears the poster when the image is removed', async () => {
        const video = { posterMediaId: 'media-1', videoUrl: '', provider: 'youtube' };
        const wrapper = await createWrapper(video);

        await wrapper.get('.remove-poster').trigger('click');

        expect(video.posterMediaId).toBeNull();
        expect(wrapper.get('output').text()).toBe('');
    });
    it('ignores late media callbacks after editing has been disabled', async () => {
        const video = { posterMediaId: 'existing-media', videoUrl: '', provider: 'youtube' };
        const wrapper = await createWrapper(video);
        await wrapper.setProps({ isDisabled: true });
        await wrapper.get('.select-poster').trigger('click');
        await wrapper.get('.remove-poster').trigger('click');
        expect(video.posterMediaId).toBe('existing-media');
    });
});
