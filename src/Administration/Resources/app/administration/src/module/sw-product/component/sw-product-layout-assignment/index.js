import template from './sw-product-layout-assignment.html.twig';

/**
 * @sw-package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
