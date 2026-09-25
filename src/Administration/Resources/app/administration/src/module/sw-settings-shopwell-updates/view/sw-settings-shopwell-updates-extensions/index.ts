import template from './sw-shopwell-updates-extensions.html.twig';

/**
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['feature'],

    props: {
        isLoading: {
            type: Boolean,
        },
        extensions: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        hasExtensions(): boolean {
            return this.extensions.length > 0;
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-shopwell-updates.extensions.columns.name'),
                    rawData: true,
                },
                {
                    property: 'icon',
                    label: this.$t('sw-settings-shopwell-updates.extensions.columns.available'),
                    rawData: true,
                },
            ];
        },
    },

    methods: {
        openMyExtensions() {
            void this.$router.push({
                name: 'sw.extension.my-extensions.listing.app',
            });
        },
    },
});
