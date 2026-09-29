/**
 * @sw-package framework
 */

import LocaleFactory from 'src/core/factory/locale.factory';

const originalNavigatorLanguage = navigator.language;
const originalNavigatorLanguages = navigator.languages;

function setBrowserLanguages(language: string, languages: string[] = [language]) {
    Object.defineProperty(window.navigator, 'language', {
        value: language,
        configurable: true,
    });
    Object.defineProperty(window.navigator, 'languages', {
        value: languages,
        configurable: true,
    });
}

describe('core/factory/locale.factory.ts', () => {
    beforeEach(() => {
        // Clear the locale registry before each test
        const registry = LocaleFactory.getLocaleRegistry();
        registry.clear();

        LocaleFactory.setSystemFallbackLocale(null);

        window.localStorage.removeItem('sw-admin-locale');

        setBrowserLanguages(originalNavigatorLanguage, [...originalNavigatorLanguages]);

        // Reset Shopwell.Snippet mock
        Object.defineProperty(Shopwell, 'Snippet', {
            value: undefined,
            writable: true,
            configurable: true,
        });
    });

    describe('extend', () => {
        it('should extend an existing locale with new messages', () => {
            // Register a locale first
            LocaleFactory.register('en-GB', {
                global: {
                    test: 'original',
                },
            });

            // Verify initial state
            const registryBefore = LocaleFactory.getLocaleRegistry();
            const messagesBefore = registryBefore.get('en-GB');
            expect(messagesBefore).toEqual({
                global: {
                    test: 'original',
                },
            });

            // Extend the locale
            const result = LocaleFactory.extend('en-GB', {
                global: {
                    newKey: 'new value',
                },
            });

            expect(result).toBe('en-GB');

            const registry = LocaleFactory.getLocaleRegistry();
            const messages = registry.get('en-GB');

            expect(messages).toEqual({
                global: {
                    test: 'original',
                    newKey: 'new value',
                },
            });
        });

        it('should merge nested objects deeply when extending', () => {
            LocaleFactory.register('en-GB', {
                module: {
                    section: {
                        key1: 'value1',
                        key2: 'value2',
                    },
                },
            });

            // Verify initial state
            const registryBefore = LocaleFactory.getLocaleRegistry();
            const messagesBefore = registryBefore.get('en-GB');
            expect(messagesBefore).toEqual({
                module: {
                    section: {
                        key1: 'value1',
                        key2: 'value2',
                    },
                },
            });

            LocaleFactory.extend('en-GB', {
                module: {
                    section: {
                        key2: 'updated value2',
                        key3: 'value3',
                    },
                },
            });

            const registry = LocaleFactory.getLocaleRegistry();
            const messages = registry.get('en-GB');

            expect(messages).toEqual({
                module: {
                    section: {
                        key1: 'value1',
                        key2: 'updated value2',
                        key3: 'value3',
                    },
                },
            });
        });

        it('should not call setLocaleMessage when Shopwell.Snippet is not instantiated', () => {
            LocaleFactory.register('en-GB', {
                test: 'value',
            });

            const result = LocaleFactory.extend('en-GB', {
                newKey: 'new value',
            });

            expect(result).toBe('en-GB');
            // No error should be thrown
        });

        it('should call setLocaleMessage twice when Shopwell.Snippet is instantiated', () => {
            LocaleFactory.register('en-GB', {
                original: 'value',
            });

            // Mock Shopwell.Snippet
            const setLocaleMessageMock = jest.fn();
            Object.defineProperty(Shopwell, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            // Verify mock hasn't been called yet
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(0);

            LocaleFactory.extend('en-GB', {
                newKey: 'new value',
            });

            // Should be called twice: first with empty object, then with merged messages
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(2);
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(1, 'en-GB', {});
            expect(setLocaleMessageMock).toHaveBeenNthCalledWith(2, 'en-GB', {
                original: 'value',
                newKey: 'new value',
            });
        });

        it('should properly merge messages across multiple extend calls', () => {
            LocaleFactory.register('zh-CN', {
                plugin1: {
                    key1: 'value1',
                },
            });

            // Verify state after registration
            let registry = LocaleFactory.getLocaleRegistry();
            let messages = registry.get('zh-CN');
            expect(messages).toEqual({
                plugin1: {
                    key1: 'value1',
                },
            });

            LocaleFactory.extend('zh-CN', {
                plugin2: {
                    key2: 'value2',
                },
            });

            // Verify state after first extend
            registry = LocaleFactory.getLocaleRegistry();
            messages = registry.get('zh-CN');
            expect(messages).toEqual({
                plugin1: {
                    key1: 'value1',
                },
                plugin2: {
                    key2: 'value2',
                },
            });

            LocaleFactory.extend('zh-CN', {
                plugin3: {
                    key3: 'value3',
                },
            });

            // Verify final state after second extend
            registry = LocaleFactory.getLocaleRegistry();
            messages = registry.get('zh-CN');

            expect(messages).toEqual({
                plugin1: {
                    key1: 'value1',
                },
                plugin2: {
                    key2: 'value2',
                },
                plugin3: {
                    key3: 'value3',
                },
            });
        });

        it('should trigger reactivity updates on multiple extends when i18n is instantiated', () => {
            LocaleFactory.register('en-GB', {
                base: 'value',
            });

            const setLocaleMessageMock = jest.fn();
            Object.defineProperty(Shopwell, 'Snippet', {
                value: {
                    setLocaleMessage: setLocaleMessageMock,
                },
                writable: true,
                configurable: true,
            });

            // Verify mock hasn't been called yet
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(0);

            LocaleFactory.extend('en-GB', {
                plugin1: 'value1',
            });

            // Verify called 2 times after first extend
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(2);

            LocaleFactory.extend('en-GB', {
                plugin2: 'value2',
            });

            // Should be called 4 times total (2 calls per extend)
            expect(setLocaleMessageMock).toHaveBeenCalledTimes(4);

            // Check the final call has all merged values
            expect(setLocaleMessageMock).toHaveBeenLastCalledWith('en-GB', {
                base: 'value',
                plugin1: 'value1',
                plugin2: 'value2',
            });
        });

        it('should return false and warn when locale name is invalid', () => {
            const spy = jest.fn();
            jest.spyOn(global.console, 'warn').mockImplementation(spy);

            const result = LocaleFactory.extend('invalid', {
                test: 'value',
            });

            expect(result).toBe(false);
            expect(spy).toHaveBeenCalledWith(
                '[LocaleFactory]',
                'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode]] for example "en-US"',
            );
        });

        it('should return false and warn when extending a non-existent locale', () => {
            const spy = jest.fn();
            jest.spyOn(global.console, 'warn').mockImplementation(spy);

            // Verify locale doesn't exist before trying to extend
            const registry = LocaleFactory.getLocaleRegistry();
            expect(registry.has('en-GB')).toBe(false);

            const result = LocaleFactory.extend('en-GB', {
                test: 'value',
            });

            expect(result).toBe(false);
            expect(spy).toHaveBeenCalledWith(
                '[LocaleFactory]',
                'The locale "en-GB" doesn\'t exist. Please use the register method to register a new locale',
            );
        });

        it('should handle optional chaining for setLocaleMessage', () => {
            LocaleFactory.register('en-GB', {
                test: 'value',
            });

            // Mock Shopwell.Snippet but without setLocaleMessage
            Object.defineProperty(Shopwell, 'Snippet', {
                value: {},
                writable: true,
                configurable: true,
            });

            // Should not throw an error
            expect(() => {
                LocaleFactory.extend('en-GB', {
                    newKey: 'new value',
                });
            }).not.toThrow();
        });

        it('should handle empty messages object when extending', () => {
            LocaleFactory.register('en-GB', {
                existing: 'value',
            });

            const result = LocaleFactory.extend('en-GB', {});

            expect(result).toBe('en-GB');

            const registry = LocaleFactory.getLocaleRegistry();
            const messages = registry.get('en-GB');

            expect(messages).toEqual({
                existing: 'value',
            });
        });
    });

    describe('register', () => {
        it('should register a new locale', () => {
            // Verify locale doesn't exist before registration
            const registryBefore = LocaleFactory.getLocaleRegistry();
            expect(registryBefore.has('en-GB')).toBe(false);

            const result = LocaleFactory.register('en-GB', {
                test: 'value',
            });

            expect(result).toBe('en-GB');

            const registry = LocaleFactory.getLocaleRegistry();
            expect(registry.has('en-GB')).toBe(true);
            expect(registry.get('en-GB')).toEqual({
                test: 'value',
            });
        });

        it('should return false and warn when registering without a name', () => {
            const spy = jest.fn();
            jest.spyOn(global.console, 'warn').mockImplementation(spy);

            const result = LocaleFactory.register('', {});

            expect(result).toBe(false);
            expect(spy).toHaveBeenCalledWith('[LocaleFactory]', 'A locale always needs a name');
        });

        it('should return false and warn when locale name format is invalid', () => {
            const spy = jest.fn();
            jest.spyOn(global.console, 'warn').mockImplementation(spy);

            const result = LocaleFactory.register('en', {});

            expect(result).toBe(false);
            expect(spy).toHaveBeenCalledWith(
                '[LocaleFactory]',
                'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode] for example "en-US"',
            );
        });

        it('should return false and warn when locale is already registered', () => {
            const spy = jest.fn();
            jest.spyOn(global.console, 'warn').mockImplementation(spy);

            // Verify locale doesn't exist before first registration
            const registryBefore = LocaleFactory.getLocaleRegistry();
            expect(registryBefore.has('en-GB')).toBe(false);

            LocaleFactory.register('en-GB', { test: 'value' });

            // Verify locale exists after first registration
            const registryAfter = LocaleFactory.getLocaleRegistry();
            expect(registryAfter.has('en-GB')).toBe(true);

            const result = LocaleFactory.register('en-GB', { test: 'value2' });

            expect(result).toBe(false);
            expect(spy).toHaveBeenCalledWith(
                '[LocaleFactory]',
                'The locale "en-GB" is registered already.',
                'Please use the extend method to extend and override certain keys',
            );
        });
    });

    describe('getLocaleRegistry', () => {
        it('should return the locale registry', () => {
            const registry = LocaleFactory.getLocaleRegistry();

            expect(registry).toBeInstanceOf(Map);
        });

        it('should return the same registry instance', () => {
            const registry1 = LocaleFactory.getLocaleRegistry();
            const registry2 = LocaleFactory.getLocaleRegistry();

            expect(registry1).toBe(registry2);
        });
    });

    describe('getLastKnownLocale', () => {
        it('should prefer the stored locale over the browser locale', () => {
            LocaleFactory.setSystemFallbackLocale('zh-CN');

            window.localStorage.setItem('sw-admin-locale', 'fr-FR');

            expect(LocaleFactory.getLastKnownLocale()).toBe('fr-FR');
        });

        it('should prefer the browser locale when it is registered', () => {
            setBrowserLanguages('zh-CN');

            LocaleFactory.setSystemFallbackLocale('fr-FR');
            LocaleFactory.register('en-GB', {});
            LocaleFactory.register('zh-CN', {});

            expect(LocaleFactory.getLastKnownLocale()).toBe('zh-CN');
        });

        it('should fall back to english when the browser locale is not registered', () => {
            setBrowserLanguages('es-ES');

            LocaleFactory.setSystemFallbackLocale('zh-CN');
            LocaleFactory.register('en-GB', {});
            LocaleFactory.register('zh-CN', {});

            expect(LocaleFactory.getLastKnownLocale()).toBe('en-GB');
        });

        it('should fall back to the system locale when neither browser nor english are registered', () => {
            setBrowserLanguages('es-ES');

            LocaleFactory.setSystemFallbackLocale('zh-CN');
            LocaleFactory.register('zh-CN', {});

            expect(LocaleFactory.getLastKnownLocale()).toBe('zh-CN');
        });
    });
});
