import template from './sw-cms-el-config-blog-listing.html.twig';

const { Component, Mixin } = Shopwell;

/**
 * The one setting of the element: how the entries are arranged.
 *
 * There is nothing else to configure, and that is on purpose. A date filter or a category picker here
 * would be a second place that decides what a blog page shows, next to the page the merchant is
 * editing - the resolver would have to honour both, and one of them would win silently.
 *
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        layoutOptions(): Array<{ id: number; value: string; label: string }> {
            return [
                {
                    id: 1,
                    value: 'classic',
                    label: this.$t('sw-cms.elements.blogListing.config.label.layoutClassic'),
                },
                {
                    id: 2,
                    value: 'grid',
                    label: this.$t('sw-cms.elements.blogListing.config.label.layoutGrid'),
                },
                {
                    id: 3,
                    value: 'list',
                    label: this.$t('sw-cms.elements.blogListing.config.label.layoutList'),
                },
            ];
        },

        layout(): string | null {
            const config = this.element.config.layout as { value?: string | null } | undefined;

            return config?.value ?? null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig();
        },

        onLayoutChange(layout: string | null): void {
            const config = this.element.config.layout as { value?: string | null } | undefined;

            if (!config) {
                return;
            }

            config.value = layout;

            this.$emit('element-update', this.element);
        },
    },
});
