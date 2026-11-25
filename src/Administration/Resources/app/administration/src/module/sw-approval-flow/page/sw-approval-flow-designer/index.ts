/** @sw-package framework */
import type { RouteLocationNormalized } from 'vue-router';
import type { HttpError } from 'src/core/factory/http-client.types';
import type { ApprovalConnectionEndpoints } from '../../designer/approval-editor-session';
import { useApprovalEditorSession } from '../../designer/approval-editor-session';
import { APPROVAL_NODE_STEP } from '../../designer/approval-editing.extension';
import type { ApprovalNodeView } from '../../designer/approval-designer.service';
import type {
    ApprovalDesignCatalog,
    ApprovalDesignDraft,
    ApprovalDesignGraph,
    ApprovalGraphDiagnostic,
    ApprovalJsonValue,
} from '../../service/approval-flow-design.api.service';
import template from './sw-approval-flow-designer.html.twig';
import './sw-approval-flow-designer.scss';

interface DesignerCanvas {
    fit(): void;
    zoom(delta: number): void;
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: [
        'repositoryFactory',
        'approvalDesignerService',
        'approvalFlowDesignApiService',
        'acl',
    ],
    props: {
        definitionId: { type: String, default: null },
    },

    setup() {
        return useApprovalEditorSession();
    },

    data() {
        return {
            catalog: null as ApprovalDesignCatalog | null,
            diagnostics: [] as ApprovalGraphDiagnostic[],
            revision: null as number | null,
            selectedType: null as string | null,
            isLoading: false,
            isSaving: false,
            loadError: false,
            saveError: null as string | null,
            loadSequence: 0,
            showDiagnostics: false,
            showDraftSettings: true,
        };
    },

    computed: {
        canEdit(): boolean {
            return this.acl.can(this.definitionId ? 'approval_flow_definition.editor' : 'approval_flow_definition.creator');
        },
        readOnly(): boolean {
            return !this.canEdit || this.isSaving;
        },
        selectedNodeId(): string | null {
            return this.editorState?.selection.nodeId ?? null;
        },
        selectedConnection(): string | null {
            return this.editorState?.selection.connectionId ?? null;
        },
        toolbarActions() {
            return this.editorState ? (this.editorSession?.actions('toolbar') ?? []) : [];
        },
        panels() {
            const nodeId = this.selectedNodeId ?? undefined;
            const connectionId = this.selectedConnection ?? undefined;
            return this.editorState ? (this.editorSession?.components('panel', { nodeId, connectionId }) ?? []) : [];
        },
        showSettings(): boolean {
            return (
                (!this.definitionId && this.showDraftSettings) ||
                this.selectedNodeId !== null ||
                this.selectedConnection !== null ||
                this.panels.length > 0
            );
        },
        settingsTitle(): string {
            return this.selectedNodeId
                ? (this.nodeViews[this.selectedNodeId]?.title ?? this.$t('sw-approval-flow.designer.settings'))
                : this.$t('sw-approval-flow.designer.settings');
        },
        selectedNode() {
            return this.graph?.nodes.find((node) => node.id === this.selectedNodeId);
        },
        selectedSettings(): string | undefined {
            const node = this.selectedNode;
            if (!node || !this.catalog?.nodeTypes.some((type) => type.key === `${node.type}@${node.typeVersion}`)) {
                return undefined;
            }

            return this.approvalDesignerService.getNode(`${node.type}@${node.typeVersion}`)?.settingsComponent;
        },
        nodeViews(): Record<string, ApprovalNodeView> {
            const nodeTypes = new Map(this.catalog?.nodeTypes.map((node) => [node.key, node]));
            const businessLabels = new Map(
                this.catalog?.businessTypes.map((business) => [business.type, business.labelSnippet]),
            );

            return Object.fromEntries(
                (this.graph?.nodes ?? []).map((node) => {
                    const key = `${node.type}@${node.typeVersion}`;
                    const label = nodeTypes.get(key)?.labelSnippet ?? key;
                    const presentation = this.approvalDesignerService.getNode(key);
                    const view = presentation?.view?.(node, (name, count) => {
                        const snippet = businessLabels.get(name) ?? name;
                        return this.$te(snippet) ? this.$t(snippet, count ?? 1) : name;
                    });

                    return [node.id, { title: this.$te(label) ? this.$t(label) : key, ...view }];
                }),
            );
        },
        selectedTypeDescriptor() {
            return this.catalog?.nodeTypes.find((node) => node.key === this.selectedType);
        },
        canInsertNode(): boolean {
            const ports = Object.values(this.selectedTypeDescriptor?.ports ?? {});
            return ports.length === 2 && ports.includes('input') && ports.includes('output');
        },
        nodeOptions() {
            return (this.catalog?.nodeTypes ?? [])
                .filter(
                    (type) =>
                        type.type !== 'core:start' &&
                        type.type !== 'core:end' &&
                        this.approvalDesignerService.getNode(type.key),
                )
                .map((type) => ({
                    value: type.key,
                    label: this.$te(type.labelSnippet) ? this.$t(type.labelSnippet) : type.key,
                }));
        },
        nodeSelectionOptions() {
            return (this.graph?.nodes ?? []).map((node) => ({
                value: node.id,
                label: this.nodeViews[node.id].title,
            }));
        },
    },

