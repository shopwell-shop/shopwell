/**
 * @sw-package framework
 */
import initContext from 'src/app/init/context.init';
import {
    getCurrency,
    getEnvironment,
    getLocale,
    getShopwellVersion,
    getModuleInformation,
    getAppInformation,
    getUserInformation,
    getUserTimezone,
    getShopId,
    getTheme,
} from '@shopwell-ag/meteor-admin-sdk/es/context';
import { isService } from '@shopwell-ag/meteor-admin-sdk/es/_private/context';
import { getId } from '@shopwell-ag/meteor-admin-sdk/es/window';
import { publish } from '@shopwell-ag/meteor-admin-sdk/es/channel';
import { nextTick } from 'vue';
import useTheme from 'src/app/composables/use-theme';

jest.mock('@shopwell-ag/meteor-admin-sdk/es/channel', () => {
    const actual = jest.requireActual('@shopwell-ag/meteor-admin-sdk/es/channel');

    return {
        __esModule: true,
        ...actual,
        publish: jest.fn(actual.publish),
    };
});

describe('src/app/init/context.init.ts', () => {
    beforeAll(() => {
        initContext();
    });

    beforeEach(() => {
        Shopwell.Store.get('extensions').extensionsState = {};
        Shopwell.Store.get('context').app.windowId = null;
    });

    it('should handle currency', async () => {
        await getCurrency().then((currency) => {
            expect(currency).toEqual(
                expect.objectContaining({
                    systemCurrencyId: expect.any(String),
                    systemCurrencyISOCode: expect.any(String),
                }),
            );
        });
    });

    it('should handle environment', async () => {
        await getEnvironment().then((environment) => {
            expect(environment).toEqual(expect.any(String));
        });
    });

    it('should handle locale', async () => {
        await getLocale().then((locale) => {
            expect(locale).toEqual(
                expect.objectContaining({
                    fallbackLocale: expect.any(String),
                    locale: expect.any(String),
                }),
            );
        });
    });

    it('should handle shopwell version', async () => {
        await getShopwellVersion().then((version) => {
            expect(version).toEqual(expect.any(String));
        });
    });

    it('should handle module information', async () => {
        await getModuleInformation().then((moduleInformation) => {
            expect(moduleInformation).toEqual(
                expect.objectContaining({
                    modules: expect.any(Array),
                }),
            );
        });
    });

    it('should return placeholder app information', async () => {
        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'unknown',
                    version: '0.0.0',
                    type: 'app',
                }),
            );
        });
    });

    it('should return user timezone', async () => {
        Shopwell.Store.get('session').setCurrentUser({
            timeZone: 'Europe/Berlin',
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('Europe/Berlin');
        });

        Shopwell.Store.get('session').setCurrentUser({
            timeZone: undefined,
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('UTC');
        });
    });

    it('should return app information', async () => {
        Shopwell.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: ['product'],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'jestapp',
                    version: '1.0.0',
                    type: 'app',
                    privileges: {
                        read: ['product'],
                    },
                }),
            );
        });
    });

    it('should identify a Shopwell Service through the private SDK API', async () => {
        Shopwell.Context.app.config.version = '6.7.14.0';

        Shopwell.Store.get('extensions').addExtension({
            name: 'jestservice',
            baseUrl: window.location.origin,
            permissions: {},
            version: '1.0.0',
            type: 'app',
            sourceType: 'service',
        });

        await expect(isService()).resolves.toBe(true);
    });

    it('should return user information', async () => {
        Shopwell.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: ['user'],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopwell.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await getUserInformation().then((userInformation) => {
            expect(userInformation).toEqual(
                expect.objectContaining({
                    aclRoles: expect.any(Array),
                    active: true,
                    admin: true,
                    email: 'john.doe@test.com',
                    firstName: 'John',
                    id: '123',
                    lastName: 'Doe',
                    localeId: 'lOcAlEiD',
                    title: 'Dr.',
                    type: 'user',
                    username: 'john.doe',
                }),
            );
        });
    });

    it('should not return user information when permissions arent existing', async () => {
        Shopwell.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopwell.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Extension "jestapp" does not have the permission to read users');
    });

    it('should not return user information when extension is not existing', async () => {
        Shopwell.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Could not find a extension with the given event origin ""');
    });

    it('returns windowId from store', async () => {
        Shopwell.Store.get('context').app.windowId = '123';

        const windowId = await getId();

        expect(windowId).toBe('123');
    });

    it('should initialize windowId if not set', async () => {
        expect(Shopwell.Store.get('context').app.windowId).toBeNull();

        const windowId = await getId();

        expect(Shopwell.Store.get('context').windowId).not.toBeNull();
        expect(windowId).toBe(Shopwell.Store.get('context').app.windowId);
    });

    it('should return correct shopId', async () => {
        expect(Shopwell.Store.get('context').app.config.shopId).toBeNull();

        expect(await getShopId()).toBeNull();

        Shopwell.Store.get('context').app.config.shopId = 'shop-id';

        expect(await getShopId()).toBe('shop-id');
    });

    describe('theme', () => {
        afterEach(async () => {
            useTheme().setTheme('system');
            await nextTick();

            localStorage.removeItem('mt-theme');
        });

        it('should handle the resolved theme', async () => {
            useTheme().setTheme('dark');
            await nextTick();

            await expect(getTheme()).resolves.toBe('dark');

            useTheme().setTheme('light');
            await nextTick();

            await expect(getTheme()).resolves.toBe('light');
        });

        it('should resolve the system preference to a concrete theme', async () => {
            await expect(getTheme()).resolves.toBe('light');
        });

        it('should publish the resolved theme on change', async () => {
            publish.mockClear();

            useTheme().setTheme('dark');
            await nextTick();

            expect(publish).toHaveBeenCalledWith('contextTheme', 'dark');
        });

        it('should not publish when the resolved theme stays the same', async () => {
            publish.mockClear();

            useTheme().setTheme('light');
            await nextTick();

            publish.mockClear();

            useTheme().setTheme('system');
            await nextTick();

            expect(publish).not.toHaveBeenCalledWith('contextTheme', expect.anything());
        });
    });
});
