/**
 * @sw-package after-sales
 */

import { flushPromises, mount } from '@vue/test-utils';
import SwSettingsOrderReturnIndex from './index';

type SettingsIndexVm = {
    isLoading: boolean;
    isSaveSuccessful: boolean;
    canEdit: boolean;
    acl: { can: jest.Mock };
    $refs: { systemConfig?: { saveAll: jest.Mock } };
    $router: { push: jest.Mock };
    $t: (key: string) => string;
    $createTitle: () => string;
    createNotificationSuccess: jest.Mock;
    createNotificationError: jest.Mock;
};

type SettingsIndex = {
    computed: {
        canEdit(this: SettingsIndexVm): boolean;
    };
    methods: {
        onSave(this: SettingsIndexVm): void;
        onSaveFinish(this: SettingsIndexVm): void;
        onLoadingChanged(this: SettingsIndexVm, loading: boolean): void;
        onOpenReasons(this: SettingsIndexVm): void;
    };
};

const page = SwSettingsOrderReturnIndex as unknown as SettingsIndex;

function createVm(canEdit = true, saveAll: jest.Mock = jest.fn().mockResolvedValue(undefined)): SettingsIndexVm {
    return {
        isLoading: false,
        isSaveSuccessful: false,
        canEdit,
        acl: { can: jest.fn(() => canEdit) },
        $refs: { systemConfig: { saveAll } },
        $router: { push: jest.fn() },
        $t: (key: string) => key,
        $createTitle: () => 'title',
        createNotificationSuccess: jest.fn(),
        createNotificationError: jest.fn(),
    };
}

describe('src/module/sw-settings-order-return/page/sw-settings-order-return-index/index', () => {
    it('requires the standard system config privilege to save the rules', () => {
        const vm = createVm(true);
        expect(page.computed.canEdit.call(vm)).toBe(true);
        expect(vm.acl.can).toHaveBeenCalledWith('system.system_config');
        expect(page.computed.canEdit.call(createVm(false))).toBe(false);
    });

    it('uses the standard sales channel aware configuration component', async () => {
        const wrapper = mount(await wrapTestComponent('sw-settings-order-return-index', { sync: true }), {
            global: {
                provide: { acl: { can: () => true } },
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-page': { template: '<div><slot name="content"/></div>' },
                    'sw-card-view': { template: '<div><slot/></div>' },
                    'sw-extension-component-section': true,
                    'sw-system-config': {
                        name: 'sw-system-config',
                        props: { domain: String, salesChannelSwitchable: Boolean },
                        template: '<div/>',
                    },
                },
            },
        });

        expect(wrapper.findComponent({ name: 'sw-system-config' }).props()).toMatchObject({
            domain: 'core.orderReturn',
            salesChannelSwitchable: true,
        });
        expect(wrapper.find('.sw-settings-order-return-index__reasons').exists()).toBe(false);
        wrapper.unmount();
    });

    it('saves the whole config domain and reports the success', async () => {
        const vm = createVm();
        const saveAll = vm.$refs.systemConfig?.saveAll as jest.Mock;

        page.methods.onSave.call(vm);
        expect(vm.isLoading).toBe(true);

        await flushPromises();

        expect(saveAll).toHaveBeenCalledTimes(1);
        expect(vm.isSaveSuccessful).toBe(true);
        expect(vm.isLoading).toBe(false);
        expect(vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-order-return.notification.saveSuccess',
        });
    });

    it('does not save without the privilege', () => {
        const vm = createVm(false);
        const saveAll = vm.$refs.systemConfig?.saveAll as jest.Mock;

        page.methods.onSave.call(vm);

        expect(saveAll).not.toHaveBeenCalled();
        expect(vm.isLoading).toBe(false);
    });

    it('reports the message of the server when saving fails', async () => {
        const vm = createVm(true, jest.fn().mockRejectedValue(new Error('the deadline must be positive')));

        page.methods.onSave.call(vm);
        await flushPromises();

        expect(vm.isSaveSuccessful).toBe(false);
        expect(vm.isLoading).toBe(false);
        expect(vm.createNotificationError).toHaveBeenCalledWith({ message: 'the deadline must be positive' });
    });

    it('forgets the saved state once the page moved on and mirrors the loading of the config', () => {
        const vm = createVm();
        vm.isSaveSuccessful = true;

        page.methods.onSaveFinish.call(vm);
        expect(vm.isSaveSuccessful).toBe(false);

        page.methods.onLoadingChanged.call(vm, true);
        expect(vm.isLoading).toBe(true);
    });

    it('opens the reason list of the module', () => {
        const vm = createVm();

        page.methods.onOpenReasons.call(vm);

        expect(vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.order.return.reason' });
    });
});
