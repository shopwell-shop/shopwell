/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-customer-detail-wallet.html.twig';
import './sw-customer-detail-wallet.scss';
import type LoyaltyApiService from '../../../../sw-loyalty/service/loyalty.api.service';

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

interface WalletForm {
    currencyId: string | null;
    amount: number;
    comment: string;
}

type WalletActionMode = 'topUp' | 'adjust';

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

        walletCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addAssociation('currency');

            return criteria;
        },

        walletTransactionCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(
                Criteria.equalsAny(
                    'customerWalletId',
                    this.wallets.map((wallet) => wallet.id),
                ),
            );
            criteria.addAssociation('currency');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
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

        walletModalTitle(): string {
            return this.walletModalMode === 'topUp'
                ? this.$t('sw-customer.detailWallet.walletModalTitleTopUp')
                : this.$t('sw-customer.detailWallet.walletModalTitleAdjust');
        },

        allowWalletChange(): boolean {
            return this.acl.can('wallet.editor');
        },

        isWalletFormValid(): boolean {
            return this.walletForm.currencyId !== null && this.walletForm.amount !== 0;
        },

        isCurrencyLocked(): boolean {
            return this.selectedWalletId !== null;
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
                await this.loadWalletTransactions();
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
            if (this.wallets.length === 0) {
                this.walletTransactions = [];
                this.total = 0;

                return Promise.resolve();
            }

            return this.walletTransactionRepository.search(this.walletTransactionCriteria).then((transactions) => {
                this.walletTransactions = transactions as unknown as WalletTransaction[];
                this.total = transactions.total ?? this.walletTransactions.length;
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

        formatDate(date: string | null): string {
            return date ? this.dateFilter(date) : '-';
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

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
                currencyId: wallet ? wallet.currencyId : null,
                amount: 0,
                comment: '',
            };
            this.showWalletModal = true;
        },

        closeWalletModal() {
            this.showWalletModal = false;
        },

        async onSaveWallet(): Promise<void> {
            const { currencyId } = this.walletForm;

            if (!this.isWalletFormValid || currencyId === null) {
                return;
            }

            this.isSavingWallet = true;

            try {
                const loyaltyApiService = this.loyaltyApiService as LoyaltyApiService;

                if (this.walletModalMode === 'topUp') {
                    await loyaltyApiService.topUpWallet(this.customerId, currencyId, this.walletForm.amount);
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

                await this.loadWallets();
                await this.loadWalletTransactions();
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
