/**
 * @sw-package discovery
 */
import { getBlogTypeComponent, registerBlogType, registeredBlogTypes, resetBlogTypes } from './blog-type.registry';

const postLoader = jest.fn(() => Promise.resolve({ default: { name: 'post-component' } }));

describe('src/module/sw-blog/service/blog-type.registry', () => {
    afterEach(() => {
        resetBlogTypes();
        jest.clearAllMocks();
    });

    it('hands out the loader that was registered for a type', () => {
        registerBlogType('post', postLoader);

        expect(getBlogTypeComponent('post')).toBe(postLoader);
    });

    it('does not invent a loader for a type nobody registered', () => {
        expect(getBlogTypeComponent('podcast')).toBeUndefined();
        expect(getBlogTypeComponent('')).toBeUndefined();
        expect(getBlogTypeComponent(null)).toBeUndefined();
        expect(getBlogTypeComponent(undefined)).toBeUndefined();
    });

    it('lets a plugin override the editor of a type the core registered', () => {
        const override = jest.fn(() => Promise.resolve({ default: { name: 'own-post' } }));

        registerBlogType('post', postLoader);
        registerBlogType('post', override);

        expect(getBlogTypeComponent('post')).toBe(override);
    });

    it('ignores an empty type instead of registering a dead key', () => {
        registerBlogType('   ', postLoader);

        expect(registeredBlogTypes()).toEqual([]);
    });

    it('lists the registered types sorted, so a dropdown does not jump between requests', () => {
        registerBlogType('video', postLoader);
        registerBlogType('post', postLoader);
        registerBlogType('podcast', postLoader);

        expect(registeredBlogTypes()).toEqual(['podcast', 'post', 'video']);
    });

    it('forgets everything on reset, so one test can not leak into the next', () => {
        registerBlogType('post', postLoader);
        resetBlogTypes();

        expect(registeredBlogTypes()).toEqual([]);
    });
});
