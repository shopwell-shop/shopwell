/**
 * @sw-package framework
 */
import {
    createWrapper,
    googleClient,
    keycloakChannel,
    officialAccountChannel,
    setupPageHooks,
} from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should flag an enabled channel without credentials in the tabs', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.channelTabs).toEqual([
            { label: 'sso.oauth2.channel.google', name: 'google', hasError: false },
            { label: 'sso.oauth2.channel.wechat', name: 'wechat', hasError: false },
        ]);

        wrapper.vm.channels[1].enabled = true;

        expect(wrapper.vm.channelTabs[1].hasError).toBe(true);
    });

    it('should flag an enabled channel whose own required field is empty', async () => {
        const { wrapper } = await createWrapper({ channels: [keycloakChannel] });
        const [keycloak] = wrapper.vm.channels;

        keycloak.enabled = true;
        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[0], 'keycloak-id');
        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[1], 'keycloak-secret');

        expect(wrapper.vm.channelTabs[0].hasError).toBe(true);

        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[2], 'https://keycloak.test');

        expect(wrapper.vm.channelTabs[0].hasError).toBe(false);
    });

    it('should warn and switch to the channel when an enabled channel has an empty required field', async () => {
        const { wrapper, repository } = await createWrapper();
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.channels[1].enabled = true;

        await wrapper.vm.onSave();

        expect(notification).toHaveBeenCalled();
        expect(wrapper.vm.activeChannel).toBe('wechat');
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should not store a channel that was never configured', async () => {
        const { wrapper, repository } = await createWrapper();

        await wrapper.vm.onSave();

        expect(repository.create).not.toHaveBeenCalled();
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should not store a channel whose fields are only on the defaults the server sends', async () => {
        const { wrapper, repository } = await createWrapper();
        const [google] = wrapper.vm.channels;

        // writing the default back is not a configuration
        wrapper.vm.setFieldValue(google, google.formFields[0], '');

        expect(wrapper.vm.isChannelConfigured(google)).toBe(false);

        await wrapper.vm.onSave();

        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should update the configured channels and create the newly filled one', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });
        const created = { technicalName: null };

        repository.create.mockReturnValue(created);

        const wechat = wrapper.vm.channels[1];

        wechat.enabled = true;
        wrapper.vm.setFieldValue(wechat, wechat.formFields[0], 'wechat-id');
        wrapper.vm.setFieldValue(wechat, wechat.formFields[1], 'wechat-secret');

        await wrapper.vm.onSave();

        expect(repository.get).toHaveBeenCalledWith('client-google');
        expect(repository.save).toHaveBeenCalledTimes(2);

        expect(created).toEqual({
            technicalName: 'wechat',
            enabled: true,
            clientId: 'wechat-id',
            clientSecret: 'wechat-secret',
            scopes: null,
            settings: null,
        });
    });

    it('should report a failed save and stop the spinner', async () => {
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper, repository } = await createWrapper({ saveError: new Error('Could not save the channel') });
        const [google] = wrapper.vm.channels;

        google.enabled = true;
        wrapper.vm.setFieldValue(google, google.formFields[0], 'google-id');
        wrapper.vm.setFieldValue(google, google.formFields[1], 'google-secret');

        await wrapper.vm.onSave();

        expect(repository.save).toHaveBeenCalled();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not save the channel',
        });
        expect(wrapper.vm.isSaving).toBe(false);

        notificationSpy.mockRestore();
    });

    it('should store the fields of the channel that go into the settings column', async () => {
        const { wrapper, repository } = await createWrapper({ channels: [keycloakChannel] });
        const created = { technicalName: null };

        repository.create.mockReturnValue(created);

        const keycloak = wrapper.vm.channels[0];

        keycloak.enabled = true;
        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[0], 'keycloak-id');
        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[1], 'keycloak-secret');
        wrapper.vm.setFieldValue(keycloak, keycloak.formFields[2], 'https://keycloak.test');

        await wrapper.vm.onSave();

        expect(created.settings).toEqual({ auth_server_url: 'https://keycloak.test' });
    });

    it('should leave out settings the channel does not declare any more', async () => {
        const { wrapper, repository } = await createWrapper({
            channels: [keycloakChannel],
            clients: [
                {
                    id: 'client-keycloak',
                    technicalName: 'keycloak',
                    enabled: true,
                    clientId: 'keycloak-id',
                    clientSecret: 'keycloak-secret',
                    scopes: null,
                    settings: { auth_server_url: 'https://keycloak.test', dropped_in_a_newer_version: 'x' },
                },
            ],
        });

        await wrapper.vm.onSave();

        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                settings: { auth_server_url: 'https://keycloak.test' },
            }),
        );
    });

    it('should store the scopes the admin picked and clear them when none is picked', async () => {
        const { wrapper, repository } = await createWrapper({ channels: [officialAccountChannel] });
        const created = { technicalName: null };

        repository.create.mockReturnValue(created);

        const channel = wrapper.vm.channels[0];

        channel.enabled = true;
        wrapper.vm.setFieldValue(channel, channel.formFields[0], 'id');
        wrapper.vm.setFieldValue(channel, channel.formFields[1], 'secret');
        wrapper.vm.setFieldValue(channel, channel.formFields[2], ['snsapi_userinfo']);

        await wrapper.vm.onSave();

        expect(created.scopes).toEqual(['snsapi_userinfo']);
    });
});
