/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import Bottle from 'bottlejs';
import ApplicationBootstrapper from 'src/core/application';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';

// Mock performance api for vue devtools
window.performance.mark = () => {};
window.performance.measure = () => {};
window.performance.clearMarks = () => {};
window.performance.clearMeasures = () => {};

window.removePageLoadingIndicator = jest.fn();

Shopwell.Service().register('localeHelper', () => {
    return {
        setLocaleWithId: jest.fn(),
    };
});

/**
 * @private
 */
export function createApplication() {
    Bottle.config = { strict: false };
    const container = new Bottle();

    return new ApplicationBootstrapper(container);
}

/**
 * @private
 */
export function prepareVueAdapterEnvironment() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    if (!Shopwell.Service('loginService')) {
        Shopwell.Service().register('loginService', () => {
            return {
                isLoggedIn: () => true,
            };
        });
    }

    if (!Shopwell.Service('localeToLanguageService')) {
        Shopwell.Service().register('localeToLanguageService', () => {
            return {
                localeToLanguage: () => Promise.resolve(),
            };
        });
    }

    Shopwell.Store.get('system').locales = ['en-GB', 'zh-CN'];

    Shopwell.Store.get('session').setAdminLocaleState({
        locales: ['en-GB', 'zh-CN'],
        locale: 'en-GB',
        languageId: '12345678',
    });

    process.env.NODE_ENV = 'test';
}

/**
 * @private
 */
export function resetVueAdapterTestState() {
    AsyncComponentFactory.markComponentTemplatesAsNotResolved();
    window.removePageLoadingIndicator.mockClear();
}
