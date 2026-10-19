/**
 * @sw-package framework
 */
import OAuth2ChannelService from './oauth2-channel.api.service';

describe('module/sw-settings-oauth2-login/service/oauth2-channel.api.service', () => {
    function createService(channels, token = 'the-token') {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({ data: { channels } })),
        };
        const loginService = {
            getToken: jest.fn(() => token),
        };

        return {
            httpClient,
            service: new OAuth2ChannelService(httpClient, loginService),
        };
    }

    it('should request the channels of the server with the admin token', async () => {
        const { service, httpClient } = createService([{ technicalName: 'google' }]);

        await expect(service.list()).resolves.toEqual([{ technicalName: 'google' }]);

        expect(httpClient.get).toHaveBeenCalledWith('/_action/sso/oauth2-channels', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer the-token',
                'Content-Type': 'application/json',
            },
        });
    });

    it('should resolve with an empty list when the response carries no channels', async () => {
        const { service } = createService(undefined);

        await expect(service.list()).resolves.toEqual([]);
    });
});
