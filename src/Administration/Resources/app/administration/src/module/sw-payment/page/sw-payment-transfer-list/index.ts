/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-payment-transfer-list.html.twig';
import './sw-payment-transfer-list.scss';
import {
    PAYMENT_TRANSFER_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
} from '../../service/payment-gateway.api.service';

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
         * The recipient is part of the listing, the transfer itself only stores the id.
         */
        criteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy ?? 'createdAt', this.sortDirection, this.naturalSorting));
            criteria.addAssociation('recipient');

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'transfer-no-filter': {
                    property: 'transferNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.transfer.filters.transferNoFilter.label'),
                    placeholder: this.$t('sw-payment.transfer.filters.transferNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'out-no-filter': {
                    property: 'outNo',
                    type: 'string-filter',
                    label: this.$t('sw-payment.transfer.filters.outNoFilter.label'),
                    placeholder: this.$t('sw-payment.transfer.filters.outNoFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'channel-filter': {
                    property: 'channel',
                    type: 'string-filter',
                    label: this.$t('sw-payment.transfer.filters.channelFilter.label'),
                    placeholder: this.$t('sw-payment.transfer.filters.channelFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'provider-filter': {
                    property: 'provider',
                    type: 'string-filter',
                    label: this.$t('sw-payment.transfer.filters.providerFilter.label'),
                    placeholder: this.$t('sw-payment.transfer.filters.providerFilter.placeholder'),
                    criteriaFilterType: 'equalsAny',
                },
                'state-filter': {
                    property: 'statusId',
                    type: 'multi-select-filter',
                    label: this.$t('sw-payment.transfer.filters.stateFilter.label'),
                    placeholder: this.$t('sw-payment.transfer.filters.stateFilter.placeholder'),
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
         */
        stateFilterOptions(): Array<{ id: string; name: string }> {
            return [...(this.states ?? [])].map((state: StateMachineState) => {
                return {
                    id: state.id,
                    name: state.translated?.name || state.name || state.technicalName,
                };
            });
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        transferColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'transferNo',
                    label: 'sw-payment.transfer.list.columnTransferNo',
                    routerLink: 'sw.payment.transferDetail',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'outNo',
                    label: 'sw-payment.transfer.list.columnOutNo',
                    allowResize: true,
                },
                {
                    property: 'recipientId',
                    label: 'sw-payment.transfer.list.columnRecipient',
                    allowResize: true,
                },
                {
                    property: 'provider',
                    label: 'sw-payment.transfer.list.columnProvider',
                    allowResize: true,
                },
                {
                    property: 'channel',
                    label: 'sw-payment.transfer.list.columnChannel',
                    allowResize: true,
                },
                {
                    property: 'amount',
                    label: 'sw-payment.transfer.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'statusId',
                    label: 'sw-payment.transfer.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-payment.transfer.list.columnCreatedAt',
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

        transferStateLabel(stateId: string): string {
            return findPaymentStateLabel(this.states ?? [], stateId) as string;
        },

        transferStateVariant(stateId: string): string {
            return findPaymentStateVariant(this.states ?? [], stateId) as string;
        },

        formatAmount(amount: number, currency: string): string {
            return formatPaymentAmount(amount, currency);
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

        formatDateOrDash(value: string | null): string {
            return value ? this.dateFilter(value) : '–';
        },
    },
});
