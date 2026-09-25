/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-preview-category-heading', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-block-category-heading', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsBlock({
    name: 'category-heading',
    label: 'sw-cms.blocks.commerce.categoryHeading.label',
    category: 'commerce',
    component: 'sw-cms-block-category-heading',
    previewComponent: 'sw-cms-preview-category-heading',
    defaultConfig: {
        marginTop: '20px',
        marginLeft: null,
        marginBottom: '20px',
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'category-name',
    },
});
