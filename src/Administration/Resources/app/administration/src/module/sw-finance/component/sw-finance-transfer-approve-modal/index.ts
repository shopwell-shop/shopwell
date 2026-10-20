/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-finance-transfer-approve-modal.html.twig';
import './sw-finance-transfer-approve-modal.scss';
import { OUTCOME, providerLabelKey, transferStateLabelKey } from '../../service/payment-transfer.api.service';
import type { PaymentTransferSubmitResult } from '../../service/payment-transfer.api.service';
import { formatPaymentAmount } from '../../../sw-payment/service/payment-gateway.api.service';

const { Mixin } = Shopwell;

type TransferLike = {
    id: string;
    transferNo?: string | null;
    outNo?: string | null;
    provider?: string | null;
    channel?: string | null;
    amount?: number | null;
    currency?: string | null;
    failureCode?: string | null;
    failureReason?: string | null;
    recipient?: {
        holderName?: string | null;
        providerAccountId?: string | null;
        identityType?: string | null;
    } | null;
    paymentApp?: {
        id?: string | null;
        name?: string | null;
        technicalName?: string | null;
    } | null;
};

/**
 * The second confirmation of a payout - the last gate between a click and money leaving the platform.
 *
 * Three details of this modal are deliberate and must not be "simplified":
 *
 *  1. **The confirmation is an explicit checkbox.** A dialog that can be dismissed with the enter key is
 *     not a confirmation of anything; the operator has to tick that the money will actually move.
 *  2. **The dialog can not be closed while the request runs.** An impatient second click is exactly how
 *     a payout is submitted twice, and the platform can not tell a duplicate from a rebooking.
 *  3. **"Unknown" is not a failure.** When the channel does not answer conclusively the row stays
 *     `created` and the operator is told to check the channel backend first. Never retried here.
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
        confirmed: boolean;
        submitInProgress: boolean;
    } {
        return {
            confirmed: false,
            submitInProgress: false,
        };
    },

    computed: {
        providerName(): string {
            return this.transfer.provider ?? '';
        },

        providerLabel(): string {
            const key = providerLabelKey(this.providerName);

            if (!this.providerName) {
                return '-';
            }

            return this.$te(key) ? (this.$t(key)) : this.providerName;
        },

        channelLabel(): string {
            return [this.providerLabel, this.transfer.channel].filter(Boolean).join(' · ');
        },

        amountLabel(): string {
            return formatPaymentAmount(this.transfer.amount ?? null, this.transfer.currency ?? null);
        },

        recipientLabel(): string {
            const recipient = this.transfer.recipient;

            if (!recipient) {
                return '-';
            }

            return recipient.holderName || recipient.providerAccountId || '-';
        },

        appLabel(): string {
            const app = this.transfer.paymentApp;

            if (!app) {
                return '-';
            }

            return app.name || app.technicalName || app.id || '-';
        },

        warningText(): string {
            return this.$t('sw-finance.transfer.approve.warning', {
                provider: this.providerLabel,
                amount: this.amountLabel,
            });
        },

        /**
         * The gate of the gate: a disabled button while the box is unticked or a request is in flight.
         */
        isConfirmDisabled(): boolean {
            return !this.confirmed || this.submitInProgress;
        },
    },

    methods: {
        /**
         * A modal that can be closed while the submission runs turns one payout into two: the operator
         * would redo it, and the platform only records the attempts.
         */
        onClose(): void {
            if (this.submitInProgress) {
                return;
            }

            this.$emit('modal-close');
        },

        onConfirm(): void {
            if (this.isConfirmDisabled) {
                return;
            }

            this.submitInProgress = true;

            this.paymentTransferApiService
                .submitTransfer(this.transfer.id)
                .then((result: PaymentTransferSubmitResult) => {
                    this.handleOutcome(result);
                })
                .catch((error: { response?: { data?: { errors?: Array<{ detail?: string }> } } }) => {
                    const detail = error.response?.data?.errors?.[0]?.detail;

                    this.createNotificationError({
                        message: detail || (this.$t('sw-finance.transfer.approve.messageSubmitFailed')),
                    });
                })
                .finally(() => {
                    this.submitInProgress = false;
                });
        },

        /**
         * The three outcomes are three different messages, and the difference is a safety boundary: the
         * operator has to know whether the money moved, whether it definitely did not, or whether nobody
         * knows.
         */
        handleOutcome(result: PaymentTransferSubmitResult): void {
            if (result.outcome === OUTCOME.UNKNOWN) {
                this.createNotificationWarning({
                    message: this.$t('sw-finance.transfer.approve.noticeUnknown'),
                });

                // the row is still `created` and the submit counter went up - the operator decides what
                // happens next, the platform never retries on its own
                this.$emit('transfer-decided');

                return;
            }

            if (result.outcome === OUTCOME.FAILED) {
                const transfer = result.transfer;
                const code = transfer?.failureCode ?? '';
                const reason = transfer?.failureReason ?? '';

                this.createNotificationError({
                    message:
                        code || reason
                            ? (this.$t('sw-finance.transfer.approve.noticeFailed', {
                                  provider: this.providerLabel,
                                  code,
                                  reason,
                              }))
                            : (this.$t('sw-finance.transfer.approve.noticeFailedWithoutReason', {
                                  provider: this.providerLabel,
                              })),
                });

                this.$emit('transfer-decided');

                return;
            }

            if (result.outcome === OUTCOME.ACCEPTED) {
                // the endpoint answers the shape of `PaymentTransferPresenter::transfer()`, and there the
                // state of the row is `state` - `statusMachineState` is the association of the entity and
                // is not part of the answer
                const technicalName = result.transfer?.state?.technicalName;

                this.createNotificationSuccess({
                    // an answer without a state still moved the money: the operator is told that it was
                    // handed over instead of being shown a row number in the middle of a sentence
                    message: technicalName
                        ? this.$t('sw-finance.transfer.approve.noticeAccepted', {
                              state: this.$t(transferStateLabelKey(technicalName)),
                          })
                        : this.$t('sw-finance.transfer.approve.noticeAcceptedWithoutState'),
                });

                this.$emit('transfer-decided');

                return;
            }

            // the endpoint answers only the three outcomes above; anything else means the response was
            // not the one this modal understands, which is as uncertain as an unanswered call
            this.createNotificationWarning({
                message: this.$t('sw-finance.transfer.approve.noticeUnknown'),
            });

            this.$emit('transfer-decided');
        },
    },
});
