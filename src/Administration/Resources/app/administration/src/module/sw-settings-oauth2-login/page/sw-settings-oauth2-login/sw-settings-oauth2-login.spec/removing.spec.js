/**
 * @sw-package framework
 */
import { createWrapper, googleClient, setupPageHooks } from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should remove the configuration of a channel after the confirmation', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });

        await wrapper.get('.sw-settings-oauth2-login__channel-reset').trigger('click');
        expect(wrapper.get('.sw-settings-oauth2-login__remove-modal-content').text()).toContain(
            'sw-settings-oauth2-login.channel.removeModalMessage',
        );
        await wrapper.get('mt-modal-action-stub').trigger('click');
        await flushPromises();
        expect(repository.delete).toHaveBeenCalledWith('client-google');
        expect(wrapper.find('mt-modal-action-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-oauth2-login__channel-reset').exists()).toBe(false);
    });

    it('should keep the configuration and report a failed remove', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper, repository } = await createWrapper({
            deleteError: new Error('Could not remove the channel'),
            clients: [googleClient],
        });

        await wrapper.get('.sw-settings-oauth2-login__channel-reset').trigger('click');
        await wrapper.get('mt-modal-action-stub').trigger('click');
        await flushPromises();
        expect(repository.delete).toHaveBeenCalledWith('client-google');
        expect(wrapper.find('mt-modal-action-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-oauth2-login__channel-reset').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-oauth2-login__save-action').attributes('disabled')).toBeUndefined();
        expect(notification).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not remove the channel',
        });
        notification.mockRestore();
    });

    it('should keep the configuration when the remove modal is closed instead of confirmed', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });

        await wrapper.get('.sw-settings-oauth2-login__channel-reset').trigger('click');
        await wrapper.get('.dismiss-modal').trigger('click');
        expect(wrapper.find('mt-modal-action-stub').exists()).toBe(false);
        expect(repository.delete).not.toHaveBeenCalled();
    });
});
