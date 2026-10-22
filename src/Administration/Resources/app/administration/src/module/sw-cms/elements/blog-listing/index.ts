/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-preview-blog-listing', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-config-blog-listing', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-blog-listing', () => import('./component'));

/**
 * The element that renders the entries of the blog.
 *
 * The element itself owns one decision - how the entries are arranged - and nothing else. Which
 * entries are shown is not configured here: the resolver of the php side collects them for the page
 * the element was dropped on. That is also why `page` and `landingpage` are allowed next to the two
 * blog layouts: an entry may close with a block of further reading, and a merchant may want a strip
 * of the newest articles on the home page.
 *
 * The name is part of the wire contract - the storefront resolves the element by exactly this string.
 *
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsElement({
    name: 'blog-listing',
    label: 'sw-cms.elements.blogListing.label',
    component: 'sw-cms-el-blog-listing',
    configComponent: 'sw-cms-el-config-blog-listing',
    previewComponent: 'sw-cms-el-preview-blog-listing',
    allowedPageTypes: [
        Shopwell.Constants.CMS.PAGE_TYPES.BLOG_LISTING,
        Shopwell.Constants.CMS.PAGE_TYPES.BLOG_DETAIL,
        Shopwell.Constants.CMS.PAGE_TYPES.SHOP,
        Shopwell.Constants.CMS.PAGE_TYPES.LANDING,
    ],
    defaultConfig: {
        layout: {
            source: 'static',
            value: 'classic',
        },
    },
});
