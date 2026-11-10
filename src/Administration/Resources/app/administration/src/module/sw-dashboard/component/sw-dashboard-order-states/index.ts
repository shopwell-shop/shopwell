import type { PropType } from 'vue';
import VChart from 'vue-echarts';
import { use, type ComposeOption } from 'echarts/core';
import { PieChart, type PieSeriesOption } from 'echarts/charts';
import { SVGRenderer } from 'echarts/renderers';
import type Repository from 'src/core/data/repository.data';
import { getDashboardPeriod, getPeriodBounds, type DashboardPeriod } from '../../helper/dashboard-period';
import template from './sw-dashboard-order-states.html.twig';
import './sw-dashboard-order-states.scss';

use([PieChart, SVGRenderer]);

const { Criteria } = Shopwell.Data;

type StateBucket = { key: string; orderCount?: { count?: number } };
/**
 * @sw-package after-sales
 *
 * Order states for today, shown in business workflow order.
 *
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    components: { 'v-chart': VChart },

    inject: ['repositoryFactory', 'acl'],

    props: { period: { type: Object as PropType<DashboardPeriod>, default: () => getDashboardPeriod() } },

    data(): {
        buckets: Array<{ key: string; name: string; count: number }>;
        isLoading: boolean;
        loadError: boolean;
        requestId: number;
    } {
        return {
            buckets: [],
            isLoading: true,
            loadError: false,
            requestId: 0,
        };
    },

    computed: {
        orderRepository(): Repository<'order'> {
            return this.repositoryFactory.create('order');
        },

        stateMachineStateRepository(): Repository<'state_machine_state'> {
            return this.repositoryFactory.create('state_machine_state');
        },

        canViewOrders(): boolean {
            return this.acl.can('order.viewer');
        },

        totalOrders(): number {
            return this.buckets.reduce((total, state) => total + state.count, 0);
        },

        stateRows(): Array<{ key: string; name: string; count: number; percentage: number; color: string }> {
            const order: Record<string, number> = { open: 0, in_progress: 1, completed: 2, cancelled: 3 };
            const colors: Record<string, string> = {
                open: 'var(--color-background-accent-default)',
                in_progress: 'var(--dashboard-state-progress)',
                completed: 'var(--dashboard-state-completed)',
                cancelled: 'var(--color-background-tertiary-default)',
            };
            return [...this.buckets]
                .sort(
                    (first, second) =>
                        (order[first.key] ?? 4) - (order[second.key] ?? 4) || first.key.localeCompare(second.key),
                )
                .map((state) => ({
                    ...state,
                    color: colors[state.key] ?? 'var(--color-background-secondary-default)',
                    percentage: this.totalOrders > 0 ? (state.count / this.totalOrders) * 100 : 0,
                }));
        },

        chartOptions(): ComposeOption<PieSeriesOption> {
            return {
                media: [
                    {
                        query: { maxWidth: 340 },
                        option: {
                            series: [{ radius: '42%', labelLine: { length: 8, length2: 8 } }],
                        },
                    },
                    {
                        option: {
                            series: [{ radius: '58%', labelLine: { length: 14, length2: 14 } }],
                        },
                    },
                ],
                animation: false,
                series: [
                    {
                        type: 'pie',
                        radius: '58%',
                        center: ['50%', '50%'],
                        startAngle: 90,
                        avoidLabelOverlap: true,
                        percentPrecision: 1,
                        selectedMode: false,
                        emphasis: { disabled: true, scale: false },
                        itemStyle: {
                            borderColor: 'var(--color-background-primary-default)',
                            borderWidth: 2,
                            borderRadius: 3,
                        },
                        label: {
                            show: true,
                            position: 'outside',
                            alignTo: 'edge',
                            edgeDistance: 8,
                            distanceToLabelLine: 6,
                            bleedMargin: 4,
                            formatter: (params) => {
                                const count = typeof params.value === 'number' ? params.value.toLocaleString() : '';
                                const percentage = (params.percent ?? 0).toFixed(1);
                                return `{name|${params.name}}\n{value|${count}}  {percent|${percentage}%}`;
                            },
                            rich: {
                                name: { color: 'var(--color-text-primary-default)', fontSize: 12, lineHeight: 22 },
                                value: {
                                    color: 'var(--color-text-primary-default)',
                                    fontSize: 14,
                                    fontWeight: 600,
                                    lineHeight: 22,
                                },
                                percent: { color: 'var(--color-text-secondary-default)', fontSize: 12, lineHeight: 22 },
                            },
                        },
                        labelLine: { show: true, length: 14, length2: 14, smooth: 0.25, lineStyle: { width: 1.5 } },
                        labelLayout: { moveOverlap: 'shiftY' },
                        data: this.stateRows
                            .filter((state) => state.count > 0)
                            .map((state) => ({
                                name: state.name,
                                value: state.count,
                                itemStyle: { color: state.color },
                            })),
                    },
                ],
            };
        },

        chartHeight(): number {
            return Math.max(300, this.stateRows.length * 34 + 100);
        },

        hasData(): boolean {
            return this.totalOrders > 0;
        },
    },

    created() {
        void this.loadStates();
    },

    watch: {
        period() {
            void this.loadStates();
        },
    },

    methods: {
        async loadStates(): Promise<void> {
            if (!this.canViewOrders) {
                this.isLoading = false;

                return;
            }

            const requestId = ++this.requestId;
            this.isLoading = true;
            this.loadError = false;

            try {
                const [buckets, states] = await Promise.all([
                    this.fetchStateBuckets(),
                    this.fetchOrderStateNames(),
                ]);

                if (requestId !== this.requestId) {
                    return;
                }
                const names = new Map(states.map((state) => [state.technicalName, state.name]));

                this.buckets = buckets.map((bucket) => {
                    return {
                        key: bucket.key,
                        name: names.get(bucket.key) ?? bucket.key,
                        count: bucket.orderCount?.count ?? 0,
                    };
                });
            } catch {
                if (requestId === this.requestId) {
                    this.loadError = true;
                    this.buckets = [];
                }
            } finally {
                if (requestId === this.requestId) {
                    this.isLoading = false;
                }
            }
        },

        fetchStateBuckets(): Promise<StateBucket[]> {
            const bounds = getPeriodBounds(this.period);
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(0);
            criteria.addFilter(Criteria.range('orderDateTime', { gte: bounds.from, lte: bounds.to }));
            criteria.addAggregation(
                Criteria.terms(
                    'orderStates',
                    'stateMachineState.technicalName',
                    null,
                    null,
                    Criteria.count('orderCount', 'id'),
                ),
            );
            return this.orderRepository
                .search(criteria)
                .then((result) => (result.aggregations?.orderStates as { buckets?: StateBucket[] })?.buckets ?? []);
        },

        /**
         * The translated names of the order state machine, so the legend speaks
         * the language of the operator instead of technical names.
         */
        fetchOrderStateNames(): Promise<Array<{ technicalName: string; name: string }>> {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order.state'));

            criteria.setTotalCountMode(0);
            return this.stateMachineStateRepository.search(criteria).then((states) =>
                states.map((state) => ({
                    technicalName: state.technicalName,
                    name: state.translated?.name ?? state.name,
                })),
            );
        },
    },
});
