/**
 * @sw-package framework
 */
import initializeLocaleService from 'src/app/init/locale.init';

const originalNavigatorLanguage = navigator.language;
const originalNavigatorLanguages = navigator.languages;
const originalSystemLanguageId = Shopwell.Context.api.systemLanguageId;

describe('src/app/init/locale.init.ts', () => {
    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg1, msg2) => {
                if (typeof msg2 !== 'string') {
                    return false;
                }

                return msg2?.includes('A apiService always needs a name');
            },
        });

        Shopwell.Service().register('snippetService', () => {
            return {
                getLocales: jest.fn().mockResolvedValue([]),
                getSnippets: jest.fn().mockResolvedValue({}),
            };
        });
    });

    beforeEach(() => {
        Shopwell.Application.getContainer('factory').locale.getLocaleRegistry().clear();
        Shopwell.Application.getContainer('factory').locale.setSystemFallbackLocale(null);
        Shopwell.Context.api.systemLanguageId = originalSystemLanguageId;

        window.localStorage.removeItem('sw-admin-locale');

        Object.defineProperty(window.navigator, 'language', {
            value: originalNavigatorLanguage,
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: originalNavigatorLanguages,
            configurable: true,
        });
    });

    afterEach(() => {
        Shopwell.Context.api.systemLanguageId = originalSystemLanguageId;
    });

    it('should register the locale factory with correct snippet languages', async () => {
        global.console.warn = jest.fn();
        await initializeLocaleService();

        expect(Shopwell.Application.getContainer('factory').locale).toEqual(
            expect.objectContaining({
                getLocaleByName: expect.any(Function),
                getLocaleRegistry: expect.any(Function),
                register: expect.any(Function),
                extend: expect.any(Function),
                getBrowserLanguage: expect.any(Function),
                getBrowserLanguages: expect.any(Function),
                setSystemFallbackLocale: expect.any(Function),
                getLastKnownLocale: expect.any(Function),
                storeCurrentLocale: expect.any(Function),
            }),
        );
    });

    it('should return locale factory when snippet service is not available', async () => {
        global.console.warn = jest.fn();
        const originalService = Shopwell.Service;
        Shopwell.Service = jest.fn().mockReturnValue(undefined);

        const result = await initializeLocaleService();

        expect(result).toEqual(
            expect.objectContaining({
                register: expect.any(Function),
            }),
        );

        Shopwell.Service = originalService;
    });

    it('should register all locales for languages in the database', async () => {
        const expectedLocales = {
            id1: 'en-GB',
            id2: 'zh-CN',
            id3: 'fr-FR',
            id4: 'jp-JP',
        };

        // Mock the snippetService to return expected locales
        Shopwell.Service('snippetService').getLocales = () => {
            return Promise.resolve(expectedLocales);
        };

        expect(Shopwell.Service('snippetService')).toBeDefined();

        await initializeLocaleService();

        const factoryContainer = Shopwell.Application.getContainer('factory');
        const localeRegistry = factoryContainer.locale.getLocaleRegistry();
        const locales = Array.from(localeRegistry.keys());

        expect(locales).toEqual(Object.values(expectedLocales));
    });

    it('should use the system language locale when browser and english fallbacks are unavailable', async () => {
        Object.defineProperty(window.navigator, 'language', {
            value: 'es-ES',
            configurable: true,
        });
        Object.defineProperty(window.navigator, 'languages', {
            value: ['es-ES'],
            configurable: true,
        });
        Shopwell.Context.api.systemLanguageId = 'system-language-id';

        Shopwell.Service('snippetService').getLocales = () => {
            return Promise.resolve({
                'system-language-id': 'zh-CN',
            });
        };

        await initializeLocaleService();

        expect(Shopwell.Application.getContainer('factory').locale.getLastKnownLocale()).toBe('zh-CN');
    });
});
