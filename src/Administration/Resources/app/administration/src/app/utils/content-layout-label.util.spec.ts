/**
 * @sw-package discovery
 */
import { getContentLayoutName, getContentLayoutNameKey } from './content-layout-label.util';

describe('built-in content layout labels', () => {
    it.each([
        ['91ddf2e3fd20050a47409eb9ebbdfff5', 'Default blog article', 'defaultBlogPost'],
        ['d25760ad3288f7caa419037d34abf383', 'Default blog video', 'defaultBlogVideo'],
        ['b2cd20e487fbc369061861e22ac317aa', 'Default blog listing', 'defaultBlogListing'],
    ])('localizes built-in layout %s', (id, name, key) => {
        expect(getContentLayoutNameKey({ id, name })).toBe(`sw-experience-studio.builtinLayouts.${key}`);
    });

    it('keeps custom layouts and merchant-renamed built-ins verbatim', () => {
        expect(getContentLayoutNameKey({ id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'My article design' })).toBeNull();
        expect(getContentLayoutNameKey({ id: 'custom-id', name: 'Default blog article' })).toBeNull();
    });
    it('translates the built-in label with the component translator', () => {
        const $t = jest.fn((key: string) => `translated:${key}`);
        const name = getContentLayoutName.call(
            { $t },
            { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' },
        );

        expect(name).toBe('translated:sw-experience-studio.builtinLayouts.defaultBlogPost');
        expect($t).toHaveBeenCalledTimes(1);
    });

    it('returns merchant-authored names without treating them as translation keys', () => {
        const $t = jest.fn();

        expect(getContentLayoutName.call({ $t }, { id: 'custom-id', name: 'my.translation.key' })).toBe(
            'my.translation.key',
        );
        expect($t).not.toHaveBeenCalled();
    });
});
