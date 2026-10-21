/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-customer-detail-wallet.html.twig';
import './sw-customer-detail-wallet.scss';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

interface WalletCustomer {
    id: string;
}

/**
 * A currency as it is attached to a wallet or a wallet transaction.
 */
interface WalletCurrency {
    id: string;
    isoCode: string;
    name: string;
}

/**
 * One wallet of the customer.
 */
interface CustomerWallet {
    id: string;
    currencyId: string;
    currency: WalletCurrency | null;
    balance: number;
    lockedBalance: number;
}

/**
 * One booked wallet transaction of the customer.
 */
interface WalletTransaction {
    id: string;
    type: string;
    amount: number;
    balanceAfter: number;
    currency: WalletCurrency | null;
    orderId: string | null;
    comment: string | null;
    createdAt: string | null;
}

/**
 * A recharge the customer started but has not paid yet. It is listed so the operator sees why a
 * promised balance is not there - the money only arrives with the paid notification of the channel.
 */
interface PendingTopUp {
    id: string;
    amount: number;
    currency: string;
    state: string;
    channelTechnicalName: string | null;
    createdAt: string | null;
}

interface WalletForm {
    currencyId: string | null;
    amount: number;
    comment: string;
}

type WalletActionMode = 'topUp' | 'adjust';

