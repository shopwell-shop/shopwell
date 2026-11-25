/** @sw-package framework */
import type Repository from 'src/core/data/repository.data';
import template from './sw-approval-flow-definition-list.html.twig';
import './sw-approval-flow-definition-list.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-definition-list',
    template,
    inject: ['repositoryFactory', 'acl'],
    data(): {
        definitions: { id: string; name: string; draftRevision: number | null; draftUpdatedAt: string | null }[];
        total: number;
        isLoading: boolean;
        hasError: boolean;
        loadRevision: number;
    } {
        return { definitions: [], total: 0, isLoading: false, hasError: false, loadRevision: 0 };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        definitionRepository(): Repository<'approval_flow_definition'> {
            return this.repositoryFactory.create('approval_flow_definition');
        },
        page(): number {
            return this.routeInteger(this.$route.query.page, 1);
        },
        limit(): number {
            return Math.min(100, this.routeInteger(this.$route.query.limit, 25));
        },
        term(): string {
            return typeof this.$route.query.term === 'string' ? this.$route.query.term : '';
        },
        parameters() {
            return { term: this.term, limit: this.limit, offset: (this.page - 1) * this.limit };
        },
        columns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-approval-flow.design.name'),
                    renderer: 'text' as const,
                    position: 0,
                    sortable: false,
                    allowResize: false,
                },
                {
                    property: 'draftRevision',
                    label: this.$t('sw-approval-flow.design.draftRevision'),
                    renderer: 'text' as const,
                    position: 1,
                    sortable: false,
                    allowResize: false,
                },
                {
                    property: 'draftUpdatedAt',
                    label: this.$t('sw-approval-flow.design.updatedAt'),
                    renderer: 'text' as const,
                    position: 2,
                    sortable: false,
                    allowResize: false,
                },
            ];
        },
    },
    watch: {
        parameters: {
            immediate: true,
            handler() {
                void this.loadDefinitions();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        routeInteger(value: unknown, fallback: number): number {
            if (typeof value !== 'string' || !/^\d+$/.test(value)) {
                return fallback;
            }
            const number = Number(value);
            return Number.isSafeInteger(number) && number > 0 ? number : fallback;
        },
        async loadDefinitions(): Promise<void> {
            const revision = ++this.loadRevision;
            this.isLoading = true;
            this.hasError = false;
            this.definitions = [];
            try {
                const criteria = new Shopwell.Data.Criteria(this.page, this.limit);
                criteria.setTotalCountMode(1);
                criteria.addAssociation('draft');
                criteria.addIncludes({
                    approval_flow_definition: ['id', 'name', 'draft'],
                    approval_flow_draft: [
                        'definitionId',
                        'revision',
                        'createdAt',
                        'updatedAt',
                    ],
                });
                criteria.addSorting(Shopwell.Data.Criteria.sort('name'));
                criteria.addSorting(Shopwell.Data.Criteria.sort('id'));
                if (this.term) {
                    criteria.addFilter(Shopwell.Data.Criteria.contains('name', this.term));
                }
                const result = await this.definitionRepository.search(criteria, Shopwell.Context.api);
                if (revision === this.loadRevision) {
                    this.definitions = result.map((definition) => {
                        const draft = definition.draft;
                        return {
                            id: definition.id,
                            name: definition.name,
                            draftRevision: draft?.revision ?? null,
                            draftUpdatedAt: draft?.updatedAt ?? draft?.createdAt ?? null,
                        };
                    });
                    this.total = result.total ?? 0;
                }
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
        onSearch(term: string): void {
            void this.$router.push({
                name: 'sw.approval.flow.definitions',
                query: { term, limit: String(this.limit) },
            });
        },
        onPageChange({ page, limit }: { page: number; limit: number }): void {
            void this.$router.push({
                name: 'sw.approval.flow.definitions',
                query: { ...this.$route.query, page: String(page), limit: String(limit) },
            });
        },
        definitionRoute(id: string) {
            return { name: 'sw.approval.flow.definition', params: { id }, query: { ...this.$route.query } };
        },
        date(value: string | null): string {
            return value ? Shopwell.Utils.format.date(value) : this.$t('sw-approval-flow.general.notAvailable');
        },
    },
});
