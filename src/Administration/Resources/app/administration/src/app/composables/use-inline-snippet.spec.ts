/**
 * @sw-package framework
 */
import useInlineSnippet from './use-inline-snippet';

function stubShopwell(currentLocale: string, fallbackLocale: string): void {
    window.Shopwell = {
        Store: { get: jest.fn().mockReturnValue({ currentLocale }) },
        Context: { app: { fallbackLocale } },
        Utils: {
            types: {
                isEmpty: (value: unknown) => value === null || value === undefined || Object.keys(value).length === 0,
                isObject: (value: unknown) => typeof value === 'object' && value !== null,
            },
        },
    } as unknown as typeof Shopwell;
}

describe('src/app/composables/use-inline-snippet', () => {
    beforeEach(() => {
        stubShopwell('zh-CN', 'en-GB');
    });

    it('returns the value for the current locale when present', () => {
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'zh-CN': '你好', 'en-GB': 'Hello' })).toBe('你好');
    });

    it('falls back to the fallback locale when the current locale is missing', () => {
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'en-GB': 'Hello' })).toBe('Hello');
    });

    it('returns the first non-empty value when neither locale matches', () => {
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({ 'fr-FR': '', 'it-IT': 'Ciao' })).toBe('Ciao');
    });

    it('returns an empty string for an empty value', () => {
        const { getInlineSnippet } = useInlineSnippet();

        expect(getInlineSnippet({})).toBe('');
    });
});
