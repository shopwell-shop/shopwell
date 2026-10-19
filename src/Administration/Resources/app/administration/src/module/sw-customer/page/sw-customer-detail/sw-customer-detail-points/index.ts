/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';

import template from './sw-customer-detail-points.html.twig';
import './sw-customer-detail-points.scss';
import type { LoyaltySummary, LoyaltySummaryTransaction } from '../../../../sw-loyalty/service/loyalty.api.service';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

interface PointsCustomer {
    id: string;
}

interface PointsForm {
    points: number;
    comment: string;
}

interface MembershipForm {
    tierId: string | null;
    durationDays: number;
}

interface MembershipTierOption {
    id: string;
    name: string;
    level: number;
    minGrowthValue: number;
}

interface MembershipOrder {
    id: string;
    planName: string;
    amount: number;
    currency: string;
    state: string;
    durationDays: number;
    paidAt: string | null;
    expiresAt: string | null;
    createdAt: string | null;
}

/**
 * The points and membership tab of a customer: the membership the customer holds, the orders that
 * led to it and the point ledger. A membership can also be granted here, for a goodwill upgrade or a
 * membership that was paid outside of the shop.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['acl', 'repositoryFactory', 'loyaltyApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        customer: {
            type: Object as PropType<PointsCustomer>,
            required: true,
        },
    },

    data() {
        return {
            isLoading: true,
            isSavingPoints: false,
            isSavingMembership: false,
            page: 1,
            limit: 25,
            total: 0,
            typeFilter: null as string | null,
            loyaltySummary: null as LoyaltySummary | null,
            transactions: [] as LoyaltySummaryTransaction[],
            orders: [] as MembershipOrder[],
            tiers: [] as MembershipTierOption[],
            showPointsModal: false,
            showMembershipModal: false,
            pointsForm: { points: 0, comment: '' } as PointsForm,
            membershipForm: { tierId: null, durationDays: 365 } as MembershipForm,
        };
    },

    computed: {
        customerId(): string {
            return this.customer.id;
        },

        loyaltyTierRepository() {
            return this.repositoryFactory.create('loyalty_tier');
        },

        membershipOrderRepository() {
            return this.repositoryFactory.create('membership_order');
        },

        tierCriteria(): CriteriaType {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('active', true));
            criteria.addSorting(Criteria.sort('level', 'ASC'));

            return criteria;
        },

        orderCriteria(): CriteriaType {
            const criteria = new Criteria(1, 10);

            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },

        currentTier(): MembershipTierOption | null {
            const tierId = this.loyaltySummary?.membershipTier?.id;

            return this.tiers.find((tier) => tier.id === tierId) ?? null;
        },

        nextTier(): MembershipTierOption | null {
            const current = this.currentTier;

            if (!current) {
                return this.tiers[0] ?? null;
            }

            return this.tiers.find((tier) => tier.level > current.level) ?? null;
        },

        growthValue(): number {
            return this.loyaltySummary?.growthValue ?? 0;
        },

        /**
         * How far the customer is on the way to the next tier, 0 when there is no next tier.
         */
        growthProgress(): number {
            const next = this.nextTier;

            if (!next) {
                return 0;
            }

            const from = this.currentTier?.minGrowthValue ?? 0;
            const span = next.minGrowthValue - from;

            if (span <= 0) {
                return 100;
            }

            const reached = Math.min(Math.max(this.growthValue - from, 0), span);

            return Math.round((reached / span) * 100);
        },

        membershipValidUntil(): string | null {
            return this.loyaltySummary?.membershipValidUntil ?? null;
        },

        membershipIsRunning(): boolean {
            if (!this.membershipValidUntil) {
                return false;
            }

            return new Date(this.membershipValidUntil).getTime() > Date.now();
        },

        membershipTierLabel(): string {
            const tier = this.loyaltySummary?.membershipTier;

            if (!tier) {
                return this.$t('sw-customer.detailPoints.noMembershipTier');
            }

            return this.$t(
                'sw-customer.detailPoints.membershipTierValue',
                {
                    name: tier.name,
                    level: tier.level,
                },
                0,
            );
        },

        pointsTransactionColumns() {
            return [
                {
                    property: 'createdAt',
                    label: 'sw-customer.detailPoints.columnDate',
                },
                {
                    property: 'type',
                    label: 'sw-customer.detailPoints.columnType',
                },
                {
                    property: 'points',
                    label: 'sw-customer.detailPoints.columnPoints',
                },
                {
                    property: 'balanceAfter',
                    label: 'sw-customer.detailPoints.columnBalanceAfter',
                },
                {
                    property: 'comment',
                    label: 'sw-customer.detailPoints.columnComment',
                    allowResize: true,
                },
            ];
        },

        orderColumns() {
            return [
                {
                    property: 'planName',
                    label: 'sw-customer.detailPoints.columnOrderPlan',
                },
                {
                    property: 'amount',
                    label: 'sw-customer.detailPoints.columnOrderAmount',
                },
                {
                    property: 'state',
                    label: 'sw-customer.detailPoints.columnOrderState',
                },
                {
                    property: 'paidAt',
                    label: 'sw-customer.detailPoints.columnOrderPaidAt',
                },
            ];
        },

        typeFilterOptions() {
            return [
                { value: null, label: this.$t('sw-customer.detailPoints.filterTypeAll') },
                ...['award', 'redeem', 'adjust', 'expire'].map((type) => {
                    return {
                        value: type,
                        label: this.getPointsTransactionTypeLabel(type),
                    };
                }),
            ];
        },

        tierOptions() {
            return this.tiers.map((tier) => {
                return {
                    value: tier.id,
                    label: `${tier.name} (${this.$t('sw-customer.detailPoints.tierLevel', { level: tier.level }, 0)})`,
                };
            });
        },

        allowAdjustPoints(): boolean {
            return this.acl.can('loyalty.editor');
        },

        isPointsFormValid(): boolean {
            return this.pointsForm.points !== 0;
        },

        isMembershipFormValid(): boolean {
            return this.membershipForm.tierId !== null && this.membershipForm.durationDays > 0;
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
                await Promise.all([this.loadLoyaltySummary(), this.loadTiers(), this.loadOrders(), this.loadTransactions()]);
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadLoyaltySummary(): Promise<void> {
            const loyaltyApiService = this.loyaltyApiService;

            return loyaltyApiService.getCustomerSummary(this.customerId).then((summary) => {
                this.loyaltySummary = summary;
            });
        },

        loadTransactions(): Promise<void> {
            const loyaltyApiService = this.loyaltyApiService;

            return loyaltyApiService
                .getCustomerTransactions(this.customerId, {
                    page: this.page,
                    limit: this.limit,
                    type: this.typeFilter,
                })
                .then((result) => {
                    this.transactions = result.transactions;
                    this.total = result.total;
                });
        },

        loadTiers(): Promise<void> {
            return this.loyaltyTierRepository.search(this.tierCriteria).then((tiers) => {
                this.tiers = tiers as unknown as MembershipTierOption[];
            });
        },

        loadOrders(): Promise<void> {
            return this.membershipOrderRepository.search(this.orderCriteria).then((orders) => {
                this.orders = orders as unknown as MembershipOrder[];
            });
        },

        /**
         * Turns the machine type of a booking (`award`, `redeem`, ...) into its snippet key.
         */
        resolveTypeSnippetKey(type: string): string {
            return type.replace(/_([a-z])/g, (_match, letter: string) => letter.toUpperCase());
        },

        getPointsTransactionTypeLabel(type: string): string {
            return this.$t(`sw-customer.detailPoints.pointTransactionType.${this.resolveTypeSnippetKey(type)}`, {}, 0);
        },

        getOrderStateLabel(state: string): string {
            return this.$t(`sw-customer.detailPoints.membershipOrderState.${this.resolveTypeSnippetKey(state)}`, {}, 0);
        },

        getOrderStateVariant(state: string): string {
            if (state === 'activated') {
                return 'success';
            }

            return state === 'failed' ? 'error' : 'warning';
        },

        formatAmount(amount: number, currency: string): string {
            return this.currencyFilter(amount, currency) as string;
        },

        formatDate(date: string | null): string {
            return date ? this.dateFilter(date) : '-';
        },

        formatExpiry(date: string | null): string {
            if (!date) {
                return this.$t('sw-customer.detailPoints.membershipExpiresNever');
            }

            return this.dateFilter(date);
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

            void this.loadTransactions();
        },

        onFilterChange(): void {
            this.page = 1;

            void this.loadTransactions();
        },

        openPointsModal() {
            this.pointsForm = { points: 0, comment: '' };
            this.showPointsModal = true;
        },

        closePointsModal() {
            this.showPointsModal = false;
        },

        openMembershipModal() {
            this.membershipForm = {
                tierId: this.currentTier?.id ?? this.tiers[0]?.id ?? null,
                durationDays: 365,
            };
            this.showMembershipModal = true;
        },

        closeMembershipModal() {
            this.showMembershipModal = false;
        },

        async onSavePoints(): Promise<void> {
            if (!this.isPointsFormValid) {
                return;
            }

            this.isSavingPoints = true;

            try {
                const loyaltyApiService = this.loyaltyApiService;

                await loyaltyApiService.adjustPoints(this.customerId, this.pointsForm.points, this.pointsForm.comment);
                this.showPointsModal = false;
                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detailPoints.messagePointsSaved'),
                });

                await Promise.all([this.loadLoyaltySummary(), this.loadTransactions()]);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-customer.detailPoints.notificationErrorMessage'),
                });
            } finally {
                this.isSavingPoints = false;
            }
        },

        async onSaveMembership(): Promise<void> {
            const tierId = this.membershipForm.tierId;

            if (!this.isMembershipFormValid || tierId === null) {
                return;
            }

            this.isSavingMembership = true;

            try {
                const loyaltyApiService = this.loyaltyApiService;

                await loyaltyApiService.grantMembership(this.customerId, tierId, this.membershipForm.durationDays);
                this.showMembershipModal = false;
                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detailPoints.messageMembershipSaved'),
                });

                await Promise.all([this.loadLoyaltySummary(), this.loadOrders()]);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-customer.detailPoints.notificationMembershipErrorMessage'),
                });
            } finally {
                this.isSavingMembership = false;
            }
        },
    },
});
