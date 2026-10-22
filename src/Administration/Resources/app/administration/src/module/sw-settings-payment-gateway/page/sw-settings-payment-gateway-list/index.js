/**
 * @sw-package framework
 */
import template from './sw-settings-payment-gateway-list.html.twig';
import './sw-settings-payment-gateway-list.scss';

const { Mixin } = Shopwell;

/**
 * The kind of row the platform itself collects through. It is seeded by the migration, listed here so
 * a merchant can see which account the platform is paid to, and read only everywhere: the server
 * refuses every write to it, this page only renders it.
 */
const PLATFORM_TYPE = 'platform';

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
            apps: [],
            term: '',
            isLoading: true,
            isDeleting: false,
            deleteTarget: null,
        };
    },

    computed: {
        appColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-payment-gateway.list.columnName',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: 'sw-settings-payment-gateway.list.columnActive',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'providers',
                    label: 'sw-settings-payment-gateway.list.columnProviders',
                    align: 'center',
                    allowResize: true,
                },
                {
                    property: 'channels',
                    label: 'sw-settings-payment-gateway.list.columnChannels',
                    align: 'center',
                    allowResize: true,
                },
            ];
        },

        /**
         * The apps of the endpoint are a plain list, not an entity collection, so the search is done
         * here. The technical name is searched as well even though it is not displayed: it is the id
         * an external app identifies itself with, so an operator who has it in hand still finds the row.
         */
        filteredApps() {
            const term = this.term.trim().toLowerCase();

            if (term === '') {
                return this.apps;
            }

            return this.apps.filter((app) => {
                return [app.name, app.technicalName]
                    .filter((value) => Boolean(value))
                    .some((value) => value.toLowerCase().includes(term));
            });
        },

        createButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('payment_app.creator'),
                showOnDisabledElements: true,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadApps();
        },

        async loadApps() {
            this.isLoading = true;

            try {
                this.apps = await this.paymentGatewayService.listApps();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(term) {
            this.term = term;
        },

        /**
         * The row of the platform is read only: it has no detail page to open and no delete action.
         * The server refuses both as well, so hiding them is what keeps the page honest instead of
         * letting an operator run into a refusal.
         */
        isPlatformApp(app) {
            return app?.type === PLATFORM_TYPE;
        },

        /**
         * An app may be authorized for an interface without offering it, only the switched on ones
         * are the interfaces the app can really collect through.
         */
        enabledProviderCount(app) {
            return (app?.providers ?? []).filter((provider) => provider.enabled).length;
        },

        /**
         * The channels the app offers to its own users - a channel that is not switched on cannot be
         * chosen when a payment is started.
         */
        enabledChannelCount(app) {
            return (app?.channels ?? []).filter((channel) => channel.enabled).length;
        },

        onCreate() {
            void this.$router.push({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
        },

        onOpenDeleteModal(app) {
            // the action is not rendered for the platform row, but a row is also reachable through
            // other entry points - the guard is what makes "read only" hold for all of them
            if (this.isPlatformApp(app)) {
                return;
            }

            this.deleteTarget = app;
        },

        onCloseDeleteModal() {
            this.deleteTarget = null;
        },

        onDeleteModalChange(isOpen) {
            if (!isOpen) {
                this.onCloseDeleteModal();
            }
        },

        async onConfirmDelete() {
            if (!this.deleteTarget) {
                this.onCloseDeleteModal();

                return;
            }

            const id = this.deleteTarget.id;
            this.isDeleting = true;

            try {
                await this.paymentGatewayService.deleteApp(id);
                this.onCloseDeleteModal();

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-gateway.notification.deleteSuccess'),
                });

                await this.loadApps();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
};
