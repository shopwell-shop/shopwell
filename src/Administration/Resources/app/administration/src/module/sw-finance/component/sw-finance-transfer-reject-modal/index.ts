/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-finance-transfer-reject-modal.html.twig';
import './sw-finance-transfer-reject-modal.scss';

const { Mixin } = Shopwell;

const REASON_LIMIT = 255;

type TransferLike = {
    id: string;
    transferNo?: string | null;
    outNo?: string | null;
};

/**
 * The rejection of a transfer request.
 *
 * A rejection is final: the row moves to `canceled` and can never be submitted again, because the out
 * number of that request is spent. That is why the reason is mandatory - it is the only record of why
 * the money was not sent - and why the modal says so before the operator confirms.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['paymentTransferApiService'],

    emits: ['modal-close', 'transfer-decided'],

    props: {
        transfer: {
            type: Object as PropType<TransferLike>,
            required: true,
        },
    },

    data(): {
        reason: string;
        rejectInProgress: boolean;
    } {
        return {
            reason: '',
            rejectInProgress: false,
        };
    },

    computed: {
        reasonLength(): number {
            return this.reason.length;
        },

        reasonLimit(): number {
            return REASON_LIMIT;
        },

        isReasonTooLong(): boolean {
            return this.reasonLength > REASON_LIMIT;
        },

        /**
         * A rejection without a reason would leave a canceled payout nobody can explain later, so the
         * button stays disabled until there is one.
         */
        isConfirmDisabled(): boolean {
            return this.reason.trim() === '' || this.isReasonTooLong || this.rejectInProgress;
        },
    },

    methods: {
        onClose(): void {
            if (this.rejectInProgress) {
                return;
            }

            this.$emit('modal-close');
        },

        onConfirm(): void {
            if (this.isConfirmDisabled) {
                return;
            }

            this.rejectInProgress = true;

            this.paymentTransferApiService
                .cancelTransfer(this.transfer.id, this.reason.trim())
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-finance.transfer.reject.noticeRejected'),
                    });

                    this.$emit('transfer-decided');
                })
                .catch((error: { response?: { data?: { errors?: Array<{ detail?: string }> } } }) => {
                    const detail = error.response?.data?.errors?.[0]?.detail;

                    this.createNotificationError({
                        message: detail || (this.$t('sw-finance.transfer.reject.messageFailed')),
                    });
                })
                .finally(() => {
                    this.rejectInProgress = false;
                });
        },
    },
});
