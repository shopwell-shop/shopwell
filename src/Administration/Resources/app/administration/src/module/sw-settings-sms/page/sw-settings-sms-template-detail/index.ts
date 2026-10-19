/**
 * @sw-package framework
 */
import type { SmsGatewayProvider } from '../../service/sms-gateway-provider.service';
import type RepositoryType from 'src/core/data/repository.data';
import {
    approvalStatusLabel,
    auditOfProvider,
    CUSTOM_FIELD_LAST_SYNCED_AT,
    customFieldValue,
} from '../../helper/sms-template-audit.helper';
import template from './sw-settings-sms-template-detail.html.twig';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

const SCENES = ['otp', 'transaction', 'marketing'];

/**
 * One line of the ledger: what one provider account answered for this template.
 *
 * There is no language in it on purpose - a mapping row carries one template and one submitted body,
 * and the language of a recipient never decides which of them is used.
 */
interface SmsTemplateAuditLedgerRow {
    mappingId: string;
    technicalName: string;
    providerLabel: string;
    templateCode: string | null;
    auditStatus: string;
    auditStatusLabel: string;
    lastSyncedAt: string | null;
}

/**
 * A logical template: the parameter contract, the bodies per language and where it goes at the
 * providers.
 *
 * The body is edited in the language the administration is switched to, like the one of a mail
 * template: a language without a body of its own inherits the one of the default language, which the
 * renderer falls back to.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-detail',

    template,

    inject: ['repositoryFactory', 'acl', 'smsGatewayProviderService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        templateId: {
            // the route hands the id over, so the type of the key is already known here
            type: String as unknown as PropType<EntityKey<'sms_template'> | null>,
            required: false,
            default: null,
        },
    },

    data(): {
        template: EntitySchema.Entity<'sms_template'> | null;
        providers: SmsGatewayProvider[];
        gateways: Array<EntitySchema.Entity<'sms_gateway'>>;
        paramsSchemaText: string;
        defaultLanguageLabel: string;
        isLoading: boolean;
        isSaving: boolean;
    } {
        return {
            template: null,
            providers: [],
            gateways: [],
            paramsSchemaText: '',
            defaultLanguageLabel: '',
            isLoading: true,
            isSaving: false,
        };
    },

    computed: {
        isNew(): boolean {
            return this.templateId === null;
        },

        templateRepository(): RepositoryType<'sms_template'> {
            return this.repositoryFactory.create('sms_template');
        },

        gatewayRepository(): RepositoryType<'sms_gateway'> {
            return this.repositoryFactory.create('sms_gateway');
        },

        languageRepository(): RepositoryType<'language'> {
            return this.repositoryFactory.create('language');
        },

        mappings(): Array<EntitySchema.Entity<'sms_template_mapping'>> {
            const collection = this.template?.mappings;

            return collection ? Array.from(collection) : [];
        },

        /**
         * The state of the audit at every provider this template is mapped to.
         *
         * It is built from the mappings of the template and not from the providers, so a provider an
         * extension adds shows up here without a change, and a row that was never submitted shows the
         * state the shop knows - which is the one it started in.
         */
        auditLedger(): SmsTemplateAuditLedgerRow[] {
            return this.mappings.map((mapping) => {
                return {
                    mappingId: mapping.id,
                    technicalName: mapping.technicalName,
                    providerLabel: this.providerLabelOf(mapping.technicalName),
                    templateCode: mapping.templateCode ?? null,
                    auditStatus: mapping.auditStatus,
                    auditStatusLabel: this.auditStatusLabel(mapping.auditStatus),
                    lastSyncedAt: customFieldValue(mapping.customFields, CUSTOM_FIELD_LAST_SYNCED_AT),
                };
            });
        },

        /**
         * The providers that block a message until a template is approved, but that this template is
         * not mapped to at all - the one state an operator cannot see in the ledger, as there is no
         * row to show it in.
         */
        providersMissingMapping(): SmsGatewayProvider[] {
            return this.providers.filter((provider) => {
                const requiresApproval = auditOfProvider(this.providers, provider.technicalName).requiresApproval;

                return requiresApproval && !this.mappings.some((row) => row.technicalName === provider.technicalName);
            });
        },

        /**
         * The locale of the default language, which is the language a submission is made in. Empty
         * while it is still being read, so the note never claims a language prematurely.
         */
        defaultLanguageName(): string {
            return this.defaultLanguageLabel;
        },

        /**
         * The columns of the ledger. There is no language in it: a mapping row carries one template
         * and one submitted body, and a delivered message is rendered in the language of its
         * recipient, which the audit does not change.
         */
        auditLedgerColumns(): Array<{ property: string; label: string }> {
            return [
                {
                    property: 'providerLabel',
                    label: 'sw-settings-sms.template.auditLedgerColumnProvider',
                },
                {
                    property: 'templateCode',
                    label: 'sw-settings-sms.mapping.columnTemplateCode',
                },
                {
                    property: 'auditStatusLabel',
                    label: 'sw-settings-sms.mapping.columnAuditStatus',
                },
                {
                    property: 'lastSyncedAt',
                    label: 'sw-settings-sms.template.auditLedgerColumnLastSyncedAt',
                },
            ];
        },

        /**
         * The providers of the banner, named the way the rest of the page names them.
         */
        missingProviderLabel(): string {
            return this.providersMissingMapping
                .map((provider) => this.providerLabelOf(provider.technicalName))
                .join(', ');
        },

        title(): string {
            return this.isNew
                ? this.$t('sw-settings-sms.template.detailTitleCreate')
                : this.$t('sw-settings-sms.template.detailTitleEdit');
        },

        sceneOptions(): Array<{ value: string; label: string }> {
            return SCENES.map((scene) => {
                return {
                    value: scene,
                    label: this.$t(`sw-settings-sms.template.scene.${scene}`),
                };
            });
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                await Promise.all([this.loadProviders(), this.loadGateways(), this.loadDefaultLanguage()]);

                if (this.templateId === null) {
                    const newTemplate = this.templateRepository.create();
                    newTemplate.scene = 'otp';
                    newTemplate.enabled = true;

                    this.template = newTemplate;
                } else {
                    await this.loadTemplate();
                }

                this.paramsSchemaText = this.template?.paramsSchema
                    ? JSON.stringify(this.template.paramsSchema, null, 2)
                    : '';
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

        async loadGateways(): Promise<void> {
            const result = await this.gatewayRepository.search(new Criteria(1, 100));

            this.gateways = result
                .getIds()
                .map((id) => result.get(id))
                .filter(
                    (gateway): gateway is EntitySchema.Entity<'sms_gateway'> => gateway !== undefined && gateway !== null,
                );
        },

        /**
         * The default language of the shop, which is the language a template is reported in.
         *
         * A failed read leaves the note without a locale instead of failing the page: what the
         * operator came for is the template, and the note is an explanation, not a value they edit.
         */
        async loadDefaultLanguage(): Promise<void> {
            const languageId = Shopwell.Context.api.systemLanguageId;

            this.defaultLanguageLabel = '';

            if (!languageId) {
                return;
            }

            try {
                const criteria = new Criteria(1, 1);
                criteria.addFilter(Criteria.equals('id', languageId));
                criteria.addAssociation('locale');

                const language = (await this.languageRepository.search(criteria)).first();

                this.defaultLanguageLabel = language?.locale?.name ?? language?.locale?.code ?? language?.name ?? '';
            } catch {
                this.defaultLanguageLabel = '';
            }
        },

        providerLabelOf(technicalName: string): string {
            const provider = this.providers.find((entry) => entry.technicalName === technicalName);

            if (provider) {
                return this.$te(provider.labelSnippet) ? this.$t(provider.labelSnippet) : provider.technicalName;
            }

            const snippet = `sms.gateway.${technicalName}`;

            return this.$te(snippet) ? this.$t(snippet) : technicalName;
        },

        /**
         * The states of the audit are named by the server, so a state it adds is shown without a
         * change here - a state without a label is shown as it came.
         */
        auditStatusLabel(status: string | null | undefined): string {
            return approvalStatusLabel(
                status,
                (key) => this.$te(key),
                (key) => this.$t(key),
            );
        },

        /**
         * The grid took one of the three audit steps: the template is read again, which also brings
         * the ledger up to date, as both are built from the same rows.
         */
        async onMappingChanged(): Promise<void> {
            await this.loadTemplate();
        },

        /**
         * The mappings come with the template, they are saved with it in one write.
         */
        async loadTemplate(): Promise<void> {
            if (this.templateId === null) {
                return;
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('id', this.templateId));
            criteria.addAssociation('translations');
            criteria.addAssociation('mappings');

            const result = await this.templateRepository.search(criteria);

            this.template = result.first() ?? null;
        },

        /**
         * The language the administration is switched to decides which body is shown, the page reads
         * the template again so the values of that language are the ones on screen.
         */
        async onLanguageChange(): Promise<void> {
            if (this.isNew) {
                return;
            }

            await this.loadTemplate();
        },

        collectParamsSchema(): Array<Record<string, unknown>> | null {
            if (this.paramsSchemaText.trim() === '') {
                return null;
            }

            return JSON.parse(this.paramsSchemaText) as Array<Record<string, unknown>>;
        },

        async onSave(): Promise<void> {
            if (this.template === null) {
                return;
            }

            let paramsSchema: Array<Record<string, unknown>> | null = null;

            try {
                paramsSchema = this.collectParamsSchema();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.template.labelParamsSchemaHelpText'),
                });

                return;
            }

            this.isSaving = true;

            try {
                this.template.paramsSchema = paramsSchema;

                await this.templateRepository.save(this.template);

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-sms.notification.saveSuccess'),
                });

                if (this.isNew) {
                    void this.$router.push({
                        name: 'sw.settings.sms.template.detail',
                        params: { id: this.template?.id ?? '' },
                    });

                    return;
                }

                await this.loadTemplate();
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
