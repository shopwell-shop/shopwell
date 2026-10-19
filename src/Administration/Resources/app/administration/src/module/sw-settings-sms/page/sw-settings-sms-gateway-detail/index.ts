/**
 * @sw-package framework
 */
import type { SmsCredentialDefinition, SmsGatewayProvider } from '../../service/sms-gateway-provider.service';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-gateway-detail.html.twig';

const { Mixin } = Shopwell;

/**
 * The custom field that says a gateway also takes a number whose country the shop does not know.
 *
 * It is a custom field and not a column, because it belongs to the routing of one installation the
 * way the credentials of an account do - and not into the options, which are handed to the provider
 * as they are.
 */
const CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION = 'sms_accept_unknown_region';

/**
 * The form field type of a credential type. What a value is lies with the provider, how it is typed
 * in lies with the administration - a secret is a password input, a structure is a text area.
 */
const FORM_FIELD_TYPES: Record<string, string> = {
    string: 'text',
    secret: 'password',
    json: 'textarea',
    bool: 'switch',
};

/**
 * One account of a provider: the credentials it is called with and the routing it takes part in.
 *
 * The credential form is not written here, it is rendered from what the provider declares, so a
 * provider an extension added shows up with its own fields without touching this page.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-gateway-detail',

    template,

    inject: ['repositoryFactory', 'acl', 'smsGatewayProviderService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        gatewayId: {
            // the route hands the id over, so the type of the key is already known here
            type: String as unknown as PropType<EntityKey<'sms_gateway'> | null>,
            required: false,
            default: null,
        },
    },

    data(): {
        gateway: EntitySchema.Entity<'sms_gateway'> | null;
        providers: SmsGatewayProvider[];
        credentialValues: Record<string, string | boolean>;
        optionsText: string;
        acceptUnknownRegion: boolean;
        isLoading: boolean;
        isSaving: boolean;
    } {
        return {
            gateway: null,
            providers: [],
            credentialValues: {},
            optionsText: '',
            acceptUnknownRegion: false,
            isLoading: true,
            isSaving: false,
        };
    },

    computed: {
        isNew(): boolean {
            return this.gatewayId === null;
        },

        gatewayRepository(): RepositoryType<'sms_gateway'> {
            return this.repositoryFactory.create('sms_gateway');
        },

        provider(): SmsGatewayProvider | null {
            const technicalName = this.gateway?.technicalName;

            if (!technicalName) {
                return null;
            }

            return this.providers.find((entry) => entry.technicalName === technicalName) ?? null;
        },

        credentialFields(): SmsCredentialDefinition[] {
            return this.provider?.credentialDefinitions ?? [];
        },

        providerOptions(): Array<{ value: string; label: string }> {
            return this.providers.map((entry) => {
                return {
                    value: entry.technicalName,
                    label: this.providerLabel(entry),
                };
            });
        },

        strategyOptions(): Array<{ value: string; label: string }> {
            return ['exclusive', 'failover', 'round_robin'].map((strategy) => {
                return {
                    value: strategy,
                    label: this.$t(`sw-settings-sms.gateway.strategy.${strategy}`),
                };
            });
        },

        title(): string {
            return this.isNew
                ? this.$t('sw-settings-sms.gateway.detailTitleCreate')
                : this.$t('sw-settings-sms.gateway.detailTitleEdit');
        },

        templateModeLabel(): string {
            return this.provider === null
                ? ''
                : this.$t(
                      this.provider.supportsTemplateMode
                          ? 'sw-settings-sms.gateway.templateModeRequired'
                          : 'sw-settings-sms.gateway.templateModeFreeText',
                  );
        },

        regionsLabel(): string {
            if (this.provider === null || this.provider.supportedRegions.length === 0) {
                return this.$t('sw-settings-sms.gateway.regionGlobal');
            }

            return this.provider.supportedRegions.join(', ');
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                await this.loadProviders();

                if (this.gatewayId === null) {
                    const gateway = this.gatewayRepository.create();
                    gateway.enabled = true;
                    gateway.priority = 0;
                    gateway.strategy = 'exclusive';
                    gateway.technicalName = this.providers[0]?.technicalName ?? '';

                    this.gateway = gateway;
                } else {
                    await this.loadGateway();
                }

                this.fillCredentialValues();
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async loadProviders(): Promise<void> {
            this.providers = await this.smsGatewayProviderService.list();
        },

        async loadGateway(): Promise<void> {
            if (this.gatewayId === null) {
                return;
            }

            this.gateway = await this.gatewayRepository.get(this.gatewayId, Shopwell.Context.api);
            this.optionsText = this.gateway?.options ? JSON.stringify(this.gateway.options, null, 2) : '';
            this.acceptUnknownRegion = this.storedAcceptUnknownRegion();
        },

        /**
         * The routing flag is kept in the custom fields of the row, which are an opaque bag - a row
         * that was never saved carries none, and then the gateway does not take unknown countries.
         */
        storedAcceptUnknownRegion(): boolean {
            const customFields = this.gateway?.customFields;

            if (!customFields || typeof customFields !== 'object') {
                return false;
            }

            return (customFields as Record<string, unknown>)[CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION] === true;
        },

        /**
         * Writes the routing flag into the custom fields and keeps everything else that is in them:
         * the answers of the audit of this module live there as well.
         */
        collectCustomFields(): Record<string, unknown> {
            const customFields = this.gateway?.customFields;
            const collected: Record<string, unknown> =
                customFields && typeof customFields === 'object' ? { ...(customFields as Record<string, unknown>) } : {};

            collected[CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION] = this.acceptUnknownRegion;

            return collected;
        },

        /**
         * The fields the provider declares hold the values that were stored, a field the provider
         * dropped in a newer version is simply not shown any more.
         */
        fillCredentialValues(): void {
            const stored = (this.gateway?.credentials ?? {}) as Record<string, unknown>;
            const values: Record<string, string | boolean> = {};

            this.credentialFields.forEach((field) => {
                const storedValue = stored[field.name];

                values[field.name] =
                    field.type === 'bool'
                        ? storedValue === true
                        : typeof storedValue === 'string' || typeof storedValue === 'number'
                          ? String(storedValue)
                          : '';
            });

            this.credentialValues = values;
        },

        providerLabel(provider: SmsGatewayProvider): string {
            return this.$te(provider.labelSnippet) ? this.$t(provider.labelSnippet) : provider.technicalName;
        },

        credentialLabel(field: SmsCredentialDefinition): string {
            return this.$te(field.labelSnippet) ? this.$t(field.labelSnippet) : field.name;
        },

        formFieldType(field: SmsCredentialDefinition): string {
            return FORM_FIELD_TYPES[field.type] ?? 'text';
        },

        formFieldConfig(field: SmsCredentialDefinition) {
            return {
                label: this.credentialLabel(field),
                required: field.required,
            };
        },

        setCredentialValue(field: SmsCredentialDefinition, value: string | boolean): void {
            this.credentialValues[field.name] = value;
        },

        /**
         * A provider was picked: what it needs is declared by it, so the values of the provider that
         * was picked before do not belong to the new one.
         */
        onProviderChange(): void {
            this.credentialValues = {};

            this.credentialFields.forEach((field) => {
                this.credentialValues[field.name] = field.type === 'bool' ? false : '';
            });
        },

        isEmpty(value: unknown): boolean {
            return value === null || value === undefined || value === '';
        },

        collectCredentials(): Record<string, unknown> | null {
            const credentials: Record<string, unknown> = {};

            this.credentialFields.forEach((field) => {
                credentials[field.name] = this.credentialValues[field.name] ?? null;
            });

            return Object.keys(credentials).length === 0 ? null : credentials;
        },

        collectOptions(): Record<string, unknown> | null {
            if (this.optionsText.trim() === '') {
                return null;
            }

            return JSON.parse(this.optionsText) as Record<string, unknown>;
        },

        async onSave(): Promise<void> {
            if (this.gateway === null) {
                return;
            }

            if (this.credentialFields.some((field) => field.required && this.isEmpty(this.credentialValues[field.name]))) {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.notification.missingFields'),
                });

                return;
            }

            let options: Record<string, unknown> | null = null;

            try {
                options = this.collectOptions();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.gateway.labelOptionsHelpText'),
                });

                return;
            }

            this.isSaving = true;

            try {
                this.gateway.credentials = this.collectCredentials();
                this.gateway.options = options;
                // the routing flag of the shop belongs to the row, not to the connection options the
                // provider is called with
                this.gateway.customFields = this.collectCustomFields();

                await this.gatewayRepository.save(this.gateway);

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-sms.notification.saveSuccess'),
                });

                if (this.isNew) {
                    // the route brings the id, the page loads the row it just created
                    void this.$router.push({
                        name: 'sw.settings.sms.gateway.detail',
                        params: { id: this.gateway?.id ?? '' },
                    });

                    return;
                }

                // saving through the repository leaves the origin state behind, it is reloaded so the
                // change tracking does not report the write twice
                await this.loadGateway();
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
