/**
 * @sw-package discovery
 *
 * The blog module: the editorial content of the shop.
 *
 * The module owns four things a merchant maintains - the entry, the category it is filed under, the
 * author it is signed with and the comments below it - and nothing else. What an entry *looks like*
 * is not decided here: the detail page asks the type registry for the component of `blog.type`, so a
 * plugin that brings its own type is edited in the same screen as an article or a video page. A type
 * nobody registered does not white-screen, it renders the unknown component and keeps its fields
 * untouched.
 *
 * Two decisions are worth knowing when reading the pages:
 *
 * - There is no `active` flag. An entry is live when `publishedAt` is empty or in the past, and
 *   scheduled when it is in the future. That derivation lives in `blogStatusOf` and is used by the
 *   list, the detail sidebar and the badge, so the three can not disagree.
 * - The categories are a list of assignment rows, not of categories. Filing an entry writes a row of
 *   `blog_category_assignment`; the category itself is never edited from the entry.
 *
 * The categories, the authors and the comments get a menu entry of their own next to the entries: a
 * merchant reworking the taxonomy browses the categories directly, a byline is maintained where the
 * people behind them live, and approving what readers wrote is its own sitting rather than a detour
 * through every article.
 */
import privileges from './acl';

import type { SubContainer } from 'src/global.types';
import BlogApiService from './service/blog.api.service';
import { registerBlogType } from './service/blog-type.registry';

/**
 * The api service of this module, as the container knows it.
 *
 * The pages reach it through `inject`, so the key has to be declared here - the container is typed
 * by augmentation, not by string.
 */
declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        blogApiService: BlogApiService;
    }
}

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-blog-list', () => import('./page/sw-blog-list'));
Shopwell.Component.register('sw-blog-detail', () => import('./page/sw-blog-detail'));
Shopwell.Component.register('sw-blog-category-list', () => import('./page/sw-blog-category-list'));
Shopwell.Component.register('sw-blog-category-detail', () => import('./page/sw-blog-category-detail'));
Shopwell.Component.register('sw-blog-author-list', () => import('./page/sw-blog-author-list'));
Shopwell.Component.register('sw-blog-author-detail', () => import('./page/sw-blog-author-detail'));
Shopwell.Component.register('sw-blog-comment-list', () => import('./page/sw-blog-comment-list'));
Shopwell.Component.register('sw-blog-comment-detail', () => import('./page/sw-blog-comment-detail'));

Shopwell.Component.register('sw-blog-status-badge', () => import('./component/sw-blog-status-badge'));
Shopwell.Component.register('sw-blog-detail-content', () => import('./component/sw-blog-detail-content'));
Shopwell.Component.register('sw-blog-detail-content-post', () => import('./component/sw-blog-detail-content-post'));
Shopwell.Component.register('sw-blog-detail-content-video', () => import('./component/sw-blog-detail-content-video'));
Shopwell.Component.register('sw-blog-detail-content-unknown', () => import('./component/sw-blog-detail-content-unknown'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('blogApiService', () => {
    return new BlogApiService();
});

/**
 * The two types the core ships with.
 *
 * A plugin registers its own type the same way, next to registering the component it points at. The
 * loaders keep a type out of the initial bundle until an entry of that type is opened.
 */
registerBlogType('post', () => import('./component/sw-blog-detail-content-post'));
registerBlogType('video', () => import('./component/sw-blog-detail-content-video'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-blog', {
    type: 'core',
    name: 'blog',
    title: 'sw-blog.general.mainMenuItemGeneral',
    description: 'sw-blog.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#d9406b',
    // `regular-newspaper` and `regular-pen` do not exist in the icon set of this administration,
    // `regular-image-text` is the content icon the cms module uses as well
    icon: 'regular-image-text',
    entity: 'blog',

    routes: {
        list: {
            component: 'sw-blog-list',
            path: '',
            meta: {
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'list',
                },
            },
        },

        // declared before the `:id` route: a static segment wins over the parameter, and this one has
        // to, otherwise `/create` is read as an entry with the id "create"
        create: {
            component: 'sw-blog-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.blog.list',
                privilege: privileges.blog.creator,
                appSystem: {
                    view: 'create',
                },
            },
            props: {
                default: () => {
                    return { blogId: null };
                },
            },
        },

        detail: {
            component: 'sw-blog-detail',
            path: ':id',
            meta: {
                parentPath: 'sw.blog.list',
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { blogId: route.params.id.toLowerCase() };
                },
            },
        },

        'category.list': {
            component: 'sw-blog-category-list',
            path: 'category',
            meta: {
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'list',
                },
            },
        },

        'category.detail': {
            component: 'sw-blog-category-detail',
            path: 'category/:id',
            meta: {
                parentPath: 'sw.blog.category.list',
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { categoryId: route.params.id.toLowerCase() };
                },
            },
        },

        'author.list': {
            component: 'sw-blog-author-list',
            path: 'author',
            meta: {
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'list',
                },
            },
        },

        'author.detail': {
            component: 'sw-blog-author-detail',
            path: 'author/:id',
            meta: {
                parentPath: 'sw.blog.author.list',
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { authorId: route.params.id.toLowerCase() };
                },
            },
        },

        'comment.list': {
            component: 'sw-blog-comment-list',
            path: 'comment',
            meta: {
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'list',
                },
            },
        },

        'comment.detail': {
            component: 'sw-blog-comment-detail',
            path: 'comment/:id',
            meta: {
                parentPath: 'sw.blog.comment.list',
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { commentId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-blog',
            // `sw-content` is registered by `sw-cms`; a blog entry is content between a landing page
            // and a cms layout, so it hangs below it instead of opening a menu of its own
            label: 'sw-blog.general.mainMenuItemGeneral',
            // a navigation entry points at the *name* of a route - the router resolves it to build the
            // link of the menu item
            path: 'sw.blog.list',
            parent: 'sw-content',
            position: 40,
            privilege: privileges.blog.viewer,
        },
        {
            id: 'sw-blog-category',
            label: 'sw-blog.general.mainMenuItemCategory',
            path: 'sw.blog.category.list',
            parent: 'sw-blog',
            position: 10,
            privilege: privileges.blog.viewer,
        },
        {
            // the bylines: an author is maintained where the entry that needs one is written, and
            // this page is where a merchant creates one that does not exist yet
            id: 'sw-blog-author',
            label: 'sw-blog.general.mainMenuItemAuthor',
            path: 'sw.blog.author.list',
            parent: 'sw-blog',
            position: 15,
            privilege: privileges.blog.viewer,
        },
        {
            // the moderation queue of its own entry: a moderator works through the comments of
            // every article in one pass, the same way the review module keeps its list
            id: 'sw-blog-comment',
            label: 'sw-blog.general.mainMenuItemComment',
            path: 'sw.blog.comment.list',
            parent: 'sw-blog',
            position: 20,
            privilege: privileges.blog.viewer,
        },
    ],
});
