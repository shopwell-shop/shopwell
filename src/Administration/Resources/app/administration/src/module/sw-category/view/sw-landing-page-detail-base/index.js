import template from './sw-landing-page-detail-base.html.twig';

const { Mixin } = Shopwell;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        customFieldSetsArray() {
            return Shopwell.Store.get('swCategoryDetail').customFieldSets ?? [];
        },

        ...mapPropertyErrors('landingPage', ['name', 'url', 'salesChannels']),

        landingPage() {
            return Shopwell.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopwell.Store.get('cmsPage').currentPage;
        },

        isLayoutSet() {
            return this.landingPage.cmsPageId !== null;
        },
    },
};
