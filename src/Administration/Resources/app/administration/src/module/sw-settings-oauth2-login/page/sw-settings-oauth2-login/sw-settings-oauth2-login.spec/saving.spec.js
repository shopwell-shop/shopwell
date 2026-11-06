/**
 * @sw-package framework
 */
import {
    createWrapper,
    googleClient,
    keycloakChannel,
    officialAccountChannel,
    selectChannel,
    setField,
    enableChannel,
    save,
    setupPageHooks,
} from './sw-settings-oauth2-login.fixtures';

describe('src/module/sw-settings-oauth2-login/page/sw-settings-oauth2-login', () => {
    setupPageHooks();

    it('should flag an enabled channel without credentials in the tabs', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('[data-error="false"]')).toHaveLength(2);
        await selectChannel(wrapper, 'wechat');
        await enableChannel(wrapper);
        expect(wrapper.get('[data-channel="wechat"]').attributes('data-error')).toBe('true');
    });

    it('should flag an enabled channel whose own required field is empty', async () => {
        const { wrapper } = await createWrapper({ channels: [keycloakChannel] });

        await enableChannel(wrapper);
        await setField(wrapper, 'clientId', 'keycloak-id');
        await setField(wrapper, 'clientSecret', 'keycloak-secret');
        expect(wrapper.get('[data-channel="keycloak"]').attributes('data-error')).toBe('true');
        await setField(wrapper, 'auth_server_url', 'https://keycloak.test');
        expect(wrapper.get('[data-channel="keycloak"]').attributes('data-error')).toBe('false');
    });

    it('should warn and switch to the channel when an enabled channel has an empty required field', async () => {
        const { wrapper, repository } = await createWrapper();
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await selectChannel(wrapper, 'wechat');
        await enableChannel(wrapper);
        await selectChannel(wrapper, 'google');
        await save(wrapper);
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('wechat');
        expect(repository.save).not.toHaveBeenCalled();
        notification.mockRestore();
    });

    it('should not store a channel that was never configured', async () => {
        const { wrapper, repository } = await createWrapper();

        await save(wrapper);
        expect(repository.create).not.toHaveBeenCalled();
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should not store a channel whose fields are only on the defaults the server sends', async () => {
        const { wrapper, repository } = await createWrapper();

        await setField(wrapper, 'clientId', '');
        await save(wrapper);
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should update the configured channels and create the newly filled one', async () => {
        const { wrapper, repository } = await createWrapper({ clients: [googleClient] });
        const created = { technicalName: null };
        repository.create.mockReturnValue(created);

        await selectChannel(wrapper, 'wechat');
        await enableChannel(wrapper);
        await setField(wrapper, 'clientId', 'wechat-id');
        await setField(wrapper, 'clientSecret', 'wechat-secret');
        await save(wrapper);
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
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper, repository } = await createWrapper({ saveError: new Error('Could not save the channel') });

        await enableChannel(wrapper);
        await setField(wrapper, 'clientId', 'google-id');
        await setField(wrapper, 'clientSecret', 'google-secret');
        await save(wrapper);
        expect(repository.save).toHaveBeenCalled();
        expect(notification).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'Could not save the channel',
        });
        expect(wrapper.get('.sw-settings-oauth2-login__save-action').attributes('disabled')).toBeUndefined();
        notification.mockRestore();
    });

    it('should store the fields of the channel that go into the settings column', async () => {
        const { wrapper, repository } = await createWrapper({ channels: [keycloakChannel] });
        const created = { technicalName: null };
        repository.create.mockReturnValue(created);

        await enableChannel(wrapper);
        await setField(wrapper, 'clientId', 'keycloak-id');
        await setField(wrapper, 'clientSecret', 'keycloak-secret');
        await setField(wrapper, 'auth_server_url', 'https://keycloak.test');
        await save(wrapper);
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

        await save(wrapper);
        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                settings: { auth_server_url: 'https://keycloak.test' },
            }),
        );
    });

    it('should store the scopes the admin picked', async () => {
        const { wrapper, repository } = await createWrapper({ channels: [officialAccountChannel] });
        const created = { technicalName: null };
        repository.create.mockReturnValue(created);

        await enableChannel(wrapper);
        await setField(wrapper, 'clientId', 'id');
        await setField(wrapper, 'clientSecret', 'secret');
        await wrapper.get('.sw-settings-oauth2-login__field-scopes .choose-scope').trigger('click');
        await save(wrapper);
        expect(created.scopes).toEqual(['snsapi_userinfo']);
    });
});
