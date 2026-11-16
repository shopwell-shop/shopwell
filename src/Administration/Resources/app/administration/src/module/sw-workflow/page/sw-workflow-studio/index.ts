/** @sw-package framework */
import axios from 'axios';
import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import type { WorkflowDraft } from 'src/core/service/api/workflow.api.service';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';
import type { WorkflowDocument, WorkflowNodeTypeSpecification, WorkflowViolation } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-studio.html.twig';
import './sw-workflow-studio.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowService', 'workflowTemplateService', 'acl'],
    props: { workflowId: { type: String, default: null } },
    data(): {
        draftId: string;
        name: string;
        revision: number;
        savedSnapshot: string | null;
        hasPendingSettings: boolean;
        isSaving: boolean;
        saveError: 'conflict' | 'failed' | null;
        pendingNavigation: (() => void) | null;
        loadRevision: number;
        allowNavigation: boolean;
        unloadHandler: ((event: BeforeUnloadEvent) => void) | null;
        document: WorkflowDocument;
        nodeTypes: WorkflowNodeTypeSpecification[];
        violations: WorkflowViolation[];
        isLoading: boolean;
        isDiagnosing: boolean;
        hasDiagnosed: boolean;
        hasError: boolean;
        diagnosticRevision: number;
        isInitialized: boolean;
        diagnosticError: boolean;
        diagnosticTimer: ReturnType<typeof setTimeout> | null;
    } {
        return {
            draftId: this.workflowId ?? Shopwell.Utils.createId(),
            name: '',
            revision: 0,
            savedSnapshot: null,
            hasPendingSettings: false,
            isSaving: false,
            saveError: null,
            pendingNavigation: null,
            loadRevision: 0,
            allowNavigation: false,
            unloadHandler: null,
            document: createWorkflowDraft(),
            nodeTypes: [],
            violations: [],
            isLoading: true,
            isDiagnosing: false,
            hasDiagnosed: false,
            hasError: false,
            diagnosticRevision: 0,
            isInitialized: false,
            diagnosticError: false,
            diagnosticTimer: null,
        };
    },
    computed: {
        hasChanges(): boolean {
            return (
                this.isInitialized &&
                (this.hasPendingSettings ||
                    this.revision === 0 ||
                    this.savedSnapshot !== JSON.stringify({ name: this.name, document: this.document }))
            );
        },
        allowEdit(): boolean {
            return (
                !this.isSaving &&
                !this.isLoading &&
                !this.hasError &&
                this.acl.can(this.revision ? 'workflow.editor' : 'workflow.creator')
            );
        },
        canSave(): boolean {
            return (
                this.allowEdit &&
                !this.hasPendingSettings &&
                this.hasChanges &&
                this.name.trim().length > 0 &&
                this.name.length <= 255
            );
        },
    },
    watch: {
        workflowId(id: string | null) {
            if (id !== this.draftId) {
                this.draftId = id ?? Shopwell.Utils.createId();
                this.isInitialized = false;
                void this.load();
            }
        },
    },
    mounted() {
        this.unloadHandler = (event: BeforeUnloadEvent) => this.beforeUnload(event);
        window.addEventListener('beforeunload', this.unloadHandler);
    },
    beforeRouteLeave(to: RouteLocationNormalized, _from: RouteLocationNormalized, next: NavigationGuardNext) {
        this.guardNavigation(to.fullPath, next);
    },
    beforeRouteUpdate(to: RouteLocationNormalized, _from: RouteLocationNormalized, next: NavigationGuardNext) {
        this.guardNavigation(to.fullPath, next);
    },
    created() {
        void this.load();
    },
    beforeUnmount() {
        if (this.unloadHandler) window.removeEventListener('beforeunload', this.unloadHandler);
        this.loadRevision += 1;
        this.diagnosticRevision += 1;
        if (this.diagnosticTimer) clearTimeout(this.diagnosticTimer);
    },
    methods: {
        beforeUnload(event: BeforeUnloadEvent): void {
            if (this.hasChanges || this.isSaving) {
                event.preventDefault();
                event.returnValue = '';
            }
        },
        guardNavigation(path: string, next: NavigationGuardNext): void {
            if (this.isSaving) {
                next(false);
                return;
            }
            if (this.hasChanges && !this.allowNavigation) {
                next(false);
                this.pendingNavigation = () => {
                    this.allowNavigation = true;
                    void this.$router.push(path).finally(() => {
                        this.allowNavigation = false;
                    });
                };
                return;
            }
            next();
        },
        confirmLeave(): void {
            const proceed = this.pendingNavigation;
            this.pendingNavigation = null;
            proceed?.();
        },
        reload(): void {
            if (this.isSaving) return;
            this.pendingNavigation = () => void this.load(true);
        },
        applyDraft(draft: WorkflowDraft): void {
            this.hasPendingSettings = false;
            this.draftId = draft.id;
            this.name = draft.name;
            this.revision = draft.revision;
            this.document = draft.document;
            this.savedSnapshot = JSON.stringify({ name: this.name, document: this.document });
        },
        async load(force = false): Promise<void> {
            const loadRevision = ++this.loadRevision;
            this.diagnosticRevision += 1;
            if (this.diagnosticTimer) clearTimeout(this.diagnosticTimer);
            this.isLoading = true;
            this.hasError = false;
            try {
                const nodeTypes = await this.workflowService.getNodeTypes();
                if (loadRevision !== this.loadRevision) return;
                this.nodeTypes = nodeTypes;
                if (this.workflowId || force) {
                    const draft = await this.workflowService.getDraft(this.draftId);
                    if (loadRevision !== this.loadRevision) return;
                    this.applyDraft(draft);
                } else if (!this.isInitialized) {
                    const preset = this.workflowTemplateService.get('core:basic');

                    if (!preset) {
                        throw new Error('The basic workflow template is not registered.');
                    }

                    this.document = preset.create(this.nodeTypes);
                    this.name = this.$t('sw-workflow.persistence.defaultName');
                    this.revision = 0;
                    this.savedSnapshot = null;
                }
                this.isInitialized = true;
                this.saveError = null;
                void this.diagnose(this.document);
                if (force && !this.workflowId)
                    void this.$router.replace({ name: 'sw.workflow.detail', params: { id: this.draftId } });
            } catch {
                if (loadRevision === this.loadRevision) this.hasError = true;
            } finally {
                if (loadRevision === this.loadRevision) this.isLoading = false;
            }
        },
        async save(): Promise<void> {
            if (!this.canSave) return;
            this.isSaving = true;
            this.saveError = null;
            const name = this.name.trim();
            const document = JSON.parse(JSON.stringify(this.document)) as WorkflowDocument;
            try {
                const draft = this.revision
                    ? await this.workflowService.updateDraft(this.draftId, name, document, this.revision)
                    : await this.workflowService.createDraft(this.draftId, name, document);
                this.applyDraft(draft);
            } catch (error) {
                this.saveError = axios.isAxiosError(error) && error.response?.status === 409 ? 'conflict' : 'failed';
            } finally {
                this.isSaving = false;
            }
            if (!this.saveError && !this.workflowId)
                void this.$router.replace({ name: 'sw.workflow.detail', params: { id: this.draftId } });
        },
        onDocumentChange(document: WorkflowDocument): void {
            this.document = document;
            this.diagnosticRevision += 1;
            this.hasDiagnosed = false;
            this.violations = [];
            this.diagnosticError = false;
            this.isDiagnosing = true;
            if (this.diagnosticTimer) clearTimeout(this.diagnosticTimer);
            this.diagnosticTimer = setTimeout(() => void this.diagnose(this.document), 500);
        },
        async diagnose(document: WorkflowDocument): Promise<void> {
            if (this.diagnosticTimer) clearTimeout(this.diagnosticTimer);
            this.diagnosticTimer = null;
            const revision = ++this.diagnosticRevision;
            this.isDiagnosing = true;
            this.diagnosticError = false;
            try {
                const violations = await this.workflowService.diagnose(document);
                if (revision === this.diagnosticRevision) {
                    this.violations = violations;
                    this.hasDiagnosed = true;
                }
            } catch {
                if (revision === this.diagnosticRevision) this.diagnosticError = true;
            } finally {
                if (revision === this.diagnosticRevision) this.isDiagnosing = false;
            }
        },
    },
});
