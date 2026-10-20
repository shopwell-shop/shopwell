/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-finance-transfer-list.html.twig';
import './sw-finance-transfer-list.scss';
import { DECIDABLE_STATE, providerLabelKey, transferStateLabelKey } from '../../service/payment-transfer.api.service';
import {
    PAYMENT_TRANSFER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../../sw-payment/service/payment-gateway.api.service';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

const DEFAULT_FILTERS: string[] = [
    'transfer-no-filter',
    'out-no-filter',
    'channel-filter',
    'provider-filter',
    'state-filter',
];

type PaymentTransfer = EntitySchema.Entity<'payment_transfer'>;
type StateMachineState = EntitySchema.Entity<'state_machine_state'>;

type FilterDefinition = {
    property: string;
    type: string;
    label: string;
    placeholder: string;
    criteriaFilterType?: string;
    options?: Array<{ id: string; name: string }>;
    valueProperty?: string;
    labelProperty?: string;
};

interface TransferFilterFactory {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
}

/**
 * The filter factory is injected but not part of the typed service container yet, so it is looked up
 * through the component instance instead of widening the global type for one page.
 */
function transferFilterFactory(vm: unknown): TransferFilterFactory {
    return (vm as { filterFactory: TransferFilterFactory }).filterFactory;
}

/**
 * The list of transfer requests - the workbench of the operator who decides about payouts.
 *
 * Two facts drive the whole page:
 *
 *  1. **Booking and releasing are two steps.** The "New transfer request" button only books an intent;
 *     money moves when an approver confirms a row, and only a row that is still `created` can be
 *     confirmed. That is why the actions are per row and not in the smart bar.
 *  2. **Approving and rejecting are different privileges from viewing.** A reader never sees an action
 *     that would move money - hidden in the UI *and* refused by the acl of the endpoint.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('listing')],

    data(): {
        transfers: EntityCollection<'payment_transfer'> | null;
        isLoading: boolean;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        states: EntityCollection<'state_machine_state'> | null;
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
        showCreateModal: boolean;
        transferToApprove: PaymentTransfer | null;
        transferToReject: PaymentTransfer | null;
    } {
        return {
            transfers: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            states: null,
            defaultFilters: DEFAULT_FILTERS,
            storeKey: 'grid.filter.payment_transfer',
            activeFilterNumber: 0,
            showCreateModal: false,
            transferToApprove: null,
            transferToReject: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
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

        /**
         * Booking a request is a separate privilege: a reader may see the list and still not create one.
         */
        canCreate(): boolean {
            return this.acl.can('payment_transfer.creator');
        },

        /**
         * The privilege that moves money. Nothing on this page submits or rejects a transfer without it.
         */
        canApprove(): boolean {
            return this.acl.can('payment_transfer.approver');
        },

        /**
         * The recipient is part of the listing, the transfer itself only stores the id. The app is
         * associated as well: a payout is released for a specific merchant account.
         */
        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'createdAt', this.sortDirection, this.naturalSorting));
            criteria.addAssociation('recipient');
            criteria.addAssociation('paymentApp');
            criteria.addAssociation('statusMachineState');

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'transfer-no-filter': {
                    property: 'transferNo',
                    type: 'string-filter',
                    label: this.$t('sw-finance.transfer.filters.transferNoFilter.label'),
                    placeholder: this.$t('sw-finance.transfer.filters.transferNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'out-no-filter': {
                    property: 'outNo',
                    type: 'string-filter',
                    label: this.$t('sw-finance.transfer.filters.outNoFilter.label'),
                    placeholder: this.$t('sw-finance.transfer.filters.outNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'channel-filter': {
                    property: 'channel',
                    type: 'string-filter',
                    label: this.$t('sw-finance.transfer.filters.channelFilter.label'),
                    placeholder: this.$t('sw-finance.transfer.filters.channelFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'provider-filter': {
                    property: 'provider',
                    type: 'string-filter',
                    label: this.$t('sw-finance.transfer.filters.providerFilter.label'),
                    placeholder: this.$t('sw-finance.transfer.filters.providerFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'state-filter': {
                    property: 'statusId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-finance.transfer.filters.stateFilter.label'),
                    placeholder: this.$t('sw-finance.transfer.filters.stateFilter.placeholder'),
                    options: this.stateFilterOptions,
                    valueProperty: 'id',
                    labelProperty: 'name',
                },
            };
        },

        listFilters(): FilterDefinition[] {
            return transferFilterFactory(this).create('payment_transfer', this.listFilterOptions);
        },

        /**
         * The status of a transfer is a plain id, the states of the machine are read once so the
         * column and the filter can render their names.
         *
         * The name comes from the snippet of this module first: the state machine carries translations
         * of the payment gateway (`打款成功`), which are not the words the finance pages use for the same
         * state.
         */
        stateFilterOptions(): Array<{ id: string; name: string }> {
            return [...(this.states ?? [])].map((state: StateMachineState) => {
                return {
                    id: state.id,
                    name: this.stateLabelOf(
                        state.technicalName,
                        state.translated?.name || state.name || state.technicalName,
                    ),
                };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        /**
         * The data columns of the prd in its order. The last column - the actions - is a slot of the
         * listing and deliberately not a column: it carries no data of the transfer.
         */
        transferColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'transferNo',
                    label: 'sw-finance.transfer.list.columnTransferNo',
                    routerLink: 'sw.finance.transferDetail',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'outNo',
                    label: 'sw-finance.transfer.list.columnOutNo',
                    allowResize: true,
                },
                {
                    property: 'recipientId',
                    label: 'sw-finance.transfer.list.columnRecipient',
                    allowResize: true,
                },
                {
                    property: 'provider',
                    label: 'sw-finance.transfer.list.columnProvider',
                    allowResize: true,
                },
                {
                    property: 'channel',
                    label: 'sw-finance.transfer.list.columnChannel',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-finance.transfer.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'statusId',
                    label: 'sw-finance.transfer.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'failureCode',
                    label: 'sw-finance.transfer.list.columnFailure',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-finance.transfer.list.columnCreatedAt',
                    allowResize: true,
                },
            ];
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.states = await this.stateRepository.search(createPaymentStateCriteria(PAYMENT_TRANSFER_STATE_MACHINE));

            return this.getList();
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as unknown as {
                mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
            };

            // the service is async - without the await the criteria is a pending promise and reading
            // its filters throws, which leaves the grid permanently empty
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.transferRepository
                .search(criteria)
                .then((searchResult) => {
                    this.total = searchResult.total ?? 0;
                    this.transfers = searchResult;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateCriteria(filters: unknown[]): void {
            this.page = 1;
            this.criteria.filters = filters as CriteriaType['filters'];

            void this.getList();
        },

        onRefresh(): void {
            void this.getList();
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
            const state = [...(this.states ?? [])].find((entry) => entry.id === stateId) as
                | StateMachineState
                | undefined;

            return this.stateLabelOf(state?.technicalName, findPaymentStateLabel(this.states ?? [], stateId) as string);
        },

        transferStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.states ?? [], stateId) as string;
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
        },

        /**
         * The interface name is a snippet key declared by the server, so an interface this
         * administration does not translate falls back to its technical name instead of showing a key.
         */
        providerLabel(provider: string | null): string {
            if (!provider) {
                return '-';
            }

            const key = providerLabelKey(provider);
            const translated = this.$te(key) ? (this.$t(key)) : null;

            return translated || provider;
        },

        /**
         * The holder name is the only human readable identity of a recipient. The account id is the
         * fallback for every channel that does not know a name.
         */
        recipientLabel(item: PaymentTransfer): string {
            if (!item.recipient) {
                return '-';
            }

            return item.recipient.holderName || item.recipient.providerAccountId || '-';
        },

        /**
         * The failure column carries three different stories and has to tell them apart: a rejection is
         * an operator decision, a failure is a channel answer, and a return is money that came back.
         */
        failureLabel(item: PaymentTransfer): string {
            const rejectReason = (item.metadata as { rejectReason?: string } | null)?.rejectReason;

            if (rejectReason) {
                return `${this.$t('sw-finance.transfer.actions.cancel')}: ${rejectReason}`;
            }

            if (item.failureReason) {
                return item.failureReason;
            }

            return item.failureCode || '';
        },

        /**
         * Only a transfer that is still `created` may be released or rejected - everything else is
         * already on its way or finished, and the server refuses it with a 409 anyway.
         */
        isDecidable(item: PaymentTransfer): boolean {
            return item.statusMachineState?.technicalName === DECIDABLE_STATE;
        },

        /**
         * The visibility matrix of the action column: state AND privilege.
         */
        canDecide(item: PaymentTransfer): boolean {
            return this.isDecidable(item) && this.canApprove;
        },

        formatDateOrDash(value: string | null): string {
            return value ? this.dateFilter(value) : '–';
        },

        onOpenCreateModal(): void {
            this.showCreateModal = true;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
        },

        /**
         * The new row is not patched into the local collection: the server decides its number, its
         * state and its amount in the smallest unit, so the list is asked again.
         */
        onTransferCreated(): void {
            this.showCreateModal = false;
            this.page = 1;

            void this.getList();
        },

        onApprove(item: PaymentTransfer): void {
            if (!this.canDecide(item)) {
                return;
            }

            this.transferToApprove = item;
        },

        onReject(item: PaymentTransfer): void {
            if (!this.canDecide(item)) {
                return;
            }

            this.transferToReject = item;
        },

        onCloseApproveModal(): void {
            this.transferToApprove = null;
        },

        onCloseRejectModal(): void {
            this.transferToReject = null;
        },

        /**
         * Both decisions change the row - a state, the submit counter, the failure reason - and both are
         * reported by the modal through its own notice. The list only refreshes so the row shows what
         * the server actually booked.
         */
        onTransferDecided(): void {
            this.transferToApprove = null;
            this.transferToReject = null;

            void this.getList();
        },
    },
});
