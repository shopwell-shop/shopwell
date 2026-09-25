import template from './sw-landing-page-detail-cms.html.twig';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        landingPage() {
            return Shopwell.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopwell.Store.get('cmsPage').currentPage;
        },
    },
};
