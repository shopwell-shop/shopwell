import type Repository from 'src/core/data/repository.data';
import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';
import { getDashboardPeriod, type DashboardPeriod } from '../../helper/dashboard-period';
import type { TodayStatistics } from '../../helper/today-statistics';

/**
 * @sw-package after-sales
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl'],

    data(): { summary: TodayStatistics | null; isLoading: boolean; salesError: boolean; requestId: number } {
        return { summary: null, isLoading: false, salesError: false, requestId: 0 };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },
        orderRepository(): Repository<'order'> {
            return this.repositoryFactory.create('order');
        },
        period(): DashboardPeriod {
            const day = this.summary?.date;
            return day ? { date: day } : getDashboardPeriod();
        },
    },

    created() {
        void this.loadSales();
    },

    methods: {
        async loadSales(): Promise<void> {
            const requestId = ++this.requestId;
            if (!this.canViewOrders) {
                return;
            }
            this.isLoading = true;
            this.salesError = false;
            try {
                const httpClient = Shopwell.Application.getContainer('init').httpClient as {
                    get: (url: string, config: { headers: unknown }) => Promise<{ data: TodayStatistics }>;
                };
                const response = await httpClient.get('/_admin/dashboard/today', {
                    headers: this.orderRepository.buildHeaders(),
                });
                if (requestId === this.requestId) {
                    this.summary = response.data;
                }
            } catch {
                if (requestId === this.requestId) {
                    this.summary = null;
                    this.salesError = true;
                }
            } finally {
                if (requestId === this.requestId) {
                    this.isLoading = false;
                }
            }
        },
    },
});
