/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-preview-image-text', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-block-image-text', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsBlock({
    name: 'image-text',
    label: 'sw-cms.blocks.textImage.imageText.label',
    category: 'text-image',
    component: 'sw-cms-block-image-text',
    previewComponent: 'sw-cms-preview-image-text',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        left: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'standard' },
                },
                data: {
                    media: {
                        value: Shopwell.Constants.CMS.MEDIA.previewMountain,
                        source: 'default',
                    },
                },
            },
        },
        right: 'text',
    },
});
