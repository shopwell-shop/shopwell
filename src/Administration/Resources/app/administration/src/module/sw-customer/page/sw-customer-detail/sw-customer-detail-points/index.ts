/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';

import template from './sw-customer-detail-points.html.twig';
import './sw-customer-detail-points.scss';
import type { LoyaltySummary, LoyaltySummaryTransaction } from '../../../../sw-loyalty/service/loyalty.api.service';
import type LoyaltyApiService from '../../../../sw-loyalty/service/loyalty.api.service';

const { Mixin } = Shopwell;

interface PointsCustomer {
    id: string;
}

interface PointsForm {
    points: number;
    comment: string;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['acl', 'loyaltyApiService'],

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
            page: 1,
            limit: 25,
            loyaltySummary: null as LoyaltySummary | null,
            showPointsModal: false,
            pointsForm: { points: 0, comment: '' } as PointsForm,
        };
    },

    computed: {
        customerId(): string {
            return this.customer.id;
        },

        pointsTransactions(): LoyaltySummaryTransaction[] {
            return this.loyaltySummary?.transactions ?? [];
        },

        totalPointsTransactions(): number {
            return this.pointsTransactions.length;
        },

        paginatedPointsTransactions(): LoyaltySummaryTransaction[] {
            const start = (this.page - 1) * this.limit;

            return this.pointsTransactions.slice(start, start + this.limit);
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

        allowAdjustPoints(): boolean {
            return this.acl.can('loyalty.editor');
        },

        isPointsFormValid(): boolean {
            return this.pointsForm.points !== 0;
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
                await this.loadLoyaltySummary();
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadLoyaltySummary(): Promise<void> {
            const loyaltyApiService = this.loyaltyApiService as LoyaltyApiService;

            return loyaltyApiService.getCustomerSummary(this.customerId).then((summary) => {
                this.loyaltySummary = summary;
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

        formatDate(date: string | null): string {
            return date ? this.dateFilter(date) : '-';
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
        },

        openPointsModal() {
            this.pointsForm = { points: 0, comment: '' };
            this.showPointsModal = true;
        },

        closePointsModal() {
            this.showPointsModal = false;
        },

        async onSavePoints(): Promise<void> {
            if (!this.isPointsFormValid) {
                return;
            }

            this.isSavingPoints = true;

            try {
                const loyaltyApiService = this.loyaltyApiService as LoyaltyApiService;

                await loyaltyApiService.adjustPoints(this.customerId, this.pointsForm.points, this.pointsForm.comment);
                this.showPointsModal = false;
                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detailPoints.messagePointsSaved'),
                });

                await this.loadLoyaltySummary();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-customer.detailPoints.notificationErrorMessage'),
                });
            } finally {
                this.isSavingPoints = false;
            }
        },
    },
});
