/**
 * @sw-package framework
 */
import axios from 'axios';
import useNotification from 'src/app/composables/use-notification';
import type { TransferWorkflowConfiguration } from '../../service/payment-transfer.api.service';
import template from './sw-finance-transfer-workflow-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['paymentTransferApiService', 'acl'],
    emits: ['modal-close'],

    data(): {
        configuration: TransferWorkflowConfiguration | null;
        releaseId: string | null;
        isLoading: boolean;
        isSaving: boolean;
        loadFailed: boolean;
        saveReason: string | null;
    } {
        return {
            configuration: null,
            releaseId: null,
            isLoading: true,
            isSaving: false,
            loadFailed: false,
            saveReason: null,
        };
    },

    computed: {
        releaseOptions(): Array<{ value: string; label: string; disabled: boolean }> {
            return (this.configuration?.releases ?? []).map((release) => ({
                value: release.id,
                disabled: !release.available,
                label: `${release.name} · ${this.$t('sw-finance.transfer.workflow.revision', { revision: release.revision })}${release.available ? '' : ` · ${this.reasonLabel(release.reason)}`}`,
            }));
        },
        selectedReason(): string | null {
            const selected = this.configuration?.releases.find((release) => release.id === this.releaseId);
            return (
                this.saveReason ||
                selected?.reason ||
                (this.releaseId === this.configuration?.releaseId ? this.configuration?.reason : null) ||
                null
            );
        },
        selectedReasonMessage(): string {
            return this.reasonMessage(this.selectedReason);
        },
        canSave(): boolean {
            return (
                !this.isLoading &&
                !this.isSaving &&
                !this.loadFailed &&
                this.acl.can('system_config:update') &&
                this.releaseOptions.some((option) => option.value === this.releaseId && !option.disabled)
            );
        },
    },

    watch: {
        releaseId() {
            this.saveReason = null;
        },
    },

    created() {
        void this.loadConfiguration();
    },

    methods: {
        reasonLabel(reason: string | null): string {
            const key = `sw-finance.transfer.workflow.checkLabels.${reason}`;
            return this.$te(key) ? this.$t(key) : this.$t('sw-finance.transfer.workflow.status.unavailable');
        },
        reasonMessage(reason: string | null): string {
            const key = `sw-finance.transfer.workflow.checks.${reason}`;
            return this.$te(key) ? this.$t(key) : this.$t('sw-finance.transfer.workflow.checks.workflow_unavailable');
        },
        async loadConfiguration(): Promise<void> {
            this.isLoading = true;
            this.loadFailed = false;
            try {
                this.configuration = await this.paymentTransferApiService.getWorkflowConfiguration();
                this.releaseId = this.configuration.releaseId;
            } catch {
                this.loadFailed = true;
            } finally {
                this.isLoading = false;
            }
        },
        onClose(): void {
            if (!this.isSaving) {
                this.$emit('modal-close');
            }
        },
        async onSave(): Promise<void> {
            if (!this.canSave || !this.releaseId) {
                return;
            }
            this.isSaving = true;
            this.saveReason = null;
            try {
                await this.paymentTransferApiService.saveWorkflowConfiguration(this.releaseId);
                useNotification().createNotificationSuccess({ message: this.$t('sw-finance.transfer.workflow.saved') });
                this.$emit('modal-close');
            } catch (error: unknown) {
                if (axios.isAxiosError<{ errors?: Array<{ meta?: { parameters?: { reason?: string } } }> }>(error)) {
                    this.saveReason = error.response?.data.errors?.[0]?.meta?.parameters?.reason ?? 'workflow_unavailable';
                } else {
                    this.saveReason = 'workflow_unavailable';
                }
                useNotification().createNotificationError({ message: this.$t('sw-finance.transfer.workflow.saveFailed') });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
