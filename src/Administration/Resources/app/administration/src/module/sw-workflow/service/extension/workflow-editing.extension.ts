/** @sw-package framework */
import type { WorkflowTemplateContext } from 'src/core/workflow/workflow-studio.types';
import type { WorkflowExtension } from 'src/core/workflow/workflow-extension.types';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type WorkflowNodeEditingService from '../workflow-node-editing.service';
import type WorkflowTemplateService from '../workflow-template.service';

type EditingOptions = {
    editing: WorkflowNodeEditingService;
    templates: WorkflowTemplateService;
    nodeTypes: () => WorkflowNodeTypeSpecification[];
    templateContext: WorkflowTemplateContext;
};

function record(payload: unknown): Record<string, unknown> {
    return payload && typeof payload === 'object' && !Array.isArray(payload) ? (payload as Record<string, unknown>) : {};
}

function nodeFor(document: WorkflowDocument, payload: unknown) {
    return document.nodes.find((node) => node.id === record(payload).nodeId);
}

function typeFor(types: WorkflowNodeTypeSpecification[], payload: unknown) {
    return types.find((type) => type.name === record(payload).type && type.version === record(payload).version);
}

/** Built-in edits use the same transactional command contract as extensions. @private */
export default function createWorkflowEditingExtension(options: EditingOptions): WorkflowExtension {
    return {
        name: 'core:editing',
        setup(context) {
            context.commands.register({
                name: 'core:insert-node',
                canExecute: ({ document }, payload) => {
                    const type = typeFor(options.nodeTypes(), payload);
                    const { connectionId, id } = record(payload);
                    return (
                        !!type &&
                        typeof connectionId === 'string' &&
                        typeof id === 'string' &&
                        id.length > 0 &&
                        !document.nodes.some((node) => node.id === id) &&
                        options.editing.get(type.name, type.version).canInsert(document, type, connectionId)
                    );
                },
                execute(document, payload) {
                    const type = typeFor(options.nodeTypes(), payload);

                    if (!type) {
                        throw new Error('Unknown workflow node type');
                    }

                    const { connectionId, id } = record(payload);
                    return options.editing
                        .get(type.name, type.version)
                        .insert(document, type, String(connectionId), String(id));
                },
            });
            context.commands.register({
                name: 'core:update-node',
                canExecute: ({ document }, payload) =>
                    !!nodeFor(document, payload) &&
                    !!record(payload).patch &&
                    typeof record(payload).patch === 'object' &&
                    !Array.isArray(record(payload).patch),
                execute(document, payload) {
                    const node = nodeFor(document, payload);

                    if (!node) {
                        throw new Error('Unknown workflow node');
                    }

                    const patch = record(payload).patch as Record<string, unknown>;
                    const strategy = options.editing.get(node.type, node.typeVersion);

                    if (strategy.updateConfig) {
                        return strategy.updateConfig(document, node.id, patch);
                    }

                    node.config = { ...(Array.isArray(node.config) ? {} : node.config), ...patch };
                    return document;
                },
            });
            context.commands.register({
                name: 'core:remove-node',
                canExecute: ({ document }, payload) => {
                    const node = nodeFor(document, payload);
                    return !!node && options.editing.get(node.type, node.typeVersion).canRemove(document, node.id);
                },
                execute(document, payload) {
                    const node = nodeFor(document, payload);

                    if (!node) {
                        throw new Error('Unknown workflow node');
                    }

                    return options.editing.get(node.type, node.typeVersion).remove(document, node.id);
                },
            });
            context.commands.register({
                name: 'core:load-template',
                canExecute: (_state, payload) =>
                    options.templates.get(String(record(payload).name))?.available(options.nodeTypes()) ?? false,
                execute(_document, payload) {
                    const preset = options.templates.get(String(record(payload).name));

                    if (!preset) {
                        throw new Error('Unknown workflow template');
                    }

                    return preset.create(options.nodeTypes(), options.templateContext);
                },
            });
        },
    };
}
