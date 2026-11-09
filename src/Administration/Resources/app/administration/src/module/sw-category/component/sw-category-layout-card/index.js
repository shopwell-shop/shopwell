import template from './sw-category-layout-card.html.twig';

/**
 * @sw-package discovery
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
