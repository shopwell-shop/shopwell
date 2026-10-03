/**
 * @sw-package framework
 */

// eslint-disable-next-line @typescript-eslint/no-require-imports
const resolveShopwellModule = require('./resolve-module') as (specifier: string) => unknown;

// `Shopwell` is declared `const` on the global, which puts it outside the keys `replaceProperty` accepts.
const globalObject = global as unknown as { Shopwell: unknown };

describe('shopwell:* Jest module resolution', () => {
    it('reads a named export off the global at access time, not at import', () => {
        // Resolved before the global is replaced, which is the case the getters exist for: a spec swaps
        // `window.Shopwell` for a partial mock long after the module graph is built.
        const utils = resolveShopwellModule('shopwell:utils') as { createId: () => string };
        const shopwell = jest.replaceProperty(globalObject, 'Shopwell', { Utils: { createId: () => 'first' } });

        expect(utils.createId()).toBe('first');

        shopwell.replaceValue({ Utils: { createId: () => 'second' } });

        expect(utils.createId()).toBe('second');
    });

    it('resolves a store on every call', () => {
        const useStore = resolveShopwellModule('shopwell:stores/notification') as () => unknown;
        const first = { source: 'first' };
        const second = { source: 'second' };
        const shopwell = jest.replaceProperty(globalObject, 'Shopwell', { Store: { get: () => first } });

        expect(useStore()).toBe(first);

        shopwell.replaceValue({ Store: { get: () => second } });

        expect(useStore()).toBe(second);
    });
});
