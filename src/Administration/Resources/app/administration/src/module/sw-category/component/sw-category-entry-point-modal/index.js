import template from './sw-category-entry-point-modal.html.twig';
import './sw-category-entry-point-modal.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    emits: ['modal-close'],

    props: {
        salesChannelCollection: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            temporaryCollection: [],
            salesChannelOptions: [],
            selectedSalesChannelId: '',

            nextRoute: null,
            isDisplayingLeavePageWarning: false,
        };
    },

    computed: {
        selectedSalesChannel() {
            return this.temporaryCollection.find((channel) => channel.id === this.selectedSalesChannelId);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannelCollection.forEach((salesChannel) => {
                this.temporaryCollection.push({
                    id: salesChannel.id,
                    name: salesChannel.name,
                    homeEnabled: salesChannel.homeEnabled,
                    homeName: salesChannel.homeName,
                    homeMetaTitle: salesChannel.homeMetaTitle,
                    homeMetaDescription: salesChannel.homeMetaDescription,
                    homeKeywords: salesChannel.homeKeywords,
                    translated: salesChannel.translated ? { ...salesChannel.translated } : null,
                });

                this.salesChannelOptions.push({
                    value: salesChannel.id,
                    label: salesChannel.translated ? salesChannel.translated.name : salesChannel.name,
                });
            });

            if (this.salesChannelCollection.length > 0) {
                this.selectedSalesChannelId = this.salesChannelOptions[0].value;
            }
        },

        closeModal() {
            this.$emit('modal-close');
        },

        openInPagebuilder() {
            const to = { name: 'sw.experience.studio.index' };

            if (this.hasNotAppliedChanges()) {
                this.isDisplayingLeavePageWarning = true;
                this.nextRoute = to;
                return;
            }

            this.closeModal();

            this.$nextTick(() => {
                this.$router.push(to);
            });
        },

        applyChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const tempSalesChannel = this.temporaryCollection[i];
                const realSalesChannel = this.salesChannelCollection[i];

                realSalesChannel.name = tempSalesChannel.name;
                realSalesChannel.homeEnabled = tempSalesChannel.homeEnabled;
                realSalesChannel.homeName = tempSalesChannel.homeName;
                realSalesChannel.homeMetaTitle = tempSalesChannel.homeMetaTitle;
                realSalesChannel.homeMetaDescription = tempSalesChannel.homeMetaDescription;
                realSalesChannel.homeKeywords = tempSalesChannel.homeKeywords;
            }

            this.closeModal();
        },

        hasNotAppliedChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const original = this.salesChannelCollection[i];
                const copy = this.temporaryCollection[i];

                if (
                    this.isAttributeEqual(copy.name, original.name) ||
                    this.isAttributeEqual(copy.homeEnabled, original.homeEnabled) ||
                    this.isAttributeEqual(copy.homeName, original.homeName) ||
                    this.isAttributeEqual(copy.homeMetaTitle, original.homeMetaTitle) ||
                    this.isAttributeEqual(copy.homeMetaDescription, original.homeMetaDescription) ||
                    this.isAttributeEqual(copy.homeKeywords, original.homeKeywords)
                ) {
                    return true;
                }
            }

            return false;
        },

        isAttributeEqual(copy, original) {
            if (copy === null) {
                copy = '';
            }

            if (original === null) {
                original = '';
            }

            return copy !== original;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            this.isDisplayingLeavePageWarning = false;
            this.$nextTick(() => {
                this.closeModal();
                this.$nextTick(() => {
                    this.$router.push({
                        name: destination.name,
                        params: destination.params,
                    });
                });
            });
        },
    },
};
