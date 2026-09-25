/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-preview-cross-selling', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-config-cross-selling', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-cross-selling', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsElement({
    name: 'cross-selling',
    label: 'sw-cms.elements.crossSelling.label',
    component: 'sw-cms-el-cross-selling',
    configComponent: 'sw-cms-el-config-cross-selling',
    previewComponent: 'sw-cms-el-preview-cross-selling',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopwell.Data.Criteria(1, 25).addAssociation('crossSellings.assignedProducts.product'),
            },
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        elMinWidth: {
            source: 'static',
            value: '300px',
        },
        speed: {
            source: 'static',
            value: 300,
        },
    },
    collect: Shopwell.Service('cmsService').getCollectFunction(),
});
