/** @sw-package framework */
import type { WorkflowRelease } from 'src/core/service/api/workflow.api.service';
import template from './sw-workflow-request-picker.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowService', 'workflowReleaseActionService'],
    emits: ['modal-close'],
    data() {
        return {
            releases: [] as WorkflowRelease[],
            total: 0,
            page: 1,
            limit: 10,
            isLoading: false,
            hasError: false,
            requestRevision: 0,
        };
    },
    computed: {
        columns() {
            return [
                { property: 'name', label: this.$t('sw-workflow.persistence.name'), primary: true, sortable: false },
                { property: 'action', label: this.$t('sw-workflow.publication.action'), sortable: false },
            ];
        },
    },
    created() {
        void this.load();
    },
    beforeUnmount() {
        this.requestRevision += 1;
    },
    methods: {
        actionComponent(release: WorkflowRelease): string | null {
            return this.workflowReleaseActionService.get(release.requestAction ?? release.publisher);
        },
        async load(): Promise<void> {
            const revision = ++this.requestRevision;
            this.isLoading = true;
            this.hasError = false;
            this.releases = [];
            try {
                const result = await this.workflowService.getRequestCatalogue({
                    limit: this.limit,
                    offset: (this.page - 1) * this.limit,
                });
                if (revision !== this.requestRevision) return;
                this.releases = result.data;
                this.total = result.total;
            } catch {
                if (revision === this.requestRevision) this.hasError = true;
            } finally {
                if (revision === this.requestRevision) this.isLoading = false;
            }
        },
        changePage({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
            void this.load();
        },
    },
});
