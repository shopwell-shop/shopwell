/**
 * @sw-package framework
 */
import template from './sw-settings-payment-gateway-list.html.twig';

const { Mixin } = Shopwell;

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

        onRefresh() {
            return this.loadApps();
        },

        onCreate() {
            void this.$router.push({ name: 'sw.settings.payment.gateway.detail', params: { id: 'new' } });
        },

        onEdit(app) {
            void this.$router.push({ name: 'sw.settings.payment.gateway.detail', params: { id: app.id } });
        },

        onOpenDeleteModal(app) {
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
