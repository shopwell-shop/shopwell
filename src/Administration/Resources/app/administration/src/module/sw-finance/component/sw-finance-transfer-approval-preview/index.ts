/** @sw-package framework */
import type { PropType } from 'vue';
import type {
    PaymentTransferPayload,
    TransferApprovalPreview,
    TransferApprovalPreviewStep,
} from '../../service/payment-transfer.api.service';
import template from './sw-finance-transfer-approval-preview.html.twig';
import './sw-finance-transfer-approval-preview.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['paymentTransferApiService'],
    emits: ['update:blocked'],
    props: {
        payload: { type: Object as PropType<PaymentTransferPayload | null>, default: null },
    },
    data(): {
        preview: TransferApprovalPreview | null;
        isLoading: boolean;
        loadFailed: boolean;
        requestRevision: number;
        requestTimer: ReturnType<typeof setTimeout> | null;
    } {
        return { preview: null, isLoading: false, loadFailed: false, requestRevision: 0, requestTimer: null };
    },
    computed: {
        unavailableMessage(): string {
            const key = `sw-finance.transfer.workflow.checks.${this.preview?.reason}`;
            return this.$te(key) ? this.$t(key) : this.$t('sw-finance.transfer.workflow.checks.workflow_unavailable');
        },
    },
    watch: {
        payload: {
            deep: true,
            immediate: true,
            handler() {
                this.schedulePreview();
            },
        },
    },
    beforeUnmount() {
        this.requestRevision += 1;
        if (this.requestTimer !== null) clearTimeout(this.requestTimer);
    },
    methods: {
        schedulePreview(): void {
            const revision = ++this.requestRevision;
            if (this.requestTimer !== null) clearTimeout(this.requestTimer);
            this.preview = null;
            this.$emit('update:blocked', false);
            this.loadFailed = false;
            this.isLoading = this.payload !== null;
            if (this.payload === null) return;
            const payload = this.payload;
            this.requestTimer = setTimeout(() => {
                void this.loadPreview(payload, revision);
            }, 300);
        },
        async loadPreview(payload: PaymentTransferPayload, revision: number): Promise<void> {
            try {
                const preview = await this.paymentTransferApiService.previewApproval(payload);
                if (revision === this.requestRevision) {
                    this.preview = preview;
                    this.$emit('update:blocked', !preview.available && preview.reason !== 'preview_unavailable');
                }
            } catch {
                if (revision === this.requestRevision) this.loadFailed = true;
            } finally {
                if (revision === this.requestRevision) this.isLoading = false;
            }
        },
        participantNames(step: TransferApprovalPreviewStep): string {
            return step.assignees.map((assignee) => assignee.name).join(', ');
        },
    },
});
