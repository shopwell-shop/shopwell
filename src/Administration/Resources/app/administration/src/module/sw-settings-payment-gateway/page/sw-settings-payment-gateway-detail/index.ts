/**
 * @sw-package framework
 */
import type {
    PaymentAppView,
    PaymentProviderView,
    PaymentProductDefinition,
    PaymentAccountChoice,
} from 'src/app/service/payment-gateway.types';
import template from './sw-settings-payment-gateway-detail.html.twig';
import './sw-settings-payment-gateway-detail.scss';

type AccountGrant = { key: string; accountId: string | null; enabled: boolean; position: number };
type ProductState = {
    technicalName: string;
    label: string;
    interactions: string[];
    methodTranslationKey: string;
    enabled: boolean;
    settings: Record<string, unknown>;
    providerTechnicalName: string;
};

const { Mixin } = Shopwell;

const NEW_APP_ID = 'new';
const GENERATED_SECRET_BYTES = 32;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['paymentGatewayService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        section: { type: String, required: false, default: 'collection' },
        appId: {
            type: String,
            required: false,
            default: null,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data(): {
        app: PaymentAppView | null;
        registryProviders: PaymentProviderView[];
        apps: PaymentAppView[];
        availableAccounts: PaymentAccountChoice[];
        providers: AccountGrant[];
        products: ProductState[];
        allowedIpsInput: string;
        appSecretInput: string;
        generatedSecret: string;
        isSecretModalOpen: boolean;
        isLoading: boolean;
        isSaving: boolean;
        isDeleting: boolean;
        isDeleteModalOpen: boolean;
    } {
        return {
            app: null,
            registryProviders: [],
            apps: [],
            availableAccounts: [],
            providers: [],
            products: [],
            allowedIpsInput: '',
            appSecretInput: '',
            generatedSecret: '',
            isSecretModalOpen: false,
            isLoading: true,
            isSaving: false,
            isDeleting: false,
            isDeleteModalOpen: false,
        };
    },

    computed: {
        activeSection() {
            return ['collection', 'cashier', 'integration'].includes(this.section) ? this.section : 'collection';
        },

        viewTabs() {
            return ['collection', 'cashier', 'integration'].map((section) => ({
                name: section,
                label: this.$t(`sw-settings-payment-gateway.tabs.${section}`),
                onClick: () =>
                    this.$router.push({
                        name: 'sw.settings.payment.gateway.detail',
                        params: { id: this.appId || 'new', section },
                    }),
            }));
        },

        methodOptions() {
            const options = new Map<string, { value: string; label: string }>();
            this.registryProviders.forEach((provider) =>
                (provider.products || []).forEach((product) => {
                    if (product.method) {
                        options.set(product.method, {
                            value: product.method,
                            label: this.displayLabel(product.methodTranslationKey || product.method),
                        });
                    }
                }),
            );
            return [...options.values()];
        },

        isNew() {
            return !this.appId || this.appId === NEW_APP_ID;
        },

        canEdit() {
            return this.acl.can('payment_app.editor');
        },

        canCreate() {
            return this.acl.can('payment_app.creator');
        },

        canDelete() {
            return this.acl.can('payment_app.deleter');
        },

        isPlatformTenant() {
            return this.app !== null && this.app.type === 'platform';
        },

        isReadOnly() {
            return !this.canEdit;
        },

        canAddAccount() {
            return (
                !this.providers.some((grant) => !grant.accountId) &&
                this.availableAccounts.some(
                    (account) => account.active && !this.providers.some((grant) => grant.accountId === account.id),
                )
            );
        },

        pageTitle() {
            if (!this.app) {
                return '';
            }

            return this.app.name || this.app.technicalName;
        },
    },

    watch: {
        appId() {
            void this.createdComponent();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        displayLabel(label: string | undefined) {
            if (!label) {
                return '';
            }

            return this.$te(label) ? this.$t(label) : label;
        },

        async createdComponent() {
            this.isLoading = true;

            try {
                const [registryProviders, configuration] = await Promise.all([
                    this.paymentGatewayService.listProviderDefinitions(),
                    this.paymentGatewayService.listAppConfiguration(),
                ]);

                const { apps, accounts } = configuration;
                this.availableAccounts = accounts;
                const found = this.isNew ? null : (apps.find((app) => app.id === this.appId) ?? null);

                if (!this.isNew && !found) {
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t('sw-settings-payment-gateway.detail.notFound'),
                    });

                    void this.$router.push({ name: 'sw.settings.payment.gateway.list' });

                    return;
                }

                this.app = found ?? this.createEmptyApp();
                this.apps = apps;
                this.registryProviders = registryProviders;
                this.allowedIpsInput = (this.app.allowedIps || []).join('\n');
                this.providers = this.createAccountStates();
                this.products = this.createProductStates(registryProviders);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : this.$t('global.default.error'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        createEmptyApp(): PaymentAppView {
            return {
                id: null,
                technicalName: '',
                name: '',
                type: 'external',
                active: false,
                notifyUrl: null,
                returnUrl: null,
                allowedIps: [],
                rateLimit: null,
                hasSecret: false,
                accounts: [],
                channels: [],
            };
        },

        createAccountStates() {
            return (this.app?.accounts ?? []).map((grant) => ({ ...grant, key: grant.accountId }));
        },

        addAccount() {
            if (!this.canAddAccount) {
                return;
            }

            this.providers.push({
                key: Shopwell.Utils.createId(),
                accountId: null,
                enabled: true,
                position: this.providers.length,
            });
        },

        removeAccount(grant: AccountGrant) {
            this.providers = this.providers.filter((item) => item.key !== grant.key);
        },

        selectedAccount(grant: AccountGrant) {
            return this.availableAccounts.find((account) => account.id === grant.accountId) || null;
        },

        providerLabel(technicalName: string) {
            const provider = this.registryProviders.find((item) => item.technicalName === technicalName);
            return this.displayLabel(provider?.label || technicalName);
        },

        accountLabel(account: PaymentAccountChoice) {
            return account.isDefault && account.name === `${account.providerTechnicalName} platform`
                ? account.providerTechnicalName
                : account.name;
        },

        accountOptions(grant: AccountGrant) {
            return this.availableAccounts
                .filter(
                    (account) =>
                        (account.active || account.id === grant.accountId) &&
                        !this.providers.some((row) => row.key !== grant.key && row.accountId === account.id),
                )
                .map((account) => ({ value: account.id, label: this.accountLabel(account) }));
        },

        createProductStates(registry: PaymentProviderView[]): ProductState[] {
            return registry.flatMap((provider) =>
                (provider.products || []).map((product) => ({
                    ...this.createChannelState(product),
                    providerTechnicalName: provider.technicalName,
                })),
            );
        },

        hasGrantedAccount(product: ProductState) {
            return this.providers.some(
                (grant) =>
                    this.selectedAccount(grant)?.providerTechnicalName === product.providerTechnicalName && grant.enabled,
            );
        },

        createChannelState(channel: PaymentProductDefinition) {
            const assignment = (this.app?.channels ?? []).find((item) => item.channelTechnicalName === channel.product);

            return {
                technicalName: channel.product,
                label: channel.label,
                interactions: channel.interactions || [],
                methodTranslationKey: channel.methodTranslationKey || channel.method || '',
                enabled: assignment ? assignment.enabled : false,
                settings: assignment?.settings || {},
            };
        },

        onGenerateSecret() {
            const bytes = new Uint8Array(GENERATED_SECRET_BYTES);
            window.crypto.getRandomValues(bytes);
            this.appSecretInput = Array.from(bytes)
                .map((byte) => byte.toString(16).padStart(2, '0'))
                .join('');
        },

        parseAllowedIps() {
            return this.allowedIpsInput
                .split('\n')
                .map((ip) => ip.trim())
                .filter((ip) => ip !== '');
        },

        buildPayload() {
            if (!this.app) {
                throw new Error(this.$t('sw-settings-payment-gateway.detail.notFound'));
            }

            return {
                id: this.app.id,
                technicalName: this.app.technicalName,
                name: this.app.name,
                active: this.app.active,
                notifyUrl: this.app.notifyUrl,
                returnUrl: this.app.returnUrl,
                allowedIps: this.parseAllowedIps(),
                rateLimit: this.app.rateLimit,
                appSecret: this.appSecretInput,
                accounts: this.providers
                    .filter((grant) => grant.accountId)
                    .map((grant) => ({
                        accountId: grant.accountId,
                        enabled: grant.enabled,
                        position: grant.position,
                    })),
                channels: this.products.map((product) => ({
                    channelTechnicalName: product.technicalName,
                    enabled: product.enabled,
                    settings: product.settings,
                })),
            };
        },

        async onSave() {
            this.isSaving = true;

            try {
                const response = await this.paymentGatewayService.saveApp(this.buildPayload());

                this.app = response.app;
                this.apps = [...this.apps.filter((app) => app.id !== response.app.id), this.app];
                this.allowedIpsInput = (this.app.allowedIps || []).join('\n');
                this.appSecretInput = '';

                this.providers = this.createAccountStates();
                this.products = this.createProductStates(this.registryProviders);

                if (response.appSecret) {
                    this.generatedSecret = response.appSecret;
                    this.isSecretModalOpen = true;
                }

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-gateway.notification.saveSuccess'),
                });

                if (this.isNew) {
                    void this.$router.replace({
                        name: 'sw.settings.payment.gateway.detail',
                        params: { id: this.app.id as string },
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : this.$t('global.default.error'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        onCloseSecretModal() {
            this.isSecretModalOpen = false;
            this.generatedSecret = '';
        },

        onSecretModalChange(isOpen: boolean) {
            if (!isOpen) {
                this.onCloseSecretModal();
            }
        },

        onOpenDeleteModal() {
            this.isDeleteModalOpen = true;
        },

        onDeleteModalChange(isOpen: boolean) {
            if (!isOpen) {
                this.isDeleteModalOpen = false;
            }
        },

        async onConfirmDelete() {
            this.isDeleting = true;

            try {
                if (!this.app?.id) {
                    return;
                }

                await this.paymentGatewayService.deleteApp(this.app.id);
                this.isDeleteModalOpen = false;

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-gateway.notification.deleteSuccess'),
                });

                void this.$router.push({ name: 'sw.settings.payment.gateway.list' });
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : this.$t('global.default.error'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
