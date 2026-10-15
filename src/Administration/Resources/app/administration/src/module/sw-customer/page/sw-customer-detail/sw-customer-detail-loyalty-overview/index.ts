/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-customer-detail-loyalty-overview.html.twig';
import './sw-customer-detail-loyalty-overview.scss';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

interface OverviewCustomer {
    id: string;
}

interface OverviewMembershipTier {
    name: string | null;
    translated?: {
        name?: string | null;
    };
}

interface OverviewLoyalty {
    pointsBalance: number;
    growthValue: number;
    membershipTier: OverviewMembershipTier | null;
    membershipValidUntil: string | null;
}

interface OverviewCurrency {
    id: string;
    isoCode: string;
    name: string;
}

interface OverviewWallet {
    id: string;
    currencyId: string;
    currency: OverviewCurrency | null;
    balance: number;
    lockedBalance: number;
}

interface OverviewRoute {
    name: string;
    params: { id: string };
    query: { edit: boolean };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        customer: {
            type: Object as PropType<OverviewCustomer>,
            required: true,
        },

        customerEditMode: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isLoading: true,
            loyalty: null as OverviewLoyalty | null,
            wallet: null as OverviewWallet | null,
        };
    },

    computed: {
        customerId(): string {
            return this.customer.id;
        },

        loyaltyRepository() {
            return this.repositoryFactory.create('customer_loyalty');
        },

        walletRepository() {
            return this.repositoryFactory.create('customer_wallet');
        },

        loyaltyCriteria(): CriteriaType {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addAssociation('membershipTier');

            return criteria;
        },

        walletCriteria(): CriteriaType {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addAssociation('currency');

            return criteria;
        },

        canViewLoyalty(): boolean {
            return this.acl.can('loyalty.viewer');
        },

        canViewWallet(): boolean {
            return this.acl.can('wallet.viewer');
        },

        showOverview(): boolean {
            return this.canViewLoyalty || this.canViewWallet;
        },

        membershipTierName(): string {
            const tier = this.loyalty?.membershipTier;

            if (!tier) {
                return this.$t('sw-customer.detailLoyaltyOverview.noMembershipTier');
            }

            return tier.translated?.name ?? tier.name ?? this.$t('sw-customer.detailLoyaltyOverview.noMembershipTier');
        },

        membershipValidUntilLabel(): string {
            const validUntil = this.loyalty?.membershipValidUntil;

            return validUntil ? this.dateFilter(validUntil) : this.$t('sw-customer.detailLoyaltyOverview.unlimited');
        },

        pointsRoute(): OverviewRoute {
            return {
                name: 'sw.customer.detail.loyalty',
                params: { id: this.customerId },
                query: { edit: this.customerEditMode },
            };
        },

        walletRoute(): OverviewRoute {
            return {
                name: 'sw.customer.detail.wallet',
                params: { id: this.customerId },
                query: { edit: this.customerEditMode },
            };
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
                await Promise.all([
                    this.canViewLoyalty ? this.loadLoyalty() : Promise.resolve(),
                    this.canViewWallet ? this.loadWallet() : Promise.resolve(),
                ]);
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadLoyalty(): Promise<void> {
            return this.loyaltyRepository.search(this.loyaltyCriteria).then((loyalties) => {
                this.loyalty = (loyalties[0] as unknown as OverviewLoyalty) ?? null;
            });
        },

        loadWallet(): Promise<void> {
            return this.walletRepository.search(this.walletCriteria).then((wallets) => {
                this.wallet = (wallets[0] as unknown as OverviewWallet) ?? null;
            });
        },

        formatAmount(amount: number): string {
            if (!this.wallet?.currency) {
                return `${amount}`;
            }

            return this.currencyFilter(amount, this.wallet.currency.isoCode) as string;
        },
    },
});
