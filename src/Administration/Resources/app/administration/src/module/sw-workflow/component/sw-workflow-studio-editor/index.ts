/** @sw-package framework */
import { markRaw } from 'vue';
import type { PropType } from 'vue';
import type { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';
import type { WorkflowEditorState, WorkflowUiTarget } from 'src/core/workflow/workflow-extension.types';
import type {
    WorkflowDocument,
    WorkflowNode,
    WorkflowNodeTypeSpecification,
    WorkflowViolation,
} from 'src/core/workflow/workflow.types';
import createWorkflowEditingExtension from '../../service/extension/workflow-editing.extension';
import template from './sw-workflow-studio-editor.html.twig';
import './sw-workflow-studio-editor.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: [
        'workflowNodeEditingService',
        'workflowTemplateService',
        'workflowExtensionService',
        'workflowStudioService',
        'workflowNodePresentationService',
    ],
    props: {
        layout: { type: String, default: '' },
        document: { type: Object as PropType<WorkflowDocument>, required: true },
        nodeTypes: { type: Array as PropType<WorkflowNodeTypeSpecification[]>, required: true },
        violations: { type: Array as PropType<WorkflowViolation[]>, default: () => [] },
        isDiagnosing: { type: Boolean, default: false },
        hasDiagnosed: { type: Boolean, default: false },
        diagnosticError: { type: Boolean, default: false },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update:document', 'diagnose', 'extension-error'],
    data(): {
        session: CanvasEditorSession<WorkflowDocument> | null;
        state: WorkflowEditorState;
        editingNodeId: string | null;
        pickerOpen: boolean;
        templatesOpen: boolean;
        nodeDraft: WorkflowDocument | null;
        pendingPatch: Record<string, unknown>;
        extensionError: boolean;
        menu: { position: 'node-menu' | 'edge-menu'; target: WorkflowUiTarget } | null;
    } {
        return {
            session: null,
            state: {
                document: this.document,
                selection: { nodeId: null, connectionId: null },
                canEdit: this.allowEdit,
                canUndo: false,
                canRedo: false,
            },
            editingNodeId: null,
            pickerOpen: false,
            templatesOpen: false,
            nodeDraft: null,
            pendingPatch: {},
            extensionError: false,
            menu: null,
        };
    },
    computed: {
        panelTarget(): WorkflowUiTarget {
            return {
                nodeId: this.state.selection.nodeId ?? undefined,
                connectionId: this.state.selection.connectionId ?? undefined,
            };
        },
        hasExtensionPanel(): boolean {
            return (this.session?.components('panel', this.panelTarget).length ?? 0) > 0;
        },
        selectedNode(): WorkflowNode | null {
            return this.state.document.nodes.find((node) => node.id === this.state.selection.nodeId) ?? null;
        },
        draftNode(): WorkflowNode | null {
            return this.nodeDraft?.nodes.find((node) => node.id === this.editingNodeId) ?? null;
        },
        canRemove(): boolean {
            return this.session?.canExecute('core:remove-node', { nodeId: this.state.selection.nodeId }) ?? false;
        },
        typeLabels(): Record<string, string> {
            return {
                ...Object.fromEntries(this.nodeTypes.map((type) => [type.name, this.typeLabel(type.name, type.version)])),
                fallback: this.$t('sw-workflow.outcomes.fallback'),
            };
        },
        extensionDiagnostics(): { violations: WorkflowViolation[]; error: unknown } {
            try {
                return { violations: this.workflowStudioService.diagnose(this.state.document, this.nodeTypes), error: null };
            } catch (error) {
                return { violations: [], error };
            }
        },
        allViolations(): WorkflowViolation[] {
            return [...this.violations, ...this.extensionDiagnostics.violations];
        },
        invalidIds(): string[] {
            return this.allViolations.flatMap((violation) => (violation.nodeId ? [violation.nodeId] : []));
        },
        downloadUrl(): string {
            return `data:application/json;charset=utf-8,${encodeURIComponent(JSON.stringify(this.state.document, null, 2))}`;
        },
    },
    watch: {
        extensionDiagnostics: {
            immediate: true,
            handler(value: { error: unknown }) {
                if (value.error) {
                    this.extensionError = true;
                    this.$emit('extension-error', value.error);
                }
            },
        },
        document(value: WorkflowDocument) {
            if (JSON.stringify(value) !== JSON.stringify(this.state.document)) {
                this.initializeSession(value);
            }
        },
        allowEdit() {
            if (this.session) {
                this.state = this.session.getState();
            }
        },
    },
    created() {
        this.initializeSession(this.document);
    },
    beforeUnmount() {
        this.session?.dispose();
    },
    methods: {
        initializeSession(document: WorkflowDocument): void {
            this.session?.dispose();
            this.closeEditor();
            this.pickerOpen = false;
            this.templatesOpen = false;
            this.menu = null;
            const session = this.workflowExtensionService.createSession(
                document,
                {
                    canEdit: () => this.allowEdit,
                    onError: (error) => {
                        this.extensionError = true;
                        this.$emit('extension-error', error);
                    },
                },
                [
                    createWorkflowEditingExtension({
                        editing: this.workflowNodeEditingService,
                        templates: this.workflowTemplateService,
                        nodeTypes: () => this.nodeTypes,
                    }),
                ],
            );
            this.session = markRaw(session);
            this.state = session.getState();
            session.subscribe('session.changed', () => {
                this.state = session.getState();
            });
            session.subscribe('selection.changed', (value) => {
                if (value.nodeId !== this.editingNodeId) {
                    this.closeEditor();
                }
            });
            session.subscribe('document.changed', (value) => {
                this.closeEditor();
                this.pickerOpen = false;
                this.templatesOpen = false;
                this.menu = null;
                this.$emit('update:document', value);
            });
        },
        typeLabel(type: string, version = 1): string {
            const key =
                this.workflowNodePresentationService.get(type, version).labelKey ??
                `sw-workflow.types.${type.replace(':', '.')}`;
            return this.$te(key) ? this.$t(key) : type;
        },
        selectNode(id: string | null): void {
            if (!this.session?.interact('node.click', id ? { nodeId: id } : {})) {
                return;
            }
            this.session.selectNode(id);
            this.menu = null;
            this.editingNodeId = id;
            this.nodeDraft = id ? this.session.getState().document : null;
            this.pendingPatch = {};
        },
        selectConnection(id: string): void {
            if (!this.session?.interact('connection.click', { connectionId: id })) {
                return;
            }
            this.session.selectConnection(id);
            this.menu = null;
        },
        openPicker(id: string): void {
            if (!this.allowEdit || !this.session?.interact('connection.insert', { connectionId: id })) {
                return;
            }
            this.session.selectConnection(id);
            this.pickerOpen = true;
        },
        openMenu(position: 'node-menu' | 'edge-menu', target: WorkflowUiTarget): void {
            const event = position === 'node-menu' ? 'node.contextmenu' : 'connection.contextmenu';
            if (!this.session?.interact(event, target)) {
                return;
            }
            if (target.nodeId) {
                this.session.selectNode(target.nodeId);
            } else if (target.connectionId) {
                this.session.selectConnection(target.connectionId);
            }
            this.menu = this.session.actions(position, target).length ? { position, target } : null;
        },
        canvasClick(): void {
            if (this.session?.interact('canvas.click', {})) {
                this.session.selectNode(null);
                this.menu = null;
            }
        },
        canvasContextmenu(position: { x: number; y: number }): void {
            this.session?.interact('canvas.contextmenu', { position });
        },
        insert(type: WorkflowNodeTypeSpecification): void {
            const id = Shopwell.Utils.createId();
            if (
                this.session?.execute('core:insert-node', {
                    type: type.name,
                    version: type.version,
                    id,
                    connectionId: this.state.selection.connectionId,
                })
            ) {
                this.selectNode(id);
            }
        },
        stageSettings(patch: Record<string, unknown>): void {
            const node = this.draftNode;
            if (!this.allowEdit || !node || !this.nodeDraft) {
                return;
            }
            const strategy = this.workflowNodeEditingService.get(node.type, node.typeVersion);
            if (strategy.updateConfig) {
                this.nodeDraft = strategy.updateConfig(this.nodeDraft, node.id, patch);
            } else {
                const document = JSON.parse(JSON.stringify(this.nodeDraft)) as WorkflowDocument;
                const draft = document.nodes.find((item) => item.id === node.id);
                if (!draft) {
                    return;
                }
                draft.config = { ...(Array.isArray(draft.config) ? {} : draft.config), ...patch };
                this.nodeDraft = document;
            }
            this.pendingPatch = { ...this.pendingPatch, ...patch };
        },
        saveSettings(): void {
            if (!this.allowEdit) {
                return;
            }
            if (
                Object.keys(this.pendingPatch).length === 0 ||
                this.session?.execute('core:update-node', { nodeId: this.editingNodeId, patch: this.pendingPatch })
            ) {
                this.closeEditor();
            }
        },
        closeEditor(): void {
            this.nodeDraft = null;
            this.editingNodeId = null;
            this.pendingPatch = {};
        },
        remove(): void {
            this.session?.execute('core:remove-node', { nodeId: this.state.selection.nodeId });
        },
        undo(): void {
            this.session?.undo();
        },
        redo(): void {
            this.session?.redo();
        },
        loadTemplate(name: string): void {
            this.session?.execute('core:load-template', { name });
        },
        violationMessage(violation: WorkflowViolation): string {
            const key = violation.messageKey ?? `sw-workflow.diagnostics.${violation.code}`;
            return this.$te(key) ? this.$t(key) : this.$t('sw-workflow.diagnostics.unknown');
        },
    },
});
