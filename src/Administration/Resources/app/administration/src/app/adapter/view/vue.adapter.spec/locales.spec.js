/**
 * @sw-package framework
 */

import VueAdapter from 'src/app/adapter/view/vue.adapter';
import LocaleFactory from 'src/core/factory/locale.factory';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

describe('ASYNC app/adapter/view/vue.adapter.js - locales', () => {
    let application;
    let vueAdapter;

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        application = createApplication();
        vueAdapter = new VueAdapter(application);
    });

    afterEach(() => {
        resetVueAdapterTestState();
    });

    it('initLocales should call setLocaleFromuser', async () => {
        application = createApplication().addFactory('locale', () => {
            return LocaleFactory;
        });

        // create vueAdapter with custom application
        vueAdapter = new VueAdapter(application);

        // Mock function
        vueAdapter.setLocaleFromUser = jest.fn();

        vueAdapter.initLocales();
        Shopwell.Store.get('session').currentLocale = 'en-GB';

        expect(vueAdapter.setLocaleFromUser).toHaveBeenCalled();
    });

    it('initLocales should use the last known locale while the session locale is not resolved yet', async () => {
        application = createApplication().addFactory('locale', () => {
            return LocaleFactory;
        });

        vueAdapter = new VueAdapter(application);
        vueAdapter.setLocaleFromUser = jest.fn();

        Shopwell.Store.get('session').currentLocale = null;
        const getLastKnownLocaleSpy = jest.spyOn(LocaleFactory, 'getLastKnownLocale').mockReturnValue('zh-CN');

        try {
            const i18n = vueAdapter.initLocales();

            expect(i18n.global.locale.value).toBe('zh-CN');

            // let the async setAdminLocale call settle before resetting the shared state
            await flushPromises();
        } finally {
            getLastKnownLocaleSpy.mockRestore();
            localStorage.removeItem('sw-admin-locale');
            Shopwell.Store.get('session').currentLocale = 'en-GB';
        }
    });

    it('setLocaleFromUser should not set the user when user does not exist', async () => {
        vueAdapter.setLocaleFromUser();
        Shopwell.Store.get('session').removeCurrentUser();

        expect(Shopwell.Service('localeHelper').setLocaleWithId).not.toHaveBeenCalled();
    });

    it('setLocaleFromUser should set the user when user does not exist', async () => {
        Shopwell.Store.get('session').setCurrentUser({ localeId: '12345' });
        vueAdapter.setLocaleFromUser();

        expect(Shopwell.Service('localeHelper').setLocaleWithId).toHaveBeenCalled();
    });

    it('setLocaleFromUser should call the service with the user id from the store', async () => {
        const expectedId = '12345678';
        Shopwell.Store.get('session').setCurrentUser({ localeId: expectedId });

        vueAdapter.setLocaleFromUser();

        expect(Shopwell.Service('localeHelper').setLocaleWithId).toHaveBeenCalledWith(expectedId);
    });

    it('initLocales should watch for user changes and recall the "setLocaleWithId"', async () => {
        application = createApplication().addFactory('locale', () => {
            return LocaleFactory;
        });

        // Mock current user in state
        Shopwell.Store.get('session').setCurrentUser({
            localeId: 'english-id',
        });

        // create vueAdapter with custom application
        vueAdapter = new VueAdapter(application);

        vueAdapter.initLocales();

        // Change the user
        Shopwell.Store.get('session').setCurrentUser({
            localeId: 'german-id',
        });

        await flushPromises();

        expect(Shopwell.Service('localeHelper').setLocaleWithId).toHaveBeenCalledWith('german-id');
    });
});
