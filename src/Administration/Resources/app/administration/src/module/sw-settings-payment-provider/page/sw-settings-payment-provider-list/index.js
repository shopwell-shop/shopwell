/**
 * @sw-package framework
 */
import template from './sw-settings-payment-provider-list.html.twig';
import './sw-settings-payment-provider-list.scss';

const { Mixin } = Shopwell;

/**
 * Overview of the payment interfaces of the platform.
 *
 * An interface is code, not data, so the overview only renders what the interfaces declare about
 * themselves: whether they are enabled, whether their credentials are configured and how many
 * channels they offer. The credentials themselves live on the detail page, like the settings of a
 * payment method do.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['paymentGatewayService', 'acl', 'feature'],

    mixins: [Mixin.getByName('notification')],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            providers: [],
            isLoading: true,
        };
    },

    computed: {
        canEdit() {
            return this.acl.can('payment_provider_config.editor');
        },

        canDelete() {
            return this.acl.can('payment_provider_config.deleter');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                await this.loadProviders();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The interfaces and their channels are code, only the configuration is data, so the list
         * comes from the server - including the labels and the fields of the forms.
         */
        async loadProviders() {
            this.providers = await this.paymentGatewayService.listProviders();
        },

        channelCount(provider) {
            return (provider.channels ?? []).length;
        },

        enabledChannelCount(provider) {
            return (provider.channels ?? []).filter((channel) => channel.enabled).length;
        },

        /**
         * The switch saves immediately: enabling an interface is one decision, not a form. The
         * backend keeps every credential that is not sent, so the toggle cannot wipe one. The model
         * is already flipped when the event arrives, so the rollback value is the opposite.
         */
        async onToggleProvider(provider) {
            const previous = !provider.enabled;

            try {
                await this.paymentGatewayService.saveProvider(provider.technicalName, {
                    enabled: provider.enabled,
                });

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-provider.notification.saveSuccess'),
                });
            } catch (error) {
                provider.enabled = previous;

                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            }
        },
    },
};
