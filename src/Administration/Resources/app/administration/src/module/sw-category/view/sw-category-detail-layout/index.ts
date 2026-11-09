import template from './sw-category-detail-layout.html.twig';

/**
 * @private
 * @sw-package discovery
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },
});