    watch: {
        definitionId: {
            immediate: true,
            handler() {
                void this.loadDesigner();
            },
        },
        graph() {
            this.diagnostics = [];
            this.showDiagnostics = false;
        },
    },

    beforeRouteLeave(this: { canLeave(): boolean }) {
        return this.canLeave();
    },

    beforeRouteUpdate(this: { canLeave(): boolean }, to: RouteLocationNormalized, from: RouteLocationNormalized) {
        return to.params.id === from.params.id || this.canLeave();
    },

    beforeUnmount() {
        this.loadSequence += 1;
    },

    methods: {
        canLeave(): boolean {
            return !this.isSaving && (!this.isDirty || window.confirm(this.$t('sw-approval-flow.designer.discard')));
        },

        async loadDesigner(): Promise<void> {
            const sequence = ++this.loadSequence;
            this.isLoading = true;
            this.loadError = false;
            this.saveError = null;
            try {
                const [catalog, draft] = await Promise.all([
                    this.approvalFlowDesignApiService.catalog(),
                    this.definitionId ? this.loadDraft(this.definitionId) : null,
                ]);
                if (sequence !== this.loadSequence) {
                    return;
                }

                this.catalog = catalog;
                this.revision = draft?.revision ?? null;
                const graph = draft ? (JSON.parse(draft.graphJson) as ApprovalDesignGraph) : this.createGraph(catalog);
                this.load(
                    this.approvalDesignerService.createSession(graph, { canEdit: () => !this.readOnly }),
                    draft !== null,
                );
                const defaultType =
                    this.nodeOptions.find((option) => option.value === 'core:approval@1.0.0') ?? this.nodeOptions[0];
                this.selectedType = defaultType?.value ?? null;
                if (!draft) {
                    this.selectNode(graph.nodes.find((node) => node.type === 'core:approval')?.id ?? null);
                }
            } catch {
                if (sequence === this.loadSequence) {
                    this.loadError = true;
                }
            } finally {
                if (sequence === this.loadSequence) {
                    this.isLoading = false;
                }
            }
        },

        async loadDraft(definitionId: string) {
            const repository = this.repositoryFactory.create('approval_flow_definition');
            const criteria = new Shopwell.Data.Criteria();
            criteria.addAssociation('draft');
            criteria.addIncludes({
                approval_flow_definition: ['id', 'draft'],
                approval_flow_draft: ['definitionId', 'revision', 'graphJson'],
            });
            const definition = await repository.get(
                definitionId as EntityKey<'approval_flow_definition'>,
                Shopwell.Context.api,
                criteria,
            );
            if (!definition?.draft) {
                throw new Error('The definition has no draft.');
            }

            return definition.draft;
        },

        createGraph(catalog: ApprovalDesignCatalog): ApprovalDesignGraph {
            const business = catalog.businessTypes[0];
            const startType = catalog.nodeTypes.find((type) => type.key === 'core:start@1.0.0');
            const endType = catalog.nodeTypes.find((type) => type.key === 'core:end@1.0.0');
            const approvalType = catalog.nodeTypes.find((type) => type.key === 'core:approval@1.0.0');
            if (!startType || !endType || !approvalType) {
                throw new Error('The approval design catalog is missing a built-in node type.');
            }

            const start = this.approvalDesignerService.createNode(startType, { x: 0, y: 0 });
            start.config = business ? { businessType: business.type } : {};
            const approval = this.approvalDesignerService.createNode(approvalType, { x: 0, y: APPROVAL_NODE_STEP });
            const end = this.approvalDesignerService.createNode(endType, { x: 0, y: APPROVAL_NODE_STEP * 2 });
            const nodes = [start, approval, end];

            return {
                id: Shopwell.Utils.createId(),
                schemaVersion: '1.0.0',
                meta: { name: '', editingMode: 'oa', formSchemaRef: business?.formSchemaId ?? '' },
                nodes,
                connections: nodes.slice(1).map((target, index) => ({
                    id: Shopwell.Utils.createId(),
                    source: nodes[index].id,
                    sourcePort: 'out',
                    target: target.id,
                    targetPort: 'in',
                    condition: { kind: 'always' },
                    extensions: {},
                })),
                layout: { algorithm: 'core:layered', nodes: [] },
                extensions: {},
            };
        },

        selectNode(id: string | null): void {
            this.editorSession?.selectNode(id);
        },

        selectConnection(id: string): void {
            this.editorSession?.selectConnection(id);
        },

        closeSettings(): void {
            this.showDraftSettings = false;
            this.selectNode(null);
        },

        addNode(): void {
            const descriptor = this.selectedTypeDescriptor;
            if (!descriptor || !this.graph) {
                return;
            }

            const terminal = this.graph.nodes.find((node) => node.type === 'core:end');
            const incoming = this.graph.connections.filter((edge) => edge.target === terminal?.id);
            const connection = this.selectedConnection ?? (incoming.length === 1 ? incoming[0].id : null);
            if (connection && this.canInsertNode) {
                this.insertNode(connection);
                return;
            }

            const interiorNodes = this.graph.nodes.filter((node) => node.type !== 'core:start' && node.type !== 'core:end');
            const lastNode = this.selectedNode ?? interiorNodes.at(-1);
            const position = {
                x: (lastNode?.position.x ?? 0) + APPROVAL_NODE_STEP * 2,
                y: lastNode?.position.y ?? 0,
            };
            const node = this.approvalDesignerService.createNode(descriptor, position);
            if (this.execute('approval:add-node', node)) {
                this.selectNode(node.id);
            }
        },

        insertNode(connectionId: string): void {
            if (!this.selectedTypeDescriptor || !this.canInsertNode) {
                return;
            }

            const node = this.approvalDesignerService.createNode(this.selectedTypeDescriptor, { x: 0, y: 0 });
            if (this.execute('approval:insert-node', { connectionId, node, nextConnectionId: Shopwell.Utils.createId() })) {
                this.selectNode(node.id);
            }
        },

        moveNode(id: string, position: { x: number; y: number }): void {
            this.execute('approval:move-node', { id, position });
        },

        connect(endpoints: ApprovalConnectionEndpoints): void {
            this.execute('approval:connect', {
                id: Shopwell.Utils.createId(),
                ...endpoints,
                condition: { kind: 'always' },
                extensions: {},
            });
        },

        removeSelection(): void {
            if (this.selectedNodeId) {
                this.execute('approval:remove-node', this.selectedNodeId);
            } else if (this.selectedConnection) {
                this.execute('approval:remove-connection', this.selectedConnection);
            }
        },

        updateConfiguration(config: Record<string, ApprovalJsonValue>): void {
            if (this.selectedNodeId) {
                this.execute('approval:set-configuration', { id: this.selectedNodeId, config });
            }
        },

        selectBusiness(type: string): void {
            const business = this.catalog?.businessTypes.find((item) => item.type === type);
            if (business) {
                this.execute('approval:set-business', { type, formSchemaId: business.formSchemaId });
            }
        },

        setName(name: string): void {
            if (!this.definitionId) {
                this.execute('approval:set-name', name);
            }
        },

        async saveDraft(): Promise<void> {
            if (!this.graph || this.readOnly || !this.isDirty || !this.graph.meta.name.trim()) {
                return;
            }

            const sequence = this.loadSequence;
            const graphJson = JSON.stringify(this.graph);
            this.isSaving = true;
            this.saveError = null;
            let createdId: string | undefined;
            try {
                const saved =
                    this.definitionId && this.revision !== null
                        ? await this.approvalFlowDesignApiService.saveDraft(this.definitionId, this.revision, graphJson)
                        : await this.approvalFlowDesignApiService.createDraft(this.graph.meta.name.trim(), graphJson);
                if (sequence !== this.loadSequence) {
                    return;
                }

                this.onSaved(saved, graphJson);
                if (!this.definitionId) {
                    createdId = saved.definitionId;
                }
            } catch (error) {
                if (sequence === this.loadSequence) {
                    this.saveError = (error as HttpError).response?.status === 409 ? 'conflict' : 'saveError';
                }
            } finally {
                this.isSaving = false;
            }

            if (createdId) {
                await this.$router.replace({ name: 'sw.approval.flow.designer', params: { id: createdId } });
            }
        },

        onSaved(saved: ApprovalDesignDraft, graphJson: string): void {
            this.acceptSaved(graphJson);
            this.revision = saved.revision;
            this.diagnostics = saved.diagnostics;
            this.showDiagnostics = saved.diagnostics.length > 0;
        },

        undo(): void {
            this.editorSession?.undo();
        },

        redo(): void {
            this.editorSession?.redo();
        },

        fit(): void {
            (this.$refs.canvas as DesignerCanvas | undefined)?.fit();
        },

        zoom(delta: number): void {
            (this.$refs.canvas as DesignerCanvas | undefined)?.zoom(delta);
        },
    },
});
