/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ privileges = [], saveAll = jest.fn(() => Promise.resolve()) } = {}) {
    return mount(await wrapTestComponent('sw-settings-user-permission', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $t: (key) => key,
            },
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'mt-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': {
                    template: '<button class="save-button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                    props: ['isLoading', 'processSuccess', 'disabled'],
                },
                'sw-system-config': {
                    template: '<div class="system-config"></div>',
                    props: ['domain'],
                    methods: {
                        saveAll,
                    },
                },
            },
        },
    });
}

describe('module/sw-settings-user-permission/page/sw-settings-user-permission', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should render the user permission system config domain', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.system-config').exists()).toBe(true);
    });

    it('should save all config values', async () => {
        const saveAll = jest.fn(() => Promise.resolve());
        wrapper = await createWrapper({ privileges: ['user.editor'], saveAll });
        await flushPromises();

        wrapper.vm.onSave();
        await flushPromises();

        expect(saveAll).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should disable saving without editor privileges', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.save-button').attributes('disabled')).toBeDefined();
    });
});
