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
 *
 * The shape of the page follows the operator's question: which accounts are ready to collect and
 * which still miss a required credential. That is a comparison of a handful of rows, so the page is
 * a table - one row per interface - and not a stream of cards that has to be read one by one.
 *
 * There is no create button: an interface cannot be created, it appears as soon as a plugin that
 * ships one is installed.
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
        /**
         * The detail page is readable for a viewer: it only renders what the server reports and the
         * switches of an editor are disabled there. Gating the link on the editor right would hide
         * the whole page from a viewer, since the table is the only way to it.
         */
        canView() {
            return this.acl.can('payment_provider_config.viewer');
        },

        canEdit() {
            return this.acl.can('payment_provider_config.editor');
        },

        canDelete() {
            return this.acl.can('payment_provider_config.deleter');
        },

        /**
         * The server hands the interfaces over in container order, which is the order the services
         * happen to be registered in - a plugin can install itself in the middle of it. Sorting by
         * the technical name keeps the row of an interface at the same place between two reloads.
         */
        sortedProviders() {
            return [...this.providers].sort((first, second) => {
                return String(first.technicalName ?? '').localeCompare(String(second.technicalName ?? ''));
            });
        },

        /**
         * The columns of the grid. `label` is the primary column, so the row is named by the
         * interface itself and the technical name is only the second line of that cell.
         */
        providerColumns() {
            return [
                {
                    property: 'label',
                    label: 'sw-settings-payment-provider.list.columns.label',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'configured',
                    label: 'sw-settings-payment-provider.list.columns.status',
                    allowResize: true,
                },
                {
                    property: 'credentials',
                    label: 'sw-settings-payment-provider.list.columns.credentials',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'channels',
                    label: 'sw-settings-payment-provider.list.columns.channels',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'actions',
                    label: 'sw-settings-payment-provider.list.columns.actions',
                    align: 'right',
                    allowResize: true,
                },
            ];
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
         *
         * The grid addresses a row by `id`, an interface is addressed by its technical name, so the
         * name is handed over as the row id as well.
         */
        async loadProviders() {
            const providers = await this.paymentGatewayService.listProviders();

            this.providers = providers.map((provider) => {
                return { ...provider, id: provider.technicalName };
            });
        },

        channelCount(provider) {
            return (provider.channels ?? []).length;
        },

        enabledChannelCount(provider) {
            return (provider.channels ?? []).filter((channel) => channel.enabled).length;
        },

        /**
         * A field counts as filled when a value is stored for it. A secret is never handed over
         * with its value, so an empty value next to `configured` still means "stored" - and a field
         * that is not required is not part of the count at all.
         */
        isFieldFilled(field) {
            if (field.configured === true) {
                return true;
            }

            if (field.value === null || field.value === undefined) {
                return false;
            }

            return String(field.value) !== '';
        },

        requiredCredentialCount(provider) {
            return (provider.credentialFields ?? []).filter((field) => field.required === true).length;
        },

        filledCredentialCount(provider) {
            return (provider.credentialFields ?? []).filter((field) => {
                return field.required === true && this.isFieldFilled(field);
            }).length;
        },

        missingCredentialCount(provider) {
            return this.requiredCredentialCount(provider) - this.filledCredentialCount(provider);
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
