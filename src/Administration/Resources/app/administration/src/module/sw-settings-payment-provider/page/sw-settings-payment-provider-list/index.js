/**
 * @sw-package framework
 */
import template from './sw-settings-payment-provider-list.html.twig';
import './sw-settings-payment-provider-list.scss';

const { Mixin } = Shopwell;

/**
 * Overview of the payment interfaces of the platform.
 *
 * An interface is code, not data, so the page only renders what the interfaces declare about
 * themselves: their name and whether they are switched on. The credentials and the channels are
 * edited on the detail page, like the settings of a payment method are.
 *
 * The page is a flow of cards - one card per interface, the link to the configuration and the switch
 * on one line - and follows the layout of the payment methods page, which shows one card per method
 * for the same reason: the entries are few and each of them carries a name and a toggle, which a
 * table would line up as columns of mostly empty space.
 *
 * Switching an interface off takes the collection away from every app that uses it, so the switch
 * asks before it writes - the dialog is the same one the rest of the administration uses for that.
 *
 * There is no create button: an interface cannot be created, it appears as soon as a plugin that
 * ships one is installed.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['paymentGatewayService', 'acl'],

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

            /**
             * The interface whose switch was clicked, and the state the switch asked for. While the
             * dialog is open both stay untouched, so cancelling it leaves the stored state as it was
             * and the switch stays where it is - it is bound to the stored state, not to the click.
             */
            toggleTarget: null,
            pendingEnabled: false,

            /**
             * The switch renders a native checkbox, which the browser flips on its own before the
             * click reaches us. While the dialog is open nothing writes the stored state, so Vue has
             * no new value to patch and the box would stay flipped after the dialog closes. Changing
             * the key rebuilds the switch from the stored state - that is what puts it back.
             */
            toggleRenderKey: 0,
        };
    },

    computed: {
        /**
         * The server hands the interfaces over in container order, which is the order the services
         * happen to be registered in - a plugin can install itself in the middle of it. Sorting by
         * the technical name keeps the card of an interface at the same place between two reloads.
         */
        sortedProviders() {
            return [...this.providers].sort((first, second) => {
                return String(first.technicalName ?? '').localeCompare(String(second.technicalName ?? ''));
            });
        },

        /**
         * The card links to the configuration of the interface, so the page may be opened without
         * being allowed to read one.
         */
        canView() {
            return this.acl.can('payment_provider_config.viewer');
        },

        /**
         * Switching an interface on or off writes the configuration, which is the editor privilege.
         */
        canEdit() {
            return this.acl.can('payment_provider_config.editor');
        },

        /**
         * The dialog says what the click costs, so its text depends on the direction of the switch:
         * enabling opens the interface to the apps, disabling closes it for them.
         */
        toggleModalTitle() {
            const snippet = this.pendingEnabled ? 'enableModalTitle' : 'disableModalTitle';

            return this.$t(`sw-settings-payment-provider.list.${snippet}`);
        },

        toggleModalText() {
            const snippet = this.pendingEnabled ? 'enableModalText' : 'disableModalText';

            return this.$t(`sw-settings-payment-provider.list.${snippet}`);
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

        /**
         * The label the interface declares is a snippet key for the interfaces of the platform and a
         * plain text for the ones a plugin ships, so the key is translated only when it exists.
         */
        displayLabel(provider) {
            const label = provider.label ?? '';

            return this.$te(label) ? this.$t(label) : label;
        },

        /**
         * The switch only announces the click. Disabling an interface takes the collection away from
         * every app that uses the platform credentials, so nothing is written before the merchant has
         * confirmed the dialog.
         */
        onRequestToggleProvider(provider, enabled) {
            this.toggleTarget = provider;
            this.pendingEnabled = enabled;
        },

        /**
         * The confirmation is the point of no return: the dialog closes and the switch writes the
         * state the merchant asked for.
         */
        async confirmToggle() {
            const provider = this.toggleTarget;
            const enabled = this.pendingEnabled;

            this.closeToggleModal();

            if (provider === null) {
                return;
            }

            await this.onToggleProvider(provider, enabled);
        },

        /**
         * Cancelling leaves everything as it was - the stored state was never touched, so the switch
         * stays in its place.
         */
        cancelToggle() {
            this.closeToggleModal();
        },

        closeToggleModal() {
            this.toggleTarget = null;
            this.pendingEnabled = false;
            this.toggleRenderKey += 1;
        },

        /**
         * Switching the interface on or off is a write, so the card shows the new state right away and
         * rolls it back when the request fails - leaving it there would show a state the server does
         * not have. The switch is bound one way, the value comes from its update event.
         */
        async onToggleProvider(provider, enabled) {
            const previous = provider.enabled;
            provider.enabled = enabled;

            try {
                await this.paymentGatewayService.saveProvider(provider.technicalName, {
                    enabled,
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
