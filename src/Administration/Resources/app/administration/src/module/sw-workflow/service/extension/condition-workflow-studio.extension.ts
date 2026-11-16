/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_NODE_PORTS } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import ConditionWorkflowEditingStrategy from '../editing/condition-workflow-editing.strategy';
import { conditionBranches } from '../../util/workflow-condition.util';

/** @private */
const conditionWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:condition-studio',
    nodes: [
        {
            type: WORKFLOW_NODE_TYPES.CONDITION,
            version: 1,
            editing: new ConditionWorkflowEditingStrategy(),
            presentation: {
                icon: 'regular-sitemap',
                tone: 'purple',
                summaryKey: 'sw-workflow.summaries.condition',
                settingsComponent: 'sw-workflow-condition-settings',
                resolvePorts(node) {
                    const branches = conditionBranches(node);

                    return {
                        inputPorts: [WORKFLOW_NODE_PORTS.IN],
                        outputPorts: [...branches.map((branch) => branch.id), WORKFLOW_NODE_PORTS.FALLBACK],
                        labels: Object.fromEntries(
                            branches.map((branch, index) => [branch.id, `${index + 1}. ${branch.label}`]),
                        ),
                    };
                },
            },
        },
    ],
};

/** @private */
export default conditionWorkflowStudioExtension;
