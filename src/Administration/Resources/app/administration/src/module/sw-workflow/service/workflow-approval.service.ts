/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';

/** @public */
export interface WorkflowAssigneeSource {
    name: string;
    labelKey: string;
    settingsComponent: string;
    summaryComponent: string;
    supportedModes?: string[];
}
/** @public */
export interface WorkflowApprovalMode {
    name: string;
    labelKey: string;
    descriptionKey: string;
    settingsComponent?: string;
}
/** @public */
export interface WorkflowApprovalSettings {
    assignee: { source: string; config: Record<string, unknown> };
    approval: { mode: string; config: Record<string, unknown> };
}

function object(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}

/** Design-time approval extensions; execution is a separate capability. @public */
export default class WorkflowApprovalService {
    private readonly assigneeSources = new Map<string, WorkflowAssigneeSource>();
    private readonly approvalModes = new Map<string, WorkflowApprovalMode>();
    registerSource(source: WorkflowAssigneeSource): void {
        this.assigneeSources.set(source.name, source);
    }
    registerMode(mode: WorkflowApprovalMode): void {
        this.approvalModes.set(mode.name, mode);
    }
    sources(): WorkflowAssigneeSource[] {
        return [...this.assigneeSources.values()];
    }
    modes(): WorkflowApprovalMode[] {
        return [...this.approvalModes.values()];
    }
    getSource(name: string): WorkflowAssigneeSource | undefined {
        return this.assigneeSources.get(name);
    }
    getMode(name: string): WorkflowApprovalMode | undefined {
        return this.approvalModes.get(name);
    }
    read(node: WorkflowNode): WorkflowApprovalSettings {
        const config = object(node.config);
        const assignee = object(config.assignee);
        const approval = object(config.approval);
        return {
            assignee: {
                source: 'assignee' in config ? (typeof assignee.source === 'string' ? assignee.source : '') : 'core:user',
                config: object(assignee.config),
            },
            approval: {
                mode: 'approval' in config ? (typeof approval.mode === 'string' ? approval.mode : '') : 'core:single',
                config: object(approval.config),
            },
        };
    }
}
