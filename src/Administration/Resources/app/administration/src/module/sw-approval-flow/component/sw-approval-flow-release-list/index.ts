/** @sw-package framework */
import type Repository from 'src/core/data/repository.data';
import type { PropType } from 'vue';
import type { ApprovalBusinessDescriptor, ApprovalDesignGraph } from '../../service/approval-flow-design.api.service';
import template from './sw-approval-flow-release-list.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-release-list',
    template,
    inject: ['repositoryFactory'],
    props: {
        definitionId: { type: String, required: true },
        allowActivation: { type: Boolean, default: false },
        businessTypes: { type: Array as PropType<ApprovalBusinessDescriptor[]>, default: () => [] },
    },
    data(): {
        releases: Entity<'approval_flow_release'>[];
        page: number;
        limit: number;
        total: number;
        isLoading: boolean;
        hasError: boolean;
        loadRevision: number;
        selectedRelease: Entity<'approval_flow_release'> | null;
        activatedReleaseId: string | null;
    } {
        return {
            releases: [],
            page: 1,
            limit: 10,
            total: 0,
            isLoading: false,
            hasError: false,
            loadRevision: 0,
            selectedRelease: null,
            activatedReleaseId: null,
        };
    },
    computed: {
        releaseRepository(): Repository<'approval_flow_release'> {
            return this.repositoryFactory.create('approval_flow_release');
        },
        columns() {
            const columns = [
                {
                    property: 'draftRevision',
                    label: this.$t('sw-approval-flow.design.draftRevision'),
                    renderer: 'text' as const,
                    position: 0,
                    sortable: false,
                    allowResize: false,
                },
                {
                    property: 'createdAt',
                    label: this.$t('sw-approval-flow.design.publishedAt'),
                    renderer: 'text' as const,
                    position: 1,
                    sortable: false,
                    allowResize: false,
                },
                {
                    property: 'contentHash',
                    label: this.$t('sw-approval-flow.design.releaseIdentity'),
                    renderer: 'text' as const,
                    position: 2,
                    sortable: false,
                    allowResize: false,
                    cellWrap: 'normal' as const,
                },
            ];
            if (this.allowActivation) {
                columns.push({
                    property: 'actions',
                    label: this.$t('sw-approval-flow.list.actions'),
                    renderer: 'text',
                    position: 3,
                    sortable: false,
                    allowResize: false,
                    cellWrap: 'normal',
                });
            }
            return columns;
        },
        activationBusinessTypes(): ApprovalBusinessDescriptor[] {
            if (!this.selectedRelease) {
                return [];
            }
            const graph = JSON.parse(this.selectedRelease.graphJson) as ApprovalDesignGraph;
            const businessType = graph.nodes.find((node) => node.type === 'core:start')?.config.businessType;
            return this.businessTypes.filter(
                (business) => business.type === businessType && business.formSchemaId === graph.meta.formSchemaRef,
            );
        },
    },
    watch: {
        definitionId: {
            immediate: true,
            handler() {
                this.page = 1;
                this.selectedRelease = null;
                this.activatedReleaseId = null;
                void this.loadReleases();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        async loadReleases(): Promise<void> {
            const revision = ++this.loadRevision;
            this.isLoading = true;
            this.hasError = false;
            this.releases = [];
            try {
                const criteria = new Shopwell.Data.Criteria(this.page, this.limit);
                criteria.setTotalCountMode(1);
                criteria.addIncludes({
                    approval_flow_release: [
                        'id',
                        'draftRevision',
                        'createdAt',
                        'contentHash',
                        ...(this.allowActivation ? ['graphJson'] : []),
                    ],
                });
                criteria.addFilter(Shopwell.Data.Criteria.equals('definitionId', this.definitionId));
                criteria.addSorting(Shopwell.Data.Criteria.sort('createdAt', 'DESC'));
                criteria.addSorting(Shopwell.Data.Criteria.sort('id', 'DESC'));
                const result = await this.releaseRepository.search(criteria, Shopwell.Context.api);
                if (revision === this.loadRevision) {
                    this.releases = result;
                    this.total = result.total ?? 0;
                }
            } catch {
                if (revision === this.loadRevision) {
                    this.hasError = true;
                }
            } finally {
                if (revision === this.loadRevision) {
                    this.isLoading = false;
                }
            }
        },
        onActivate(release: Entity<'approval_flow_release'>): void {
            this.selectedRelease = release;
        },
        onActivated(releaseId: string): void {
            this.activatedReleaseId = releaseId;
            this.selectedRelease = null;
        },
        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = Number.isSafeInteger(page) && page > 0 ? page : 1;
            this.limit = limit;
            void this.loadReleases();
        },
        date(value: string | null): string {
            return value
                ? Shopwell.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' })
                : this.$t('sw-approval-flow.general.notAvailable');
        },
    },
});
