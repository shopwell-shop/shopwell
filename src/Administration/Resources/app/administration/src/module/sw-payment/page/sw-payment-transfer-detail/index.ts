/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-payment-transfer-detail.html.twig';
import './sw-payment-transfer-detail.scss';
import {
    PAYMENT_TRANSFER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
    formatPayload,
} from '../../service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;

type PaymentTransfer = EntitySchema.Entity<'payment_transfer'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory'],

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
    } {
        return {
            transfer: null,
            isLoading: false,
            transferStates: null,
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

        metadataJson(): string {
            return formatPayload(this.transfer?.metadata);
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

        transferStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.transferStates ?? [], stateId) as string;
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
    },
});
