/** @sw-package framework */
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import ConditionWorkflowEditingStrategy from '../editing/condition-workflow-editing.strategy';
import { conditionBranches } from '../../util/workflow-condition.util';

/** @private */
const conditionWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:condition-studio',
    nodes: [
        {
            type: 'core:condition',
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
                        inputPorts: ['in'],
                        outputPorts: [...branches.map((branch) => branch.id), 'fallback'],
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
