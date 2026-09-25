import { IMAGE_DEFAULT_CONFIG } from '../image/config.constant';

Shopwell.Component.extend('sw-cms-el-config-manufacturer-logo', 'sw-cms-el-config-image', () => import('./config'));
Shopwell.Component.extend('sw-cms-el-manufacturer-logo', 'sw-cms-el-image', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-el-preview-manufacturer-logo', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsElement({
    name: 'manufacturer-logo',
    label: 'sw-cms.elements.productHeading.logo.label',
    component: 'sw-cms-el-manufacturer-logo',
    configComponent: 'sw-cms-el-config-manufacturer-logo',
    previewComponent: 'sw-cms-el-preview-manufacturer-logo',
    allowedPageTypes: [Shopwell.Constants.CMS.PAGE_TYPES.PRODUCT_DETAIL],
    defaultConfig: IMAGE_DEFAULT_CONFIG,
});
