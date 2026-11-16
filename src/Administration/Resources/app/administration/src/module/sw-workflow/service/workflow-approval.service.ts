/** @sw-package framework */
import { WORKFLOW_ASSIGNEE_SOURCES, WORKFLOW_APPROVAL_MODES } from 'src/core/workflow/workflow-node.constants';
import { shallowReactive } from 'vue';
import { registerWorkflowContribution } from './workflow-registration';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';

/** @public */
export interface WorkflowAssigneeSource {
    name: string;
    labelKey: string;
    settingsComponent: string;
    summaryComponent: string;
    /** Mode names address version 1; later versions use name@version. */
    supportedModes?: string[];
}
/** @public */
export interface WorkflowApprovalMode {
    name: string;
    version?: number;
    labelKey: string;
    descriptionKey: string;
    settingsComponent?: string;
}
/** @public */
export interface WorkflowApprovalSettings {
    assignee: { source: string; config: Record<string, unknown> };
    approval: { mode: string; version?: number; config: Record<string, unknown> };
}

function object(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}

/** Design-time approval extensions; execution is a separate capability. @public */
export default class WorkflowApprovalService {
    private readonly assigneeSources = shallowReactive(new Map<string, WorkflowAssigneeSource>());
    private readonly approvalModes = shallowReactive(new Map<string, WorkflowApprovalMode>());
    private readonly supportedModes = shallowReactive(
        new Map<string, { source: WorkflowAssigneeSource; mode: WorkflowApprovalMode }>(),
    );

    registerSource(source: WorkflowAssigneeSource): () => void {
        const definition = { ...source, ...(source.supportedModes ? { supportedModes: [...source.supportedModes] } : {}) };
        const unregister = registerWorkflowContribution(this.assigneeSources, definition.name, definition);
        return () => {
            unregister();
            for (const [key, support] of this.supportedModes) {
                if (support.source === definition) {
                    this.supportedModes.delete(key);
                }
            }
        };
    }

    registerMode(mode: WorkflowApprovalMode): () => void {
        const definition = { ...mode };
        const version = mode.version ?? 1;

        if (!Number.isInteger(version) || version < 1) {
            throw new Error('Approval mode versions must be positive integers.');
        }

        const unregister = registerWorkflowContribution(
            this.approvalModes,
            this.modeKey(definition.name, version),
            definition,
        );
        return () => {
            unregister();
            for (const [key, support] of this.supportedModes) {
                if (support.mode === definition) {
                    this.supportedModes.delete(key);
                }
            }
        };
    }

    registerSupportedMode(sourceName: string, modeName: string, version = 1): () => void {
        const source = this.assigneeSources.get(sourceName);
        const mode = this.approvalModes.get(this.modeKey(modeName, version));

        if (!source || !mode || !source.supportedModes) {
            throw new Error('Mode opt-ins require a registered mode and a source with explicit supported modes.');
        }

        if (source.supportedModes.includes(this.modeReference(modeName, version))) {
            throw new Error(`Duplicate workflow mode support: ${sourceName}, ${modeName}`);
        }

        return registerWorkflowContribution(this.supportedModes, JSON.stringify([sourceName, modeName, version]), {
            source,
            mode,
        });
    }

    sources(): WorkflowAssigneeSource[] {
        return [...this.assigneeSources.keys()].flatMap((name) => {
            const source = this.getSource(name);
            return source ? [source] : [];
        });
    }

    modes(): WorkflowApprovalMode[] {
        return [...this.approvalModes.values()].map((mode) => ({ ...mode }));
    }

    getSource(name: string): WorkflowAssigneeSource | undefined {
        const source = this.assigneeSources.get(name);

        if (!source) {
            return undefined;
        }

        if (!source.supportedModes) {
            return { ...source };
        }

        const additionalModes = [...this.supportedModes.values()]
            .filter((support) => support.source === source)
            .map((support) => this.modeReference(support.mode.name, support.mode.version ?? 1));
        return { ...source, supportedModes: [...source.supportedModes, ...additionalModes] };
    }

    getMode(name: string, version = 1): WorkflowApprovalMode | undefined {
        const mode = this.approvalModes.get(this.modeKey(name, version));
        return mode ? { ...mode } : undefined;
    }

    supportsMode(sourceName: string, modeName: string, version = 1): boolean {
        const source = this.getSource(sourceName);

        if (!source) {
            return false;
        }

        return !source.supportedModes || source.supportedModes.includes(this.modeReference(modeName, version));
    }

    private modeReference(name: string, version: number): string {
        return version === 1 ? name : this.modeKey(name, version);
    }

    private modeKey(name: string, version: number): string {
        return `${name}@${version}`;
    }

    read(node: WorkflowNode): WorkflowApprovalSettings {
        const config = object(node.config);
        const assignee = object(config.assignee);
        const approval = object(config.approval);
        let assigneeSource: string = WORKFLOW_ASSIGNEE_SOURCES.USER;

        if ('assignee' in config) {
            assigneeSource = typeof assignee.source === 'string' ? assignee.source : '';
        }

        let approvalMode: string = WORKFLOW_APPROVAL_MODES.SINGLE;

        if ('approval' in config) {
            approvalMode = typeof approval.mode === 'string' ? approval.mode : '';
        }

        return {
            assignee: { source: assigneeSource, config: object(assignee.config) },
            approval: {
                mode: approvalMode,
                ...('version' in approval ? { version: typeof approval.version === 'number' ? approval.version : 0 } : {}),
                config: object(approval.config),
            },
        };
    }
}
