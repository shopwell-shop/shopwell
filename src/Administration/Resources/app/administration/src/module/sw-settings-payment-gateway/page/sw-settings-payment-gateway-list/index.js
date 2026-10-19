/**
 * @sw-package framework
 */
import template from './sw-settings-payment-gateway-list.html.twig';

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
                    property: 'technicalName',
                    label: 'sw-settings-payment-gateway.list.columnTechnicalName',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'name',
                    label: 'sw-settings-payment-gateway.list.columnName',
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
                {
                    property: 'active',
                    label: 'sw-settings-payment-gateway.list.columnActive',
                    align: 'center',
                    allowResize: true,
                },
            ];
        },

        filteredApps() {
            const term = this.term.trim().toLowerCase();

            if (term === '') {
                return this.apps;
            }

            return this.apps.filter((app) => {
                return [app.technicalName, app.name]
                    .filter((value) => Boolean(value))
                    .some((value) => value.toLowerCase().includes(term));
            });
        },

        canCreate() {
            return this.acl.can('payment_app.creator');
        },

        canEdit() {
            return this.acl.can('payment_app.editor');
        },

        canDelete() {
            return this.acl.can('payment_app.deleter');
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
         * The name of an interface is a snippet key, not a text. The core ships the keys of its own
         * interfaces, a plugin ships its own; a key without an entry is shown as it is, so a missing
         * translation is visible instead of silent.
         */
        displayLabel(label) {
            if (!label) {
                return '';
            }

            return this.$te(label) ? this.$t(label) : label;
        },

        /**
         * The interfaces the app is authorized for and that are switched on.
         *
         * For the tenant of the platform these are what makes the account able to collect at all: an
         * app without an enabled interface cannot open a single payment, so the row names them instead
         * of leaving the merchant guessing whether the platform is ready.
         */
        enabledProviderLabels(app) {
            return (app?.providers ?? [])
                .filter((provider) => provider.enabled)
                .map((provider) => this.displayLabel(provider.providerTechnicalName));
        },

        onRefresh() {
            return this.loadApps();
        },

        onCreate() {
            void this.$router.push({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
        },

        onEdit(app) {
            // the action is not rendered for the platform row, but a row is also reachable through
            // other entry points - the guard is what makes "read only" hold for all of them
            if (this.isPlatformApp(app)) {
                return;
            }

            void this.$router.push({ name: 'sw.settings.payment.gateway.detail', params: { id: app.id } });
        },

        onOpenDeleteModal(app) {
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
