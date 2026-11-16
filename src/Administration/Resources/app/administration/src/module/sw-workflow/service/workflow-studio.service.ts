/** @sw-package framework */
import { shallowReactive } from 'vue';
import type { WorkflowStudioDiagnostic, WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import type { WorkflowDocument, WorkflowNodeTypeSpecification, WorkflowViolation } from 'src/core/workflow/workflow.types';
import type WorkflowExtensionService from './workflow-extension.service';
import type WorkflowNodePresentationService from './workflow-node-presentation.service';
import type WorkflowNodeEditingService from './workflow-node-editing.service';
import type WorkflowTemplateService from './workflow-template.service';
import type WorkflowLayoutService from './workflow-layout.service';
import type WorkflowFieldService from './workflow-field.service';
import { registerWorkflowContribution } from './workflow-registration';

type StudioServices = {
    extensions: WorkflowExtensionService;
    presentations: WorkflowNodePresentationService;
    editing: WorkflowNodeEditingService;
    templates: WorkflowTemplateService;
    layouts: WorkflowLayoutService;
    fields: WorkflowFieldService;
};

/**
 * Installs independently owned editor capabilities without exposing canvas internals.
 * Node executability and publication validation remain authoritative on the server.
 * @public
 */
export default class WorkflowStudioService {
    private readonly extensions = new Map<string, WorkflowStudioExtension>();

    private readonly diagnostics = shallowReactive(new Map<string, WorkflowStudioDiagnostic>());

    /** @internal */
    constructor(private readonly services: StudioServices) {}

    register(extension: WorkflowStudioExtension): () => void {
        const disposers: (() => void)[] = [];
        let active = true;
        const unregister = () => {
            if (!active) {
                return;
            }

            active = false;

            for (let index = disposers.length - 1; index >= 0; index -= 1) {
                disposers[index]();
            }
        };

        try {
            disposers.push(registerWorkflowContribution(this.extensions, extension.name, extension));

            for (const node of extension.nodes ?? []) {
                disposers.push(this.services.presentations.register(node.type, node.version, node.presentation));

                if (node.editing) {
                    disposers.push(this.services.editing.register(node.type, node.version, node.editing));
                }
            }

            for (const template of extension.templates ?? []) {
                disposers.push(this.services.templates.register(template));
            }

            this.registerLayouts(extension.layouts ?? [], disposers);

            for (const provider of extension.fieldProviders ?? []) {
                disposers.push(this.services.fields.register(provider.name, provider.provide));
            }

            for (const diagnostic of extension.diagnostics ?? []) {
                disposers.push(registerWorkflowContribution(this.diagnostics, diagnostic.name, { ...diagnostic }));
            }

            if (extension.editor) {
                disposers.push(this.services.extensions.register(extension.editor));
            }
        } catch (error) {
            unregister();
            throw error;
        }

        return unregister;
    }

    private registerLayouts(layouts: NonNullable<WorkflowStudioExtension['layouts']>, disposers: (() => void)[]): void {
        for (const layout of layouts) {
            disposers.push(this.services.layouts.register(layout.name, layout.layout, layout.route));

            for (const policy of layout.policies ?? []) {
                disposers.push(this.services.layouts.registerPolicyLayout(policy, layout.name));
            }
        }
    }

    diagnose(document: WorkflowDocument, types: WorkflowNodeTypeSpecification[]): WorkflowViolation[] {
        const violations: WorkflowViolation[] = [];

        const serializedDocument = JSON.stringify(document);
        const serializedTypes = JSON.stringify(types);

        for (const diagnostic of this.diagnostics.values()) {
            // Each extension receives its own snapshot, so one check cannot change the next check's inputs.
            const documentSnapshot = JSON.parse(serializedDocument) as WorkflowDocument;
            const typeSnapshot = JSON.parse(serializedTypes) as WorkflowNodeTypeSpecification[];
            violations.push(...diagnostic.check(documentSnapshot, typeSnapshot));
        }

        return violations;
    }
}
