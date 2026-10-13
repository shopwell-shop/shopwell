/**
 * @sw-package framework
 */
import type { SmsGatewayProvider } from '../../service/sms-gateway-provider.service';
import template from './sw-settings-sms-template-mapping-grid.html.twig';

const AUDIT_STATUSES = [
    'pending',
    'approved',
    'rejected',
    'suspended',
];

/**
 * The rows that land one logical template on the concrete template of a provider.
 *
 * A row without a gateway account holds for every account of the provider, which the providers with
 * a shared template pool need. The grid is edited in place and saved with the template, so a new
 * template and its mappings are one write.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-mapping-grid',

    template,

    inject: ['repositoryFactory'],

    props: {
        template: {
            type: Object,
            required: true,
        },
        providers: {
            type: Array,
            required: true,
        },
        gateways: {
            type: Array,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        /**
         * The prop is an object, so the entity it carries is typed here once instead of on every use.
         */
        templateEntity(): EntitySchema.Entity<'sms_template'> {
            return this.template as EntitySchema.Entity<'sms_template'>;
        },

        mappingCollection(): EntityCollection<'sms_template_mapping'> | null {
            return this.templateEntity.mappings ?? null;
        },

        mappings(): Array<EntitySchema.Entity<'sms_template_mapping'>> {
            const collection = this.mappingCollection;

            return collection ? Array.from(collection) : [];
        },

        providerOptions(): Array<{ value: string; label: string }> {
            return (this.providers as SmsGatewayProvider[]).map((provider) => {
                return {
                    value: provider.technicalName,
                    label: this.providerLabel(provider),
                };
            });
        },

        /**
         * Every account of the shop. A row of one provider can only point at an account of that
         * provider, the mapping is answered with the account's template, so the accounts are labelled
         * with their provider.
         */
        gatewayOptions(): Array<{ value: string; label: string }> {
            return (this.gateways as Array<EntitySchema.Entity<'sms_gateway'>>).map((gateway) => {
                return {
                    value: gateway.id,
                    label: `${this.providerLabelOf(gateway.technicalName)} · ${gateway.label || gateway.technicalName}`,
                };
            });
        },

        auditStatusOptions(): Array<{ value: string; label: string }> {
            return AUDIT_STATUSES.map((status) => {
                return {
                    value: status,
                    label: this.$t(`sw-settings-sms.mapping.auditStatus.${status}`),
                };
            });
        },
    },

    methods: {
        providerLabel(provider: SmsGatewayProvider): string {
            return this.$te(provider.labelSnippet) ? this.$t(provider.labelSnippet) : provider.technicalName;
        },

        providerLabelOf(technicalName: string): string {
            const snippet = `sms.gateway.${technicalName}`;

            return this.$te(snippet) ? this.$t(snippet) : technicalName;
        },

        /**
         * The providers that report a template need one, the ones that take a free text body do not -
         * the field is only pointed out, the server checks it again before a message is built.
         */
        needsTemplateCode(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            const provider = (this.providers as SmsGatewayProvider[]).find(
                (entry) => entry.technicalName === mapping.technicalName,
            );

            return provider?.supportsTemplateMode ?? false;
        },

        onAddMapping(): void {
            const collection = this.mappingCollection;

            if (!collection) {
                return;
            }

            const mapping = this.repositoryFactory.create('sms_template_mapping').create();

            mapping.templateId = this.templateEntity.id;
            mapping.technicalName = (this.providers as SmsGatewayProvider[])[0]?.technicalName ?? '';
            mapping.auditStatus = 'pending';
            // no account is picked yet: a row without one holds for every account of the provider

            collection.add(mapping);
        },

        onRemoveMapping(mapping: EntitySchema.Entity<'sms_template_mapping'>): void {
            this.mappingCollection?.remove(mapping.id);
        },
    },
});
