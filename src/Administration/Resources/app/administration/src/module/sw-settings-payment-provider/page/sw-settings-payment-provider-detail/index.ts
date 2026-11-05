/**
 * @sw-package framework
 */
import type { PaymentAccountView } from 'src/module/sw-payment/service/payment-gateway.api.service';
import type { PaymentConfigField } from 'src/module/sw-payment/component/sw-payment-config-field';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-settings-payment-provider-detail.html.twig';
import './sw-settings-payment-provider-detail.scss';

type Value = string | number | boolean | null;
type FieldValues = Record<string, Value>;
type ProductDefinition = { product: string; label: string; paramFields: PaymentConfigField[] };
type ProviderDefinition = {
    technicalName: string;
    label: string;
    credentialFields: PaymentConfigField[];
    products: ProductDefinition[];
};

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['paymentGatewayService', 'acl'],
    props: { accountId: { type: String, required: false, default: null } },
    data(): {
        account: PaymentAccountView | null;
        providers: ProviderDefinition[];
        credentials: FieldValues;
        productParams: Record<string, FieldValues>;
        isLoading: boolean;
        isSaving: boolean;
        error: string | null;
    } {
        return {
            account: null,
            providers: [],
            credentials: {},
            productParams: {},
            isLoading: true,
            isSaving: false,
            error: null,
        };
    },
    computed: {
        accountName: {
            get(): string {
                if (!this.account) return '';
                return this.account.isDefault && this.account.name === `${this.account.providerTechnicalName} platform`
                    ? this.account.providerTechnicalName
                    : this.account.name;
            },
            set(name: string): void {
                if (this.account) this.account.name = name;
            },
        },
        provider(): ProviderDefinition | null {
            return this.providers.find((item) => item.technicalName === this.account?.providerTechnicalName) || null;
        },
        providerOptions(): { value: string; label: string }[] {
            return this.providers.map((provider) => ({ value: provider.technicalName, label: this.label(provider.label) }));
        },
        shortFields(): PaymentConfigField[] {
            return (this.provider?.credentialFields || []).filter((field) => field.type !== 'textarea');
        },
        multilineFields(): PaymentConfigField[] {
            return (this.provider?.credentialFields || []).filter((field) => field.type === 'textarea');
        },
        products(): ProductDefinition[] {
            return this.provider?.products || [];
        },
        canEdit(): boolean {
            return this.acl.can(this.accountId ? 'payment_account.editor' : 'payment_account.creator');
        },
    },
    created(): void {
        void this.load();
    },
    methods: {
        label(label: string): string {
            return this.$te(label) ? this.$t(label) : label;
        },
        async load(): Promise<void> {
            try {
                const [providers, account] = await Promise.all([
                    this.paymentGatewayService.listAccountDefinitions(),
                    this.accountId ? this.paymentGatewayService.getAccount(this.accountId) : Promise.resolve(null),
                ]);
                this.providers = providers as ProviderDefinition[];
                this.account = account || {
                    id: '',
                    name: '',
                    providerTechnicalName: '',
                    active: false,
                    ownerAppId: null,
                    isDefault: false,
                    enabledProducts: [],
                    credentials: {},
                    productParams: {},
                    settings: {},
                };
                this.initializeFields();
            } catch {
                this.error = this.$t('sw-settings-payment-provider.accounts.loadError');
            } finally {
                this.isLoading = false;
            }
        },
        initializeFields(): void {
            const account = this.account;
            if (!account) return;
            this.credentials = Object.fromEntries(
                (this.provider?.credentialFields || []).map((field) => [
                    field.name,
                    account.credentials[field.name] ?? (!account.id ? field.default : null) ?? '',
                ]),
            );
            this.productParams = Object.fromEntries(
                (this.provider?.products || []).map((product) => [
                    product.product,
                    Object.fromEntries(
                        product.paramFields.map((field) => [
                            field.name,
                            account.productParams[product.product]?.[field.name] ??
                                (!account.id ? field.default : null) ??
                                '',
                        ]),
                    ),
                ]),
            );
        },
        selectProvider(value: string): void {
            if (!this.account || this.accountId) return;
            this.account.providerTechnicalName = value;
            this.initializeFields();
        },
        setProduct(product: string, enabled: boolean): void {
            if (!this.account) return;
            this.account.enabledProducts = enabled
                ? [...this.account.enabledProducts, product]
                : this.account.enabledProducts.filter((name) => name !== product);
        },
        async save(): Promise<void> {
            if (!this.account || !this.canEdit || !this.account.name.trim() || !this.provider) return;
            this.isSaving = true;
            this.error = null;
            try {
                this.account = await this.paymentGatewayService.saveAccount(this.accountId, {
                    name: this.account.name.trim(),
                    providerTechnicalName: this.account.providerTechnicalName,
                    active: this.account.active,
                    enabledProducts: this.account.enabledProducts,
                    credentials: this.credentials,
                    productParams: this.productParams,
                    settings: this.account.settings,
                });
                this.initializeFields();
                useNotification().createNotificationSuccess({
                    message: this.$t('sw-settings-payment-provider.accounts.saved'),
                });
                if (!this.accountId)
                    void this.$router.replace({
                        name: 'sw.settings.payment.provider.detail',
                        params: { id: this.account.id },
                    });
            } catch {
                this.error = this.$t('sw-settings-payment-provider.accounts.saveError');
            } finally {
                this.isSaving = false;
            }
        },
    },
});