/**
 * The wallet tab of a customer: what the customer owns per currency, what is still on its way, and
 * every booking that led there. Balances are never computed here - the server owns them.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'loyaltyApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        customer: {
            type: Object as PropType<WalletCustomer>,
            required: true,
        },
    },

    data() {
        return {
            isLoading: true,
            isSavingWallet: false,
            page: 1,
            limit: 25,
            total: 0,
            wallets: [] as CustomerWallet[],
            walletTransactions: [] as WalletTransaction[],
            pendingTopUps: [] as PendingTopUp[],
            typeFilter: null as string | null,
            showWalletModal: false,
            walletModalMode: 'topUp' as WalletActionMode,
            selectedWalletId: null as string | null,
            walletForm: { currencyId: null, amount: 0, comment: '' } as WalletForm,
        };
    },

    computed: {
        customerId(): string {
            return this.customer.id;
        },

        walletRepository() {
            return this.repositoryFactory.create('customer_wallet');
        },

        walletTransactionRepository() {
            return this.repositoryFactory.create('customer_wallet_transaction');
        },

        topUpRepository() {
            return this.repositoryFactory.create('customer_wallet_top_up');
        },

        walletIds(): string[] {
            return this.wallets.map((wallet) => wallet.id);
        },

        walletCriteria(): CriteriaType {
            const criteria = new Criteria(1, 50);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addAssociation('currency');
            criteria.addSorting(Criteria.sort('currency.isoCode', 'ASC'));

            return criteria;
        },

        walletTransactionCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equalsAny('customerWalletId', this.walletIds));
            criteria.addAssociation('currency');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.typeFilter) {
                criteria.addFilter(Criteria.equals('type', this.typeFilter));
            }

            return criteria;
        },

        topUpCriteria(): CriteriaType {
            const criteria = new Criteria(1, 10);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addFilter(Criteria.equals('state', 'open'));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        typeFilterOptions() {
            return [
                { value: null, label: this.$t('sw-customer.detailWallet.filterTypeAll') },
                ...[
                    'top_up',
                    'consume',
                    'refund',
                    'adjust',
                ].map((type) => {
                    return {
                        value: type,
                        label: this.getWalletTransactionTypeLabel(type),
                    };
                }),
            ];
        },

        walletTransactionColumns() {
            return [
                {
                    property: 'createdAt',
                    label: 'sw-customer.detailWallet.columnDate',
                },
                {
                    property: 'type',
                    label: 'sw-customer.detailWallet.columnType',
                },
                {
                    property: 'amount',
                    label: 'sw-customer.detailWallet.columnAmount',
                },
                {
                    property: 'balanceAfter',
                    label: 'sw-customer.detailWallet.columnBalanceAfter',
                },
                {
                    property: 'orderId',
                    label: 'sw-customer.detailWallet.columnOrder',
                },
                {
                    property: 'comment',
                    label: 'sw-customer.detailWallet.columnComment',
                    allowResize: true,
                },
            ];
        },

        pendingTopUpColumns() {
            return [
                {
                    property: 'createdAt',
                    label: 'sw-customer.detailWallet.columnDate',
                },
                {
                    property: 'amount',
                    label: 'sw-customer.detailWallet.columnAmount',
                },
                {
                    property: 'channelTechnicalName',
                    label: 'sw-customer.detailWallet.columnChannel',
                },
                {
                    property: 'state',
                    label: 'sw-customer.detailWallet.columnState',
                },
            ];
        },

        walletModalTitle(): string {
            return this.walletModalMode === 'topUp'
                ? this.$t('sw-customer.detailWallet.walletModalTitleTopUp')
                : this.$t('sw-customer.detailWallet.walletModalTitleAdjust');
        },

        walletModalDescription(): string {
            return this.walletModalMode === 'topUp'
                ? this.$t('sw-customer.detailWallet.walletTopUpHelpText')
                : this.$t('sw-customer.detailWallet.walletAdjustHelpText');
        },

        allowWalletChange(): boolean {
            return this.acl.can('wallet.editor');
        },

        isWalletFormValid(): boolean {
            if (this.walletForm.currencyId === null) {
                return false;
            }

            // a top up always adds money, an adjustment is also allowed to take it away
            return this.walletModalMode === 'topUp' ? this.walletForm.amount > 0 : this.walletForm.amount !== 0;
        },

        isCurrencyLocked(): boolean {
            return this.selectedWalletId !== null;
        },

        selectedWallet(): CustomerWallet | null {
            return this.wallets.find((wallet) => wallet.id === this.selectedWalletId) ?? null;
        },

        /**
         * The amounts an operator types most often, so a top up is two clicks instead of keyboard work.
         */
        quickAmounts(): number[] {
            return [
                100,
                200,
                500,
                1000,
            ];
        },

        currencyFilter() {
            return Shopwell.Filter.getByName('currency');
        },

        dateFilter() {
            return Shopwell.Filter.getByName('date');
        },
    },

    watch: {
        customer() {
            void this.createdComponent();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            await this.loadData();
        },

        async loadData(): Promise<void> {
            this.isLoading = true;

            try {
                await this.loadWallets();
                await Promise.all([this.loadWalletTransactions(), this.loadPendingTopUps()]);
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadWallets(): Promise<void> {
            return this.walletRepository.search(this.walletCriteria).then((wallets) => {
                this.wallets = wallets as unknown as CustomerWallet[];
            });
        },

        loadWalletTransactions(): Promise<void> {
            if (this.walletIds.length === 0) {
                this.walletTransactions = [];
                this.total = 0;

                return Promise.resolve();
            }

            return this.walletTransactionRepository.search(this.walletTransactionCriteria).then((transactions) => {
                this.walletTransactions = transactions as unknown as WalletTransaction[];
                this.total = transactions.total ?? this.walletTransactions.length;
            });
        },

        loadPendingTopUps(): Promise<void> {
            return this.topUpRepository.search(this.topUpCriteria).then((topUps) => {
                this.pendingTopUps = topUps as unknown as PendingTopUp[];
            });
        },

        /**
         * Turns the machine type of a booking (`top_up`, `redeem`, ...) into its snippet key.
         */
        resolveTypeSnippetKey(type: string): string {
            return type.replace(/_([a-z])/g, (_match, letter: string) => letter.toUpperCase());
        },

        getWalletTransactionTypeLabel(type: string): string {
            return this.$t(`sw-customer.detailWallet.walletTransactionType.${this.resolveTypeSnippetKey(type)}`, {}, 0);
        },

        formatAmount(amount: number, currency: WalletCurrency | null): string {
            if (!currency) {
                return `${amount}`;
            }

            return this.currencyFilter(amount, currency.isoCode) as string;
        },

        formatTopUpAmount(topUp: PendingTopUp): string {
            if (!topUp.currency) {
                return `${topUp.amount}`;
            }

            return this.currencyFilter(topUp.amount, topUp.currency) as string;
        },

        formatDate(date: string | null): string {
            return date ? this.dateFilter(date) : '-';
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

            void this.loadWalletTransactions();
        },

        onFilterChange(): void {
            this.page = 1;

            void this.loadWalletTransactions();
        },

        openTopUpModal(wallet: CustomerWallet | null = null) {
            this.openWalletModal('topUp', wallet);
        },

        openAdjustWalletModal(wallet: CustomerWallet) {
            this.openWalletModal('adjust', wallet);
        },

        openWalletModal(mode: WalletActionMode, wallet: CustomerWallet | null) {
            this.walletModalMode = mode;
            this.selectedWalletId = wallet ? wallet.id : null;
            this.walletForm = {
                currencyId: wallet ? wallet.currencyId : (this.wallets[0]?.currencyId ?? null),
                amount: 0,
                comment: '',
            };
            this.showWalletModal = true;
        },

        closeWalletModal() {
            this.showWalletModal = false;
        },

        setAmount(amount: number) {
            this.walletForm.amount = amount;
        },

        async onSaveWallet(): Promise<void> {
            const { currencyId } = this.walletForm;

            if (!this.isWalletFormValid || currencyId === null) {
                return;
            }

            this.isSavingWallet = true;

            try {
                const loyaltyApiService = this.loyaltyApiService;

                if (this.walletModalMode === 'topUp') {
                    await loyaltyApiService.topUpWallet(
                        this.customerId,
                        currencyId,
                        this.walletForm.amount,
                        this.walletForm.comment || undefined,
                    );
                } else {
                    await loyaltyApiService.adjustWallet(
                        this.customerId,
                        currencyId,
                        this.walletForm.amount,
                        this.walletForm.comment,
                    );
                }

                this.showWalletModal = false;
                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detailWallet.messageWalletSaved'),
                });

                await this.loadData();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-customer.detailWallet.notificationErrorMessage'),
                });
            } finally {
                this.isSavingWallet = false;
            }
        },
    },
});
