/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-preview-image-two-column', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopwell.Component.register('sw-cms-block-image-two-column', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopwell.Service('cmsService').registerCmsBlock({
    name: 'image-two-column',
    label: 'sw-cms.blocks.image.imageTwoColumn.label',
    category: 'image',
    component: 'sw-cms-block-image-two-column',
    previewComponent: 'sw-cms-preview-image-two-column',
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
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: Shopwell.Constants.CMS.MEDIA.previewCamera,
                        source: 'default',
                    },
                },
            },
        },
        right: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: Shopwell.Constants.CMS.MEDIA.previewPlant,
                        source: 'default',
                    },
                },
            },
        },
    },
});
