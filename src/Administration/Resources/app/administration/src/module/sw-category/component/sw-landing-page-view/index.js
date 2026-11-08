import template from './sw-landing-page-view.html.twig';

const { Mixin } = Shopwell;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    computed: {
        landingPage() {
            return Shopwell.Store.get('swCategoryDetail').landingPage;
        },

        landingPageViewTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                return {
                    label: this.$t(label),
                    name: routeName,
                    onClick: () => {
                        void this.$router.push({ name: routeName });
                    },
                    ...additionalProperties,
                };
            };

            return [
                createRouteTab('sw-landing-page.view.general', 'sw.category.landingPageDetail.base'),
                createRouteTab('sw-landing-page.view.layout', 'sw.category.landingPageDetail.layout', {
                    disabled: !this.acl.can('landing_page.editor'),
                }),
            ];
        },
    },
};
