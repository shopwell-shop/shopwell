/**
 * @sw-package framework
 */
import OAuth2LoginService from './oauth2-login.api.service';

describe('module/sw-login/service/oauth2-login.api.service', () => {
    function createService(channels) {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({ data: { channels } })),
        };

        return {
            httpClient,
            service: new OAuth2LoginService(httpClient),
        };
    }

    it('should request the login channels without an access token', async () => {
        const { service, httpClient } = createService([{ technicalName: 'google' }]);

        await expect(service.listChannels()).resolves.toEqual([{ technicalName: 'google' }]);

        expect(httpClient.get).toHaveBeenCalledWith('/_info/oauth2-login-channels', {
            headers: {
                Accept: 'application/json',
            },
        });
    });

    it('should resolve with an empty list when the response carries no channels', async () => {
        const { service } = createService(undefined);

        await expect(service.listChannels()).resolves.toEqual([]);
    });
});
