import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const POST_KEY = 'core.content_system.default_blog_post_content_layout';
const VIDEO_KEY = 'core.content_system.default_blog_video_content_layout';
let activeWrapper: VueWrapper | null = null;

async function createWrapper(config: Record<string, string> = {}, canConfigure = true) {
    const getValues = jest.fn().mockResolvedValue(config);
    const saveValues = jest.fn().mockResolvedValue(undefined);
    const wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-assignment-modal', { sync: true }), {
        props: { layoutId: 'layout-1', rootSource: 'blog', allowEdit: true },
        global: {
            stubs: {
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-switch': {
                    props: ['modelValue', 'label', 'disabled'],
                    emits: ['update:model-value'],
                    template:
                        '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:model-value\', $event.target.checked)" />{{ label }}</label>',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'sw-entity-multi-id-select': true,
            },
            provide: {
                repositoryFactory: { create: () => ({ search: jest.fn().mockResolvedValue([]) }) },
                systemConfigApiService: { getValues, saveValues },
                acl: { can: () => canConfigure },
            },
            mocks: { $t: (key: string) => key },
        },
    });
    activeWrapper = wrapper;
    await flushPromises();

    return { wrapper, getValues, saveValues };
}

describe('Experience Studio Blog layout defaults', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('shows separate selected states for the post and video defaults', async () => {
        const { wrapper } = await createWrapper({ [POST_KEY]: 'layout-1', [VIDEO_KEY]: 'other-layout' });
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(switches).toHaveLength(2);
        expect((switches[0].element as HTMLInputElement).checked).toBe(true);
        expect((switches[1].element as HTMLInputElement).checked).toBe(false);
    });

    it.each([
        ['post', 0, POST_KEY],
        ['video', 1, VIDEO_KEY],
    ])('sets the %s default independently', async (type, index, key) => {
        const { wrapper, saveValues } = await createWrapper();

        await wrapper.findAll('input[type="checkbox"]')[index].setValue(true);
        await wrapper.get('.sw-experience-studio-assignment-modal__save').trigger('click');
        await flushPromises();

        expect(saveValues).toHaveBeenCalledWith({ [key]: 'layout-1' });
    });

    it.each([
        ['post', 0, POST_KEY],
        ['video', 1, VIDEO_KEY],
    ])('clears the %s default independently', async (type, index, key) => {
        const { wrapper, saveValues } = await createWrapper({ [POST_KEY]: 'layout-1', [VIDEO_KEY]: 'layout-1' });

        await wrapper.findAll('input[type="checkbox"]')[index].setValue(false);
        await wrapper.get('.sw-experience-studio-assignment-modal__save').trigger('click');
        await flushPromises();

        expect(saveValues).toHaveBeenCalledWith({ [key]: null });
    });

    it('saves opposite changes to both defaults in one standard config request', async () => {
        const { wrapper, saveValues } = await createWrapper({ [POST_KEY]: 'layout-1', [VIDEO_KEY]: 'other-layout' });

        const switches = wrapper.findAll('input[type="checkbox"]');
        await switches[0].setValue(false);
        await switches[1].setValue(true);
        await wrapper.get('.sw-experience-studio-assignment-modal__save').trigger('click');
        await flushPromises();

        expect(saveValues).toHaveBeenCalledWith({ [POST_KEY]: null, [VIDEO_KEY]: 'layout-1' });
    });

    it('leaves unchanged defaults and the generic fallback untouched', async () => {
        const { wrapper, saveValues } = await createWrapper({
            [POST_KEY]: 'layout-1',
            [VIDEO_KEY]: 'other-layout',
            'core.content_system.default_blog_content_layout': 'legacy-layout',
        });

        await wrapper.get('.sw-experience-studio-assignment-modal__save').trigger('click');
        await flushPromises();

        expect(saveValues).not.toHaveBeenCalled();
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
    });

    it('hides both default switches without system configuration permission', async () => {
        const { wrapper, getValues } = await createWrapper({}, false);

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
        expect(getValues).not.toHaveBeenCalled();
    });
});
