import template from './sw-category-detail-cms.html.twig';

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
            required: true,
        },
    },

    computed: {
        category() {
            return Shopwell.Store.get('swCategoryDetail').category;
        },

        cmsPage() {
            return Shopwell.Store.get('cmsPage').currentPage;
        },
    },
};
