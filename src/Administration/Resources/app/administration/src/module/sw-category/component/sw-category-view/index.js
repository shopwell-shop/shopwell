import template from './sw-category-view.html.twig';
import './sw-category-view.scss';
import errorConfig from '../../error-config.json';

const { mapPageErrors } = Shopwell.Component.getComponentHelper();

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature'],

    mixins: ['placeholder'],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
        type: {
            type: String,
            required: false,
            default: 'page',
        },
    },

    computed: {
        category() {
            return Shopwell.Store.get('swCategoryDetail').category;
        },

        isCategoryColumn() {
            return Shopwell.Store.get('swCategoryDetail').isCategoryColumn;
        },

        isPage() {
            return this.type !== 'folder' && this.type !== 'link';
        },

        categoryViewTabs() {
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

            const tabs = [
                createRouteTab('sw-category.view.general', 'sw.category.detail.base', {
                    hasError: this.swCategoryViewError,
                }),
            ];

            if (this.isPage) {
                tabs.push(createRouteTab('sw-category.view.products', 'sw.category.detail.products'));
            }

            if (this.isPage) {
                tabs.push(createRouteTab('sw-category.view.layout', 'sw.category.detail.layout'));
            }

            if (this.isPage) {
                tabs.push(createRouteTab('sw-category.view.seo', 'sw.category.detail.seo'));
            }

            return tabs;
        },

        ...mapPageErrors(errorConfig),
    },
};
