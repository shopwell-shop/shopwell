import template from './sw-dashboard-wallet.html.twig';
import './sw-dashboard-wallet.scss';
import { getDayBounds } from '../../helper/order-list-filter';

const { Criteria } = Shopwell.Data;

/**
 * The ledger types `WalletService` writes. Anything else is translated as the
 * raw value, so a new backend type degrades to text instead of a snippet key.
 */
const KNOWN_TRANSACTION_TYPES = ['top_up', 'consume', 'refund', 'adjust'];

type Transaction = {
    id: string;
    type: string;
    amount: number;
    createdAt: string;
    wallet?: {
        customer?: {
            firstName?: string;
            lastName?: string;
            company?: string;
        } | null;
    } | null;
};

/**
 * @sw-package after-sales
 *
 * The money that is already in the building: what customers topped up today
 * and the last wallet movements. The finance module holds the ledger, this
 * card only answers "did money move today" without opening it.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        topUpToday: number;
        transactions: Transaction[];
        isLoading: boolean;
    } {
        return {
            topUpToday: 0,
            transactions: [],
            isLoading: true,
        };
    },

    computed: {
        walletTopUpRepository(): unknown {
            return this.repositoryFactory.create('customer_wallet_top_up');
        },

        transactionRepository(): unknown {
            return this.repositoryFactory.create('customer_wallet_transaction');
        },

        canViewWallet(): boolean {
            return this.acl.can('wallet.viewer');
        },

        currencyFilter(): (value: number, isoCode: string, decimals: number) => string {
            return Shopwell.Filter.getByName('currency');
        },

        systemCurrencyISOCode(): string {
            return Shopwell.Context.app.systemCurrencyISOCode as string;
        },

        hasTransactions(): boolean {
            return this.transactions.length > 0;
        },
    },

    created() {
        void this.loadWallet();
    },

    methods: {
        async loadWallet(): Promise<void> {
            if (!this.canViewWallet) {
                this.isLoading = false;

                return;
            }

            this.isLoading = true;

            try {
                const [topUpToday, transactions] = await Promise.all([
                    this.fetchTopUpToday(),
                    this.fetchLatestTransactions(),
                ]);

                this.topUpToday = topUpToday;
                this.transactions = transactions;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Today's credited top-ups only - open or failed payments are not money
         * the wallet actually received.
         */
        fetchTopUpToday(): Promise<number> {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('state', 'credited'));
            criteria.addFilter(Criteria.range('paidAt', { gte: getDayBounds(new Date()).from }));
            criteria.addAggregation(Criteria.sum('topUpToday', 'amount'));

            return (this.walletTopUpRepository as {
                search: (c: unknown) => Promise<{ aggregations: Record<string, { sum?: number }> }>;
            })
                .search(criteria)
                .then((result) => result.aggregations?.topUpToday?.sum ?? 0)
                .catch(() => 0);
        },

        /**
         * The newest five ledger rows, customer name folded in through the
         * wallet - the row links to the transaction list where the case lives.
         */
        fetchLatestTransactions(): Promise<Transaction[]> {
            const criteria = new Criteria(1, 5);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            criteria.addAssociation('wallet.customer');

            return (this.transactionRepository as { search: (c: unknown) => Promise<Transaction[]> })
                .search(criteria)
                .then((transactions) => [...transactions])
                .catch(() => [] as Transaction[]);
        },

        customerName(transaction: Transaction): string {
            const customer = transaction.wallet?.customer;

            if (!customer) {
                return this.$t('sw-dashboard.wallet.unknownCustomer');
            }

            if (customer.company) {
                return customer.company;
            }

            return `${customer.firstName ?? ''} ${customer.lastName ?? ''}`.trim();
        },

        typeLabel(transaction: Transaction): string {
            if (KNOWN_TRANSACTION_TYPES.includes(transaction.type)) {
                return this.$t(`sw-dashboard.wallet.types.${transaction.type}`);
            }

            return transaction.type;
        },
    },
});
