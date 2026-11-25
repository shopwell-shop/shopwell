/** @sw-package framework */
import type { PropType } from 'vue';
import type { ApprovalRequest, ApprovalSearch, ApprovalStatus, ApprovalView } from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-list.html.twig';
import './sw-approval-flow-list.scss';

const statuses: ApprovalStatus[] = [
    'draft',
    'running',
    'suspended',
    'completed',
    'terminated',
    'withdrawn',
];

function routeInteger(value: unknown, fallback: number): number {
    if (typeof value !== 'string' || !/^\d+$/.test(value)) {
        return fallback;
    }
    const number = Number(value);
    return Number.isSafeInteger(number) && number > 0 ? number : fallback;
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-list',
    template,
    inject: ['approvalFlowApiService', 'acl'],
    props: {
        view: { type: String as PropType<ApprovalView>, required: true },
    },
    data() {
        return {
            requests: [] as ApprovalRequest[],
            total: 0,
            isLoading: false,
            hasError: false,
            loadRevision: 0,
        };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        page(): number {
            return routeInteger(this.$route.query.page, 1);
        },
        limit(): number {
            return Math.min(100, routeInteger(this.$route.query.limit, 25));
        },
        status(): ApprovalStatus | '' {
            return statuses.find((status) => status === this.$route.query.status) ?? '';
        },
        term(): string {
            return typeof this.$route.query.term === 'string' ? this.$route.query.term.trim() : '';
        },
        requestParameters(): ApprovalSearch {
            return {
                view: this.view,
                limit: this.limit,
                offset: (this.page - 1) * this.limit,
                ...(this.status ? { status: this.status } : {}),
                ...(this.term ? { term: this.term } : {}),
            };
        },
        tabs() {
            return (['todo', 'done', 'started'] as const).map((view) => ({
                name: view,
                label: this.$t(`sw-approval-flow.views.${view}`),
            }));
        },
        statusOptions() {
            return [
                { value: '', label: this.$t('sw-approval-flow.list.allStatuses') },
                ...statuses.map((status) => ({ value: status, label: this.$t(`sw-approval-flow.status.${status}`) })),
            ];
        },
        columns() {
            const labels = {
                title: this.$t('sw-approval-flow.list.request'),
                initiatorName: this.$t('sw-approval-flow.list.initiator'),
                status: this.$t('sw-approval-flow.list.status'),
                createdAt: this.$t('sw-approval-flow.list.createdAt'),
            };

            return Object.entries(labels).map(([property, label], position) => ({
                property,
                label,
                position,
                renderer: 'text' as const,
                sortable: false,
                allowResize: false,
            }));
        },
    },
    watch: {
        requestParameters: {
            immediate: true,
            handler() {
                void this.loadRequests();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        async loadRequests(): Promise<void> {
            const revision = ++this.loadRevision;
            this.isLoading = true;
            this.hasError = false;
            this.requests = [];
            try {
                const result = await this.approvalFlowApiService.search(this.requestParameters);
                if (revision !== this.loadRevision) {
                    return;
                }
                this.requests = result.data;
                this.total = result.meta.total;
            } catch {
                if (revision === this.loadRevision) {
                    this.hasError = true;
                    this.total = 0;
                }
            } finally {
                if (revision === this.loadRevision) {
                    this.isLoading = false;
                }
            }
        },
        onViewChange(view: ApprovalView): void {
            void this.$router.push({
                name: `sw.approval.flow.${view}`,
                query: { limit: String(this.limit), ...(this.term ? { term: this.term } : {}) },
            });
        },
        onStatusChange(status: ApprovalStatus | '', close: () => void): void {
            close();
            void this.$router.push({
                name: this.$route.name,
                query: {
                    limit: String(this.limit),
                    ...(status ? { status } : {}),
                    ...(this.term ? { term: this.term } : {}),
                },
            });
        },
        onSearch(value: string): void {
            const term = value.trim();
            void this.$router.push({
                name: this.$route.name,
                query: {
                    limit: String(this.limit),
                    ...(this.status ? { status: this.status } : {}),
                    ...(term ? { term } : {}),
                },
            });
        },
        onPageChange({ page, limit }: { page: number; limit: number }): void {
            void this.$router.push({
                name: this.$route.name,
                query: { ...this.$route.query, page: String(page), limit: String(limit) },
            });
        },
        detailRoute(id: string) {
            return { name: 'sw.approval.flow.detail', params: { id }, query: { ...this.$route.query, view: this.view } };
        },
        date(value: string | null): string {
            return value
                ? Shopwell.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' })
                : this.$t('sw-approval-flow.general.notAvailable');
        },
    },
});
