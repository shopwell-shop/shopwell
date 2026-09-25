/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-preview-product-heading', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-block-product-heading', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsBlock({
    name: 'product-heading',
    label: 'sw-cms.blocks.commerce.productHeading.label',
    category: 'commerce',
    component: 'sw-cms-block-product-heading',
    previewComponent: 'sw-cms-preview-product-heading',
    allowedPageTypes: [Shopwell.Constants.CMS.PAGE_TYPES.PRODUCT_DETAIL],
    defaultConfig: {
        marginTop: '20px',
        marginLeft: null,
        marginBottom: '20px',
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        left: 'product-name',
        right: 'manufacturer-logo',
    },
});
