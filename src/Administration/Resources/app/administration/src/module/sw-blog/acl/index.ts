/**
 * @sw-package discovery
 *
 * The privileges of the blog.
 *
 * The group hangs below `content` and not below `settings`: an article, the categories it is filed
 * under and the comments below it are content, exactly like a landing page or a cms layout. A role
 * that may maintain content but not the blog would be able to build pages nobody can read.
 *
 * The blog category has no privilege group of its own - it is reused through the four blog roles.
 * A separate key would produce the state where a role may write an article but not file it, and
 * every listing of the administration would have to explain that gap.
 *
 * `viewer` reads, `editor` decides (it also approves comments and moves an article between
 * categories), `creator` adds and `deleter` removes.
 */

const privileges = {
    blog: {
        viewer: 'blog.viewer',
        editor: 'blog.editor',
        creator: 'blog.creator',
        deleter: 'blog.deleter',
    },
};

/**
 * The privilege names of the module, as the route manifest and the pages read them.
 *
 * @private
 */
export default privileges;

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'blog',
    roles: {
        viewer: {
            privileges: [
                'blog:read',
                'blog_category:read',
                'blog_author:read',
                'blog_comment:read',
                'blog_video:read',
                'blog_category_assignment:read',
                // the layout tab offers the cms pages of the blog, so their names have to be
                // readable to whoever may read a blog entry
                'cms_page:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'blog:update',
                'blog_category:update',
                // an author is corrected where the byline is read, not in a separate role: a role
                // that may write an article has to be able to fix the name on it
                'blog_author:update',
                'blog_video:create',
                'blog_video:update',
                'blog_video:delete',
                // filing an article into a category writes and removes join rows, never the
                // category itself
                'blog_category_assignment:create',
                'blog_category_assignment:delete',
                // approving a comment flips its status, it does not delete it
                'blog_comment:update',
            ],
            dependencies: ['blog.viewer'],
        },
        creator: {
            privileges: [
                'blog:create',
                'blog_category:create',
                'blog_author:create',
            ],
            dependencies: ['blog.viewer', 'blog.editor'],
        },
        deleter: {
            privileges: [
                'blog:delete',
                'blog_category:delete',
                'blog_author:delete',
                'blog_comment:delete',
            ],
            dependencies: ['blog.viewer'],
        },
    },
});
