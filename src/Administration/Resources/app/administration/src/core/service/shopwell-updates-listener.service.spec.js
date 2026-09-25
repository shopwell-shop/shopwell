/**
 * @sw-package framework
 */
import addShopwellUpdatesListener from 'src/core/service/shopwell-updates-listener.service';

describe('src/core/service/shopwell-updates-listener.service', () => {
    let createNotificationSpy;

    async function loginWithUpdateResponse(response) {
        let loginCallback;
        const loginService = {
            addOnLoginListener: (callback) => {
                loginCallback = callback;
            },
        };
        const serviceContainer = {
            updateService: {
                checkForUpdates: () => Promise.resolve(response),
            },
        };

        addShopwellUpdatesListener(loginService, serviceContainer);
        loginCallback();
        await flushPromises();
    }

    beforeEach(() => {
        jest.spyOn(Shopwell.Application, 'getApplicationRoot').mockImplementation(() => ({
            $t: (key) => key,
        }));
        jest.spyOn(Shopwell, 'Service').mockImplementation(() => ({
            can: () => true,
        }));
        Shopwell.Context.app.hideUpdateModule = false;
        createNotificationSpy = jest
            .spyOn(Shopwell.Store.get('notification'), 'createNotification')
            .mockImplementation(() => Promise.resolve());
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('notifies about an available update', async () => {
        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: true,
        });

        expect(createNotificationSpy).toHaveBeenCalled();
    });

    it('does not notify when auto updates are disabled', async () => {
        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: false,
        });

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });

    it('does not notify when the shop is up to date', async () => {
        await loginWithUpdateResponse({});

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });

    it('does not notify when the update module is hidden', async () => {
        Shopwell.Context.app.hideUpdateModule = true;

        await loginWithUpdateResponse({
            version: '6.4.18.0',
            autoUpdateEnabled: true,
        });

        expect(createNotificationSpy).not.toHaveBeenCalled();
    });
});
