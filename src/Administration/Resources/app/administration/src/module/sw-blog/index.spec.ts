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
    it('registers the pages of the module and the components they are built from', () => {
        const components = Component.getComponentRegistry();

        [
            'sw-blog-list',
            'sw-blog-detail',
            'sw-blog-category-list',
            'sw-blog-category-detail',
            'sw-blog-author-list',
            'sw-blog-author-detail',
            'sw-blog-comment-list',
            'sw-blog-comment-detail',
            'sw-blog-status-badge',
            'sw-blog-detail-content',
            'sw-blog-detail-content-post',
            'sw-blog-detail-content-video',
            'sw-blog-detail-content-unknown',
        ].forEach((component) => {
            expect(components.has(component)).toBe(true);
        });
    });

    it('keeps a single place for the moderation of the comments', () => {
        // the comments used to be a second list embedded in the detail page; one screen that
        // approves them is enough, and the entry page links to it instead of owning a copy
        expect(Component.getComponentRegistry().has('sw-blog-detail-comments')).toBe(false);
    });

    it('registers the module as core with the blog as its entity', () => {
        const module = Module.getModuleRegistry().get('sw-blog');

        expect(module).toBeTruthy();
        expect(module?.manifest.type).toBe('core');
        expect(module?.manifest.name).toBe('blog');
        expect(module?.manifest.entity).toBe('blog');
    });

    it('registers every page behind the viewer privilege except the one that creates an entry', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        [
            'sw.blog.list',
            'sw.blog.detail',
            'sw.blog.category.list',
            'sw.blog.category.detail',
            'sw.blog.author.list',
            'sw.blog.author.detail',
            'sw.blog.comment.list',
            'sw.blog.comment.detail',
        ].forEach((routeName) => {
            expect(routes?.has(routeName)).toBe(true);

            const route = routes?.get(routeName) as { meta?: RouteMeta } | undefined;

            expect(route?.meta?.privilege).toBe('blog.viewer');
        });

        expect(routes?.has('sw.blog.create')).toBe(true);
    });

    it('asks for the creator privilege on the page that creates an entry', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const create = routes?.get('sw.blog.create') as { meta?: RouteMeta } | undefined;

        expect(create?.meta?.privilege).toBe('blog.creator');
        expect(create?.meta?.parentPath).toBe('sw.blog.list');
    });

    it('serves the list on the module root and the categories, authors and comments beside it', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const list = routes?.get('sw.blog.list') as { path?: string } | undefined;
        const create = routes?.get('sw.blog.create') as { path?: string } | undefined;
        const detail = routes?.get('sw.blog.detail') as { path?: string } | undefined;
        const categoryList = routes?.get('sw.blog.category.list') as { path?: string } | undefined;
        const categoryDetail = routes?.get('sw.blog.category.detail') as { path?: string } | undefined;
        const authorList = routes?.get('sw.blog.author.list') as { path?: string } | undefined;
        const authorDetail = routes?.get('sw.blog.author.detail') as { path?: string } | undefined;
        const commentList = routes?.get('sw.blog.comment.list') as { path?: string } | undefined;
        const commentDetail = routes?.get('sw.blog.comment.detail') as { path?: string } | undefined;

        // the manifest resolves the paths against the module prefix, and the router matches without
        // a trailing slash, so `/sw/blog/` and `/sw/blog` are the same page
        expect(list?.path).toBe('/sw/blog/');
        // `create` has to be its own static segment, otherwise `/sw/blog/create` is matched as an
        // entry with the id `create` and the detail page loads nothing
        expect(create?.path).toBe('/sw/blog/create');
        expect(detail?.path).toBe('/sw/blog/:id');
        expect(categoryList?.path).toBe('/sw/blog/category');
        expect(categoryDetail?.path).toBe('/sw/blog/category/:id');
        expect(authorList?.path).toBe('/sw/blog/author');
        expect(authorDetail?.path).toBe('/sw/blog/author/:id');
        expect(commentList?.path).toBe('/sw/blog/comment');
        expect(commentDetail?.path).toBe('/sw/blog/comment/:id');
    });

    it('nests each detail page below its list', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const detail = routes?.get('sw.blog.detail') as { meta?: RouteMeta } | undefined;
        const categoryDetail = routes?.get('sw.blog.category.detail') as { meta?: RouteMeta } | undefined;
        const authorDetail = routes?.get('sw.blog.author.detail') as { meta?: RouteMeta } | undefined;
        const commentDetail = routes?.get('sw.blog.comment.detail') as { meta?: RouteMeta } | undefined;

        expect(detail?.meta?.parentPath).toBe('sw.blog.list');
        expect(categoryDetail?.meta?.parentPath).toBe('sw.blog.category.list');
        expect(authorDetail?.meta?.parentPath).toBe('sw.blog.author.list');
        expect(commentDetail?.meta?.parentPath).toBe('sw.blog.comment.list');
    });

    it('lowers the id of the route into the prop of the page', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const detailProps = routes?.get('sw.blog.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { blogId: string };
        };
        const categoryProps = routes?.get('sw.blog.category.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { categoryId: string };
        };
        const authorProps = routes?.get('sw.blog.author.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { authorId: string };
        };
        const commentProps = routes?.get('sw.blog.comment.detail')?.props as {
            default: (route: { params: Record<string, string> }) => { commentId: string };
        };

        expect(detailProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            blogId: 'abcdef0123456789abcdef0123456789',
        });
        expect(categoryProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            categoryId: 'abcdef0123456789abcdef0123456789',
        });
        expect(authorProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            authorId: 'abcdef0123456789abcdef0123456789',
        });
        expect(commentProps.default({ params: { id: 'ABCDEF0123456789ABCDEF0123456789' } })).toEqual({
            commentId: 'abcdef0123456789abcdef0123456789',
        });
    });

    it('opens the create route without an id, which is what tells the page it is writing a new entry', () => {
        const routes = Module.getModuleRegistry().get('sw-blog')?.routes;

        const createProps = routes?.get('sw.blog.create')?.props as {
            default: () => { blogId: string | null };
        };

        // no title is asked for before the page opens - the page itself is where an entry is written
        expect(createProps.default()).toEqual({ blogId: null });
    });

    it('hangs the module into the content menu and gives the categories, authors and comments entries of their own', () => {
        const navigation = Module.getModuleRegistry().get('sw-blog')?.manifest.navigation ?? [];

        const entry = navigation.find((item) => item.id === 'sw-blog');
        const categoryEntry = navigation.find((item) => item.id === 'sw-blog-category');
        const authorEntry = navigation.find((item) => item.id === 'sw-blog-author');
        const commentEntry = navigation.find((item) => item.id === 'sw-blog-comment');

        expect(navigation).toHaveLength(4);
        expect(entry?.parent).toBe('sw-content');
        expect(entry?.position).toBe(40);
        expect(entry?.path).toBe('sw.blog.list');
        expect(entry?.privilege).toBe('blog.viewer');

        expect(categoryEntry?.parent).toBe('sw-blog');
        expect(categoryEntry?.path).toBe('sw.blog.category.list');
        expect(categoryEntry?.privilege).toBe('blog.viewer');

        // the bylines sit between the categories and the moderation queue
        expect(authorEntry?.parent).toBe('sw-blog');
        expect(authorEntry?.path).toBe('sw.blog.author.list');
        expect(authorEntry?.privilege).toBe('blog.viewer');

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
