import template from './sw-product-detail-layout.html.twig';

/**
 * @sw-package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        isLoading() {
            return Shopwell.Store.get('swProductDetail').isLoading;
        },
    },
};
