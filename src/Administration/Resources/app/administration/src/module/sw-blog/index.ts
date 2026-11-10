/**
 * @sw-package discovery
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

/** @private */
Shopwell.Component.register('sw-blog-list', () => import('./page/sw-blog-list'));
/** @private */
Shopwell.Component.register('sw-blog-category-navigation', () => import('./component/sw-blog-category-navigation'));
/** @private */
Shopwell.Component.register('sw-blog-category-tree', () => import('./component/sw-blog-category-navigation/tree'));
/** @private */
Shopwell.Component.register('sw-blog-detail', () => import('./page/sw-blog-detail'));
/** @private */
Shopwell.Component.register('sw-blog-category-list', () => import('./page/sw-blog-category-list'));
/** @private */
/** @private */
Shopwell.Component.register('sw-blog-author-list', () => import('./page/sw-blog-author-list'));
/** @private */
Shopwell.Component.register('sw-blog-author-detail', () => import('./page/sw-blog-author-detail'));
/** @private */
Shopwell.Component.register('sw-blog-comment-list', () => import('./page/sw-blog-comment-list'));
/** @private */
Shopwell.Component.register('sw-blog-comment-detail', () => import('./page/sw-blog-comment-detail'));

/** @private */
Shopwell.Component.register('sw-blog-status-badge', () => import('./component/sw-blog-status-badge'));
/** @private */
Shopwell.Component.register('sw-blog-detail-layout', () => import('./component/sw-blog-detail-layout'));

/** @private */
Shopwell.Component.register('sw-blog-detail-content', () => import('./component/sw-blog-detail-content'));
/** @private */
Shopwell.Component.register('sw-blog-detail-content-post', () => import('./component/sw-blog-detail-content-post'));
/** @private */
Shopwell.Component.register('sw-blog-detail-content-video', () => import('./component/sw-blog-detail-content-video'));
/** @private */
Shopwell.Component.register('sw-blog-detail-content-unknown', () => import('./component/sw-blog-detail-content-unknown'));

/** @private */
Shopwell.Service().register('blogApiService', () => {
    return new BlogApiService();
});

/**
 * The two types the core ships with.
 *
 * A plugin registers its own type the same way, next to registering the component it points at. The
 * loaders keep a type out of the initial bundle until an entry of that type is opened.
 */
registerBlogType('post', { component: 'sw-blog-detail-content-post', label: 'sw-blog.type.post' });
registerBlogType('video', { component: 'sw-blog-detail-content-video', label: 'sw-blog.type.video' });

/** @private */
Module.register('sw-blog', {
    type: 'core',
    name: 'blog',
    title: 'sw-blog.general.mainMenuItemGeneral',
    description: 'sw-blog.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#d9406b',
    // `regular-newspaper` and `regular-pen` do not exist in the icon set of this administration,
    icon: 'regular-file-text',
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
                default: (route?: { query?: Record<string, unknown> }) => {
                    const type = route?.query?.type;

                    return { blogId: null, blogType: typeof type === 'string' && type !== '' ? type : 'post' };
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
            props: {
                default: (route: { query: Record<string, unknown> }) => ({
                    categoryId: typeof route.query.id === 'string' ? route.query.id.toLowerCase() : null,
                }),
            },
            meta: {
                privilege: privileges.blog.viewer,
                appSystem: {
                    view: 'list',
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

        'author.create': {
            component: 'sw-blog-author-detail',
            path: 'author/create',
            meta: {
                parentPath: 'sw.blog.author.list',
                privilege: privileges.blog.creator,
                appSystem: { view: 'detail' },
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
            label: 'sw-blog.general.mainMenuItemGeneral',
            color: '#9AA8B5',
            icon: 'regular-file-text',
            position: 55,
            privilege: privileges.blog.viewer,
        },
        {
            id: 'sw-blog-content',
            label: 'sw-blog.navigation.content',
            path: 'sw.blog.list',
            parent: 'sw-blog',
            position: 10,
            privilege: privileges.blog.viewer,
        },
        {
            id: 'sw-blog-category',
            label: 'sw-blog.general.mainMenuItemCategory',
            path: 'sw.blog.category.list',
            parent: 'sw-blog',
            position: 20,
            privilege: privileges.blog.viewer,
        },
        {
            id: 'sw-blog-author',
            label: 'sw-blog.general.mainMenuItemAuthor',
            path: 'sw.blog.author.list',
            parent: 'sw-blog',
            position: 30,
            privilege: privileges.blog.viewer,
        },
        {
            id: 'sw-blog-comment',
            label: 'sw-blog.general.mainMenuItemComment',
            path: 'sw.blog.comment.list',
            parent: 'sw-blog',
            position: 40,
            privilege: privileges.blog.viewer,
        },
    ],
});
