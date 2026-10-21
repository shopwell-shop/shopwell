/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-finance-transfer-detail.html.twig';
import './sw-finance-transfer-detail.scss';
import { DECIDABLE_STATE, providerLabelKey, transferStateLabelKey } from '../../service/payment-transfer.api.service';
import {
    PAYMENT_TRANSFER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
    formatPayload,
} from '../../../sw-payment/service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;

type PaymentTransfer = EntitySchema.Entity<'payment_transfer'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        transferId: {
            type: String,
            required: true,
        },
    },

    data(): {
        transfer: PaymentTransfer | null;
        isLoading: boolean;
        transferStates: EntityCollection<'state_machine_state'> | null;
        showApproveModal: boolean;
        showRejectModal: boolean;
    } {
        return {
            transfer: null,
            isLoading: false,
            transferStates: null,
            showApproveModal: false,
            showRejectModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.transfer?.transferNo),
        };
    },

    computed: {
        transferRepository(): RepositoryType<'payment_transfer'> {
            return this.repositoryFactory.create('payment_transfer');
        },

        stateRepository(): RepositoryType<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        dateFilter(): (value: string | Date) => string {
            return Shopwell.Filter.getByName('date') as (value: string | Date) => string;
        },

        paymentAppLabel(): string {
            const paymentApp = this.transfer?.paymentApp;

            if (!paymentApp) {
                return '–';
            }

            return paymentApp.name || paymentApp.technicalName || paymentApp.id;
        },

        /**
         * The interface name is a snippet key declared by the server.
         */
        providerLabel(): string {
            const provider = this.transfer?.provider;

            if (!provider) {
                return '–';
            }

            const key = providerLabelKey(provider);

            return this.$te(key) ? this.$t(key) : provider;
        },

        /**
         * Wechat is the only channel that hands a single use `package_info` to the shopper. The
         * existence of that value is all the administration may know - see the template.
         */
        isAwaitingUserConfirm(): boolean {
            return this.transfer?.statusMachineState?.technicalName === 'awaiting_user_confirm';
        },

        hasConfirmPayload(): boolean {
            return Boolean(this.transfer?.confirmPayload);
        },

        hasFailure(): boolean {
            return Boolean(this.transfer?.failureCode || this.transfer?.failureReason);
        },

        /**
         * The note is what the creator wrote for the approver and never travels to the channel.
         */
        note(): string {
            const metadata = this.transfer?.metadata as { note?: string } | null;

            return metadata?.note ?? '';
        },

        /**
         * A rejection is final and the reason is the only record of it, so it gets its own line right
         * next to the failure card instead of being buried in the metadata json.
         */
        rejectReason(): string {
            const metadata = this.transfer?.metadata as { rejectReason?: string } | null;

            return metadata?.rejectReason ?? '';
        },

        metadataJson(): string {
            return formatPayload(this.transfer?.metadata);
        },

        /**
         * Only a `created` transfer may be released or rejected - everything else is on its way or done.
         */
        isDecidable(): boolean {
            return this.transfer?.statusMachineState?.technicalName === DECIDABLE_STATE;
        },

        /**
         * The privilege that moves money. Without it the detail page is a pure read view.
         */
        canApprove(): boolean {
            return this.acl.can('payment_transfer.approver');
        },

        canDecide(): boolean {
            return this.isDecidable && this.canApprove;
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            const [states] = await Promise.all([
                this.stateRepository.search(createPaymentStateCriteria(PAYMENT_TRANSFER_STATE_MACHINE)),
                this.loadTransfer(),
            ]);

            this.transferStates = states;
        },

        loadTransfer(): Promise<void> {
            const criteria: CriteriaType = new Criteria(1, 1);
            criteria.addAssociation('recipient');
            criteria.addAssociation('paymentApp');
            criteria.addAssociation('statusMachineState');

            this.isLoading = true;

            return this.transferRepository
                .get(this.transferId as EntitySchema.EntityKey<'payment_transfer'>, Shopwell.Context.api, criteria)
                .then((transfer) => {
                    this.transfer = transfer;
                })
                .catch(() => {
                    this.transfer = null;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onRefresh(): Promise<void> {
            return this.loadTransfer();
        },

        /**
         * The name of a transfer state.
         *
         * The snippet of this module wins over the name the state machine carries: that translation was
         * written for the payment gateway and calls a `created` payout `已创建`, while the finance pages
         * ask the operator to submit it. A state the administration does not translate falls back to the
         * name of the machine instead of showing a key.
         */
        stateLabelOf(technicalName: string | null | undefined, fallback: string): string {
            if (!technicalName) {
                return fallback;
            }

            const key = transferStateLabelKey(technicalName);

            return this.$te(key) ? this.$t(key) : fallback;
        },

        transferStateLabel(stateId: string): string {
            const state = [...(this.transferStates ?? [])].find((entry) => entry.id === stateId) as
                | EntitySchema.Entity<'state_machine_state'>
                | undefined;

            return this.stateLabelOf(
                state?.technicalName,
                findPaymentStateLabel(this.transferStates ?? [], stateId) as string,
            );
        },

        transferStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.transferStates ?? [], stateId) as string;
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        /**
         * A missing timestamp is an empty timeline entry, not the first of january 1970.
         */
        formatDateOrDash(value: string | null | undefined): string {
            return value ? this.dateFilter(value) : '–';
        },

        onApprove(): void {
            if (!this.canDecide) {
                return;
            }

            this.showApproveModal = true;
        },

        onReject(): void {
            if (!this.canDecide) {
                return;
            }

            this.showRejectModal = true;
        },

        onCloseApproveModal(): void {
            this.showApproveModal = false;
        },

        onCloseRejectModal(): void {
            this.showRejectModal = false;
        },

        /**
         * The row on screen is stale the moment a decision is booked - a state, the submit counter, a
         * failure reason - so it is read again instead of being patched locally.
         */
        onTransferDecided(): void {
            this.showApproveModal = false;
            this.showRejectModal = false;

            void this.loadTransfer();
        },
    },
});
