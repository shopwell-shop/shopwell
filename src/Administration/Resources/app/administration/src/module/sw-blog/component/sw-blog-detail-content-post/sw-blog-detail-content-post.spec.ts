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
                'mt-text-editor': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<textarea class="meteor-editor" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)"></textarea>',
                },
                'sw-text-editor': {
                    template:
                        '<textarea class="editor" :value="value" :disabled="disabled" @input="$emit(\'update:value\', $event.target.value)"></textarea>',
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

    // @deprecated tag:v6.8.0 - The legacy editor is replaced by Meteor.
    it.deprecated('METEOR_TEXT_EDITOR')('renders the body editor of an article', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.editor').exists()).toBe(true);
        await wrapper.get('.editor').setValue('<p>Updated</p>');
        expect((wrapper.props() as { blog: { content: string } }).blog.content).toBe('<p>Updated</p>');
    });
    it.activeFeatureFlags(['METEOR_TEXT_EDITOR'])('edits with Meteor and respects the disabled state', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.editor').exists()).toBe(false);
        await wrapper.get('.meteor-editor').setValue('<p>Meteor body</p>');
        expect((wrapper.props() as { blog: { content: string } }).blog.content).toBe('<p>Meteor body</p>');
        await wrapper.setProps({ isDisabled: true });
        expect(wrapper.get('.meteor-editor').attributes('disabled')).toBeDefined();
    });
});
