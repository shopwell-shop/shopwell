/** @sw-package framework */
import type Repository from 'src/core/data/repository.data';
import type { HttpError } from 'src/core/factory/http-client.types';
import type {
    ApprovalGraphDiagnostic,
    ApprovalDesignCatalog,
    ApprovalDesignGraph,
} from '../../service/approval-flow-design.api.service';
import template from './sw-approval-flow-definition-detail.html.twig';
import './sw-approval-flow-definition-detail.scss';

type ApprovalDefinitionDetail = {
    definition: Entity<'approval_flow_definition'>;
    draft: { revision: number; graphJson: string; diagnostics: ApprovalGraphDiagnostic[] };
};

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-definition-detail',
    template,
    inject: ['repositoryFactory', 'approvalFlowDesignApiService', 'acl'],
    props: { definitionId: { type: String, required: true } },
    data(): {
        detail: ApprovalDefinitionDetail | null;
        catalog: ApprovalDesignCatalog | null;
        isLoading: boolean;
        loadError: string | null;
        catalogError: boolean;
        loadRevision: number;
        publicationPreview: { revision: number; contentHash: string } | null;
        isPublishing: boolean;
        publicationError: string | null;
        releaseListVersion: number;
    } {
        return {
            detail: null,
            catalog: null,
            isLoading: false,
            loadError: null,
            catalogError: false,
            loadRevision: 0,
            publicationPreview: null,
            isPublishing: false,
            publicationError: null,
            releaseListVersion: 0,
        };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        definitionRepository(): Repository<'approval_flow_definition'> {
            return this.repositoryFactory.create('approval_flow_definition');
        },
        backRoute() {
            return { name: 'sw.approval.flow.definitions', query: { ...this.$route.query } };
        },
        graph(): ApprovalDesignGraph | null {
            // The server's draft codec has already admitted the versioned graph wire shape.
            return this.detail ? (JSON.parse(this.detail.draft.graphJson) as ApprovalDesignGraph) : null;
        },
        nodes() {
            const descriptors = new Map(this.catalog?.nodeTypes.map((type) => [type.key, type]));
            return (this.graph?.nodes ?? []).map((node) => {
                const key = `${node.type}@${node.typeVersion}`;
                const descriptor = descriptors.get(key);
                return {
                    id: node.id,
                    key,
                    label: descriptor && this.$te(descriptor.labelSnippet) ? this.$t(descriptor.labelSnippet) : key,
                    unavailable: this.catalog !== null && !descriptor,
                };
            });
        },
    },
    watch: {
        definitionId: {
            immediate: true,
            handler() {
                void this.loadDefinition();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        async loadDocument(): Promise<ApprovalDefinitionDetail | null> {
            const criteria = new Shopwell.Data.Criteria();
            criteria.addAssociation('draft');
            criteria.addIncludes({
                approval_flow_definition: [
                    'id',
                    'name',
                    'createdAt',
                    'updatedAt',
                    'draft',
                ],
                approval_flow_draft: ['definitionId', 'revision', 'graphJson'],
            });
            const definition = await this.definitionRepository.get(
                this.definitionId as EntityKey<'approval_flow_definition'>,
                Shopwell.Context.api,
                criteria,
            );
            const draft = definition?.draft;
            if (!definition || !draft) {
                return null;
            }
            const diagnostics = await this.approvalFlowDesignApiService.diagnose(draft.graphJson);
            return { definition, draft: { revision: draft.revision, graphJson: draft.graphJson, diagnostics } };
        },
        async loadDefinition(): Promise<void> {
            const revision = ++this.loadRevision;
            this.detail = null;
            this.catalog = null;
            this.isLoading = true;
            this.loadError = null;
            this.catalogError = false;
            this.publicationPreview = null;
            this.publicationError = null;
            this.isPublishing = false;
            this.releaseListVersion = 0;
            const [definition, catalog] = await Promise.allSettled([
                this.loadDocument(),
                this.approvalFlowDesignApiService.catalog(),
            ]);
            if (revision !== this.loadRevision) {
                return;
            }
            this.isLoading = false;
            if (definition.status === 'fulfilled') {
                this.detail = definition.value;
                if (!this.detail) {
                    this.loadError = 'notFound';
                }
            } else {
                const status = (definition.reason as HttpError | null)?.response?.status;
                this.loadError = status === 404 ? 'notFound' : status === 403 ? 'forbidden' : 'detailError';
            }
            if (catalog.status === 'fulfilled') {
                this.catalog = catalog.value;
            } else {
                this.catalogError = true;
            }
        },
        async preparePublication(): Promise<void> {
            if (!this.detail) {
                return;
            }
            const { revision } = this.detail.draft;
            const loadRevision = this.loadRevision;
            this.isPublishing = true;
            this.publicationError = null;
            try {
                const { contentHash } = await this.approvalFlowDesignApiService.previewPublication(
                    this.definitionId,
                    revision,
                );
                if (loadRevision === this.loadRevision) {
                    this.publicationPreview = { revision, contentHash };
                }
            } catch (error) {
                this.showPublicationError(error, loadRevision);
            } finally {
                if (loadRevision === this.loadRevision) {
                    this.isPublishing = false;
                }
            }
        },
        async publish(): Promise<void> {
            const preview = this.publicationPreview;
            if (!preview) {
                return;
            }

            const loadRevision = this.loadRevision;
            this.isPublishing = true;
            try {
                await this.approvalFlowDesignApiService.publish(this.definitionId, preview.revision, preview.contentHash);
                if (loadRevision === this.loadRevision) {
                    this.releaseListVersion += 1;
                }
            } catch (error) {
                this.showPublicationError(error, loadRevision);
            } finally {
                if (loadRevision === this.loadRevision) {
                    this.publicationPreview = null;
                    this.isPublishing = false;
                }
            }
        },
        showPublicationError(error: unknown, loadRevision: number): void {
            if (loadRevision !== this.loadRevision) {
                return;
            }
            const response = (error as HttpError<{ errors?: { detail?: string }[] }>).response;
            this.publicationError =
                response?.status === 409
                    ? this.$t('sw-approval-flow.design.publicationConflict')
                    : (response?.data.errors?.[0]?.detail ?? this.$t('sw-approval-flow.design.publicationError'));
        },
        closePublication(): void {
            if (!this.isPublishing) {
                this.publicationPreview = null;
            }
        },
        date(value: string | null): string {
            return value ? Shopwell.Utils.format.date(value) : this.$t('sw-approval-flow.general.notAvailable');
        },
    },
});
