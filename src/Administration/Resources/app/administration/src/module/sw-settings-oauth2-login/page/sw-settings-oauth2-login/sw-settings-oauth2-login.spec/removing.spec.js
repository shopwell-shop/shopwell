/**
 * @sw-package framework
 */
import { createWrapper, googleClient, setupPageHooks } from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should remove the configuration of a channel after the confirmation', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });

        wrapper.vm.onOpenRemoveModal(wrapper.vm.channels[0]);
        expect(wrapper.vm.removeTarget).toEqual(wrapper.vm.channels[0]);

        await wrapper.vm.onConfirmRemove();

        expect(repository.delete).toHaveBeenCalledWith('client-google');
        expect(wrapper.vm.removeTarget).toBeNull();
        expect(wrapper.vm.channels[0].id).toBeNull();
    });

    it('should keep the configuration and report a failed remove', async () => {
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper, repository } = await createWrapper({
            deleteError: new Error('Could not remove the channel'),
            clients: [googleClient],
        });

        wrapper.vm.onOpenRemoveModal(wrapper.vm.channels[0]);

        await wrapper.vm.onConfirmRemove();

        expect(repository.delete).toHaveBeenCalledWith('client-google');
        expect(wrapper.vm.removeTarget).toBe(wrapper.vm.channels[0]);
        expect(wrapper.vm.channels[0].id).toBe('client-google');
        expect(wrapper.vm.isSaving).toBe(false);

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not remove the channel',
        });

        notificationSpy.mockRestore();
    });

    it('should keep the configuration when the remove modal is closed instead of confirmed', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });

        wrapper.vm.onOpenRemoveModal(wrapper.vm.channels[0]);
        wrapper.vm.onRemoveModalChange(false);

        expect(wrapper.vm.removeTarget).toBeNull();
        expect(repository.delete).not.toHaveBeenCalled();
    });
});
