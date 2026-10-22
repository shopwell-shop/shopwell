/**
 * @sw-package discovery
 */

import './index';
import { getBlogTypeComponent, registeredBlogTypes } from './service/blog-type.registry';

// the acl file registers itself in the privilege service, which does not exist in this test - the
// shape it exports is what the module registration reads, so it is reproduced here
jest.mock('./acl', () => ({
    __esModule: true,
    default: {
        blog: {
            viewer: 'blog.viewer',
            editor: 'blog.editor',
            creator: 'blog.creator',
            deleter: 'blog.deleter',
        },
    },
}));

const { Module, Component } = Shopwell;

type RouteMeta = {
    privilege?: string;
    parentPath?: string;
};

describe('src/module/sw-blog/index', () => {
    it('registers the four pages and the components of the module', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-blog-list',
            'sw-blog-detail',
            'sw-blog-category-list',
            'sw-blog-category-detail',
            'sw-blog-status-badge',
            'sw-blog-detail-content',
            'sw-blog-detail-content-post',
            'sw-blog-detail-content-video',
            'sw-blog-detail-content-unknown',
            'sw-blog-detail-comments',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('registers the module as core with the blog as its entity', () => {
        const module = Module.getModuleRegistry().get('sw-blog');

        expect(module).toBeTruthy();
        expect(module?.manifest.type).toBe('core');
        expect(module?.manifest.name).toBe('blog');
        expect(module?.manifest.entity).toBe('blog');
    });

    it('registers both entity pages and both category pages, all behind the viewer privilege', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        [
            'sw.blog.list',
            'sw.blog.detail',
            'sw.blog.category.list',
            'sw.blog.category.detail',
        ].forEach((routeName) => {
            expect(routes?.has(routeName)).toBe(true);

            const route = routes?.get(routeName) as { meta?: RouteMeta } | undefined;

            expect(route?.meta?.privilege).toBe('blog.viewer');
        });
    });

    it('serves the list on the module root and the categories beside it', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const list = routes?.get('sw.blog.list') as { path?: string } | undefined;
        const detail = routes?.get('sw.blog.detail') as { path?: string } | undefined;
        const categoryList = routes?.get('sw.blog.category.list') as { path?: string } | undefined;
        const categoryDetail = routes?.get('sw.blog.category.detail') as { path?: string } | undefined;

        // the manifest resolves the paths against the module prefix, and the router matches without
        // a trailing slash, so `/sw/blog/` and `/sw/blog` are the same page
        expect(list?.path).toBe('/sw/blog/');
        expect(detail?.path).toBe('/sw/blog/:id');
        expect(categoryList?.path).toBe('/sw/blog/category');
        expect(categoryDetail?.path).toBe('/sw/blog/category/:id');
    });

    it('nests each detail page below its list', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const detail = routes?.get('sw.blog.detail') as { meta?: RouteMeta } | undefined;
        const categoryDetail = routes?.get('sw.blog.category.detail') as { meta?: RouteMeta } | undefined;

        expect(detail?.meta?.parentPath).toBe('sw.blog.list');
        expect(categoryDetail?.meta?.parentPath).toBe('sw.blog.category.list');
    });

    it('lowers the id of the route into the prop of the page', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const detailProps = routes?.get('sw.blog.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { blogId: string };
        };
        const categoryProps = routes?.get('sw.blog.category.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { categoryId: string };
        };

        expect(detailProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            blogId: 'abcdef0123456789abcdef0123456789',
        });
        expect(categoryProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            categoryId: 'abcdef0123456789abcdef0123456789',
        });
    });

    it('hangs the module into the content menu and gives the categories and comments entries of their own', () => {
        const navigation = Module.getModuleRegistry().get('sw-blog')?.manifest.navigation ?? [];

        const entry = navigation.find((item) => item.id === 'sw-blog');
        const categoryEntry = navigation.find((item) => item.id === 'sw-blog-category');
        const commentEntry = navigation.find((item) => item.id === 'sw-blog-comment');

        expect(navigation).toHaveLength(3);
        expect(entry?.parent).toBe('sw-content');
        expect(entry?.position).toBe(40);
        expect(entry?.path).toBe('sw.blog.list');
        expect(entry?.privilege).toBe('blog.viewer');

        expect(categoryEntry?.parent).toBe('sw-blog');
        expect(categoryEntry?.path).toBe('sw.blog.category.list');
        expect(categoryEntry?.privilege).toBe('blog.viewer');

        expect(commentEntry?.parent).toBe('sw-blog');
        expect(commentEntry?.path).toBe('sw.blog.comment.list');
        expect(commentEntry?.privilege).toBe('blog.viewer');
    });

    it('registers the two types of the core so a type dropdown is not empty', () => {
        expect(registeredBlogTypes()).toEqual(['post', 'video']);
        expect(getBlogTypeComponent('post')).toBeDefined();
        expect(getBlogTypeComponent('video')).toBeDefined();
        expect(getBlogTypeComponent('podcast')).toBeUndefined();
    });
});
