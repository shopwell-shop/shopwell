/** @sw-package framework */
import type Repository from 'src/core/data/repository.data';
import axios from 'axios';
import template from './sw-workflow-list.html.twig';
import './sw-workflow-list.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'workflowService', 'acl'],
    data(): {
        drafts: EntityCollection<'workflow_draft'> | null;
        isLoading: boolean;
        isDeleting: boolean;
        error: 'loadFailed' | 'deleteFailed' | 'deleteConflict' | null;
        deletion: Entity<'workflow_draft'> | null;
        page: number;
        limit: number;
        total: number;
        term: string;
        requestRevision: number;
    } {
        return {
            drafts: null,
            isLoading: false,
            isDeleting: false,
            error: null,
            deletion: null,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            requestRevision: 0,
        };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        allowCreate(): boolean {
            return this.acl.can('workflow.creator');
        },
        isHistory(): boolean {
            return this.$route?.name === 'sw.workflow.releases';
        },
        tabs() {
            return [
                {
                    name: 'sw.workflow.index',
                    label: this.$t('sw-workflow.navigation.designs'),
                    onClick: () => this.$router.push({ name: 'sw.workflow.index' }),
                },
                {
                    name: 'sw.workflow.releases',
                    label: this.$t('sw-workflow.publication.catalogue'),
                    onClick: () => this.$router.push({ name: 'sw.workflow.releases' }),
                },
            ];
        },
        repository(): Repository<'workflow_draft'> {
            return this.repositoryFactory.create('workflow_draft');
        },
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-workflow.persistence.name'),
                    primary: true,
                    allowResize: true,
                    sortable: false,
                },
                { property: 'revision', label: this.$t('sw-workflow.persistence.revision'), sortable: false },
                { property: 'updatedAt', label: this.$t('sw-workflow.persistence.updatedAt'), sortable: false },
            ];
        },
    },
    watch: {
        '$route.name'() {
            if (!this.isHistory) void this.load();
        },
    },
    created() {
        if (!this.isHistory) void this.load();
    },
    beforeUnmount() {
        this.requestRevision += 1;
    },
    methods: {
        onCreateWorkflow(): void {
            if (this.allowCreate) void this.$router.push({ name: 'sw.workflow.create' });
        },
        formatDate(value: string | null): string {
            return value ? Shopwell.Filter.getByName('date')(value) : '';
        },
        async load(): Promise<void> {
            const revision = ++this.requestRevision;
            this.isLoading = true;
            this.error = null;
            const criteria = new Shopwell.Data.Criteria(this.page, this.limit);
            criteria.addFields('id', 'name', 'revision', 'createdAt', 'updatedAt');
            criteria.setTotalCountMode(1);
            criteria.addSorting(Shopwell.Data.Criteria.sort('createdAt', 'DESC'));
            if (this.term) criteria.setTerm(this.term);
            try {
                const drafts = await this.repository.search(criteria, Shopwell.Context.api);
                if (revision !== this.requestRevision) return;
                const lastPage = Math.max(1, Math.ceil((drafts.total ?? 0) / this.limit));
                if (this.page > lastPage) {
                    this.page = lastPage;
                    await this.load();
                    return;
                }
                this.drafts = drafts;
                this.total = drafts.total ?? 0;
            } catch {
                if (revision === this.requestRevision) this.error = 'loadFailed';
            } finally {
                if (revision === this.requestRevision) this.isLoading = false;
            }
        },
        search(term: string): void {
            this.term = term;
            this.page = 1;
            void this.load();
        },
        changePage({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
            void this.load();
        },
        async remove(): Promise<void> {
            if (!this.deletion || this.isDeleting || !this.acl.can('workflow.deleter')) return;
            this.isDeleting = true;
            this.error = null;
            try {
                await this.workflowService.deleteDraft(this.deletion.id, this.deletion.revision ?? 0);
                this.deletion = null;
                if (this.drafts?.length === 1 && this.page > 1) this.page -= 1;
                await this.load();
            } catch (error) {
                this.error = axios.isAxiosError(error) && error.response?.status === 409 ? 'deleteConflict' : 'deleteFailed';
                this.deletion = null;
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
