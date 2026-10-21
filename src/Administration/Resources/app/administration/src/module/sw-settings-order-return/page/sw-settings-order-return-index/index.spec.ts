/**
 * @sw-package after-sales
 */

import { flushPromises } from '@vue/test-utils';
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
    it('only lets an editor of the returns save the rules', () => {
        expect(page.computed.canEdit.call(createVm(true))).toBe(true);
        expect(page.computed.canEdit.call(createVm(false))).toBe(false);
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
