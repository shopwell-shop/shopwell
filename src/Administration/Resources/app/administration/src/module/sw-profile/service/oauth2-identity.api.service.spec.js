/**
 * @sw-package fundamentals@framework
 */
import OAuth2IdentityService from './oauth2-identity.api.service';

describe('module/sw-profile/service/oauth2-identity.api.service', () => {
    function createService(bindings = []) {
        const httpClient = {
            get: jest.fn(() => Promise.resolve({ data: { bindings } })),
            delete: jest.fn(() => Promise.resolve({ status: 204 })),
        };
        const loginService = {
            getToken: jest.fn(() => 'the-token'),
        };

        return {
            httpClient,
            service: new OAuth2IdentityService(httpClient, loginService),
        };
    }

    it('should request the own bindings with the admin token', async () => {
        const { service, httpClient } = createService([{ technicalName: 'google', linked: true }]);

        await expect(service.listBindings()).resolves.toEqual([{ technicalName: 'google', linked: true }]);

        expect(httpClient.get).toHaveBeenCalledWith('/_action/sso/oauth2-identities', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer the-token',
                'Content-Type': 'application/json',
            },
        });
    });

    it('should resolve with an empty list when the response carries no binding', async () => {
        const { service } = createService(undefined);

        await expect(service.listBindings()).resolves.toEqual([]);
    });

    it('should remove the binding of one channel', async () => {
        const { service, httpClient } = createService();

        await service.unbind('google');

        expect(httpClient.delete).toHaveBeenCalledWith('/_action/sso/oauth2-identities/google', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer the-token',
                'Content-Type': 'application/json',
            },
        });
    });
});
