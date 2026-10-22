/**
 * @sw-package framework
 */
import type {
    SmsApprovalStatus,
    SmsGatewayAudit,
    SmsGatewayProvider,
    SmsSubmissionField,
} from '../../service/sms-gateway-provider.service';
import type { SmsTemplateAuditAnswer } from '../../service/sms-template-audit.service';
import {
    approvalStatusLabel,
    auditOfProvider,
    CUSTOM_FIELD_LAST_SYNCED_AT,
    CUSTOM_FIELD_STATUS_CODE,
    CUSTOM_FIELD_STATUS_MESSAGE,
    customFieldValue,
    providerOf,
} from '../../helper/sms-template-audit.helper';
import template from './sw-settings-sms-template-mapping-grid.html.twig';

const { Mixin } = Shopwell;

/**
 * The states an operator may record by hand. They are a subset of the states the server answers with
 * its labels: pending is what a submission produces and unknown is what the shop does not know, so
 * neither may be set by hand.
 */
const CONFIRMABLE_STATUSES = ['approved', 'rejected', 'suspended'];

const NO_ANSWER: SmsTemplateAuditAnswer = {
    status: null,
    templateCode: null,
    errorCode: null,
    errorMessage: null,
};

/**
 * The rows that land one logical template on the concrete template of a provider.
 *
 * A row without a gateway account holds for every account of the provider, which the providers with
 * a shared template pool need. The grid is edited in place and saved with the template, so a new
 * template and its mappings are one write.
 *
 * The audit state is not typed here: it is what the provider answers, so the grid only reads it and
 * offers the steps the provider of the row declares. Which steps those are is never derived from the
 * name of a provider - an extension that adds one brings its capabilities with it.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-mapping-grid',

    template,

    inject: ['repositoryFactory', 'smsGatewayProviderService', 'smsTemplateAuditService'],

    mixins: [Mixin.getByName('notification')],

    emits: ['reload'],

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

    data(): {
        submissionTarget: EntitySchema.Entity<'sms_template_mapping'> | null;
        confirmationTarget: EntitySchema.Entity<'sms_template_mapping'> | null;
        confirmationStatus: string;
        confirmationNote: string;
        isConfirming: boolean;
        busyMappingId: string | null;
        approvalStatuses: SmsApprovalStatus[];
    } {
        return {
            submissionTarget: null,
            confirmationTarget: null,
            confirmationStatus: '',
            confirmationNote: '',
            isConfirming: false,
            busyMappingId: null,
            approvalStatuses: [],
        };
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

        columns(): Array<{ property: string; label: string }> {
            return [
                {
                    property: 'technicalName',
                    label: 'sw-settings-sms.mapping.columnTechnicalName',
                },
                {
                    property: 'gatewayId',
                    label: 'sw-settings-sms.mapping.columnGateway',
                },
                {
                    property: 'templateCode',
                    label: 'sw-settings-sms.mapping.columnTemplateCode',
                },
                {
                    property: 'signName',
                    label: 'sw-settings-sms.mapping.columnSignName',
                },
                {
                    property: 'auditStatus',
                    label: 'sw-settings-sms.mapping.columnAuditStatus',
                },
            ];
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

        /**
         * The states an operator may record, with the labels the server keys them to.
         */
        confirmationStatusOptions(): Array<{ value: string; label: string }> {
            return this.approvalStatuses.map((status) => {
                return {
                    value: status.value,
                    label: this.$te(status.labelSnippet) ? this.$t(status.labelSnippet) : status.value,
                };
            });
        },

        /**
         * The fields of the form of the row whose submission is open, declared by its provider.
         */
        submissionFields(): SmsSubmissionField[] {
            if (this.submissionTarget === null) {
                return [];
            }

            return this.auditOf(this.submissionTarget).submissionFields;
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
         * The provider a row points to, which is what decides which steps the row offers.
         */
        providerFor(mapping: EntitySchema.Entity<'sms_template_mapping'>): SmsGatewayProvider | null {
            return providerOf(this.providers as SmsGatewayProvider[], mapping.technicalName);
        },

        auditOf(mapping: EntitySchema.Entity<'sms_template_mapping'>): SmsGatewayAudit {
            return auditOfProvider(this.providers as SmsGatewayProvider[], mapping.technicalName);
        },

        canSubmit(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            return this.auditOf(mapping).canSubmit === true;
        },

        canReadStatus(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            return this.auditOf(mapping).canReadStatus === true;
        },

        /**
         * A state may be recorded by hand only where the shop cannot learn it itself - and only
         * where the provider needs an approved template at all, as the state of a provider that
         * takes a free text body decides nothing.
         */
        canConfirmStatus(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            const audit = this.auditOf(mapping);

            return audit.requiresApproval === true && audit.canReadStatus !== true;
        },

        isBusy(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            return this.busyMappingId === mapping.id;
        },

        /**
         * The audit state as the server names it, with the label the server keys it to. A state
         * the administration has no label for is shown as it is instead of being hidden.
         */
        auditStatusLabel(status: string | null | undefined): string {
            return approvalStatusLabel(
                status,
                (key) => this.$te(key),
                (key) => this.$t(key),
            );
        },

        /**
         * The reason a provider gave for a refusal, which is what an operator has to read.
         */
        statusMessageOf(mapping: EntitySchema.Entity<'sms_template_mapping'>): string | null {
            return customFieldValue(mapping.customFields, CUSTOM_FIELD_STATUS_MESSAGE);
        },

        /**
         * The raw code of the provider, kept as it was answered, next to the state it maps to.
         */
        statusCodeOf(mapping: EntitySchema.Entity<'sms_template_mapping'>): string | null {
            return customFieldValue(mapping.customFields, CUSTOM_FIELD_STATUS_CODE);
        },

        lastSyncedAtOf(mapping: EntitySchema.Entity<'sms_template_mapping'>): string | null {
            return customFieldValue(mapping.customFields, CUSTOM_FIELD_LAST_SYNCED_AT);
        },

        /**
         * The providers that report a template need one, the ones that take a free text body do not -
         * the field is only pointed out, the server checks it again before a message is built.
         */
        needsTemplateCode(mapping: EntitySchema.Entity<'sms_template_mapping'>): boolean {
            return this.providerFor(mapping)?.supportsTemplateMode ?? false;
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

        onOpenSubmission(mapping: EntitySchema.Entity<'sms_template_mapping'>): void {
            this.submissionTarget = mapping;
        },

        onCloseSubmission(): void {
            this.submissionTarget = null;
        },

        /**
         * The report was accepted: the modal closes and the template is read again, so the row shows
         * the code and the state the provider answered with.
         */
        onSubmissionFinished(): void {
            this.submissionTarget = null;
            this.emitReload();
        },

        onOpenConfirmation(mapping: EntitySchema.Entity<'sms_template_mapping'>): void {
            this.confirmationTarget = mapping;
            this.confirmationNote = '';
            this.confirmationStatus = CONFIRMABLE_STATUSES.includes(mapping.auditStatus) ? mapping.auditStatus : '';

            void this.loadApprovalStatuses();
        },

        onCloseConfirmation(): void {
            if (this.isConfirming) {
                return;
            }

            this.closeConfirmation();
        },

        /**
         * The modal reports every change of its open state, and only a dismissal closes the form - a
         * recorded state is what may not be interrupted, not the opening of the form.
         */
        onConfirmationModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.onCloseConfirmation();
            }
        },

        /**
         * Resets the confirmation form. Kept apart from the click that closes it, because a recorded
         * state closes the form while the call it just made is still running.
         */
        closeConfirmation(): void {
            this.confirmationTarget = null;
            this.confirmationStatus = '';
            this.confirmationNote = '';
        },

        /**
         * The labels of the states come from the server, so a state it adds shows up here as well -
         * read once, as they do not change while the page is open.
         */
        async loadApprovalStatuses(): Promise<void> {
            if (this.approvalStatuses.length > 0) {
                return;
            }

            try {
                const statuses = await this.smsGatewayProviderService.approvalStatuses();

                this.approvalStatuses = statuses.filter((status) => CONFIRMABLE_STATUSES.includes(status.value));
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            }
        },

        async onRefresh(mapping: EntitySchema.Entity<'sms_template_mapping'>): Promise<void> {
            this.busyMappingId = mapping.id;

            try {
                const answer = await this.smsTemplateAuditService.refresh(mapping.id);

                // the row is read again before the answer is judged: a failure may still have
                // changed nothing, and the row keeps whatever the provider said last
                this.emitReload();
                this.onAnswer(answer);
            } catch (error) {
                this.emitReload();
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.busyMappingId = null;
            }
        },

        async onConfirm(): Promise<void> {
            if (this.confirmationTarget === null) {
                return;
            }

            if (this.confirmationStatus === '' || this.confirmationNote.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.notification.missingFields'),
                });

                return;
            }

            this.isConfirming = true;

            try {
                const answer = await this.smsTemplateAuditService.confirm(
                    this.confirmationTarget.id,
                    this.confirmationStatus,
                    this.confirmationNote,
                );

                if (answer.errorCode === null && answer.errorMessage === null) {
                    // only a recorded state closes the form, so a refused one can be corrected
                    this.closeConfirmation();
                }

                this.emitReload();
                this.onAnswer(answer);
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isConfirming = false;
            }
        },

        /**
         * A refusal of the audit is answered, not thrown: the shop rendered the reason already, and
         * the code is only the fallback when it has no text for it.
         */
        onAnswer(answer: SmsTemplateAuditAnswer): void {
            const answerEntity = answer ?? NO_ANSWER;

            if (answerEntity.errorCode === null && answerEntity.errorMessage === null) {
                return;
            }

            this.createNotificationError({
                message: answerEntity.errorMessage ?? answerEntity.errorCode ?? '',
            });
        },

        /**
         * The rows are part of the template, so the template is what is read again - the page that
         * holds it also holds the ledger built from the same rows.
         */
        emitReload(): void {
            this.$emit('reload');
        },
    },
});
