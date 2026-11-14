/** @sw-package framework */
import type { WorkflowRelease } from 'src/core/service/api/workflow.api.service';
import template from './sw-workflow-release-list.html.twig';
import './sw-workflow-release-list.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowService'],
    data(): {
        releases: WorkflowRelease[];
        total: number;
        page: number;
        limit: number;
        isLoading: boolean;
        hasError: boolean;
        requestRevision: number;
    } {
        return { releases: [], total: 0, page: 1, limit: 25, isLoading: false, hasError: false, requestRevision: 0 };
    },
    computed: {
        columns() {
            return [
                { property: 'name', label: this.$t('sw-workflow.persistence.name'), primary: true, sortable: false },
                { property: 'revision', label: this.$t('sw-workflow.persistence.revision'), sortable: false },
                { property: 'createdAt', label: this.$t('sw-workflow.publication.createdAt'), sortable: false },
            ];
        },
    },
    watch: {
        '$route.query.draftId'() {
            this.page = 1;
            void this.load();
        },
    },
    created() {
        void this.load();
    },
    beforeUnmount() {
        this.requestRevision += 1;
    },
    methods: {
        formatDate(value: string): string {
            return Shopwell.Filter.getByName('date')(value);
        },
        async load(): Promise<void> {
            const revision = ++this.requestRevision;
            this.isLoading = true;
            this.hasError = false;
            try {
                const draftId = typeof this.$route?.query.draftId === 'string' ? this.$route.query.draftId : undefined;
                const params = { limit: this.limit, offset: (this.page - 1) * this.limit };
                const result = await this.workflowService.getReleases({ ...params, draftId });
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
