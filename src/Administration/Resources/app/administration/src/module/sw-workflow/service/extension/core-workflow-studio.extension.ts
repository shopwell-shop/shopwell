/** @sw-package framework */
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import { structuredWorkflowLayout } from '../../util/structured-workflow-layout.util';
import workflowRequestFieldProvider from '../field/workflow-request-field.provider';

/** Basic editing capabilities have no dependency on approval behavior. @private */
const coreWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:studio',
    nodes: [
        {
            type: WORKFLOW_NODE_TYPES.START,
            version: 1,
            presentation: { icon: 'regular-play', tone: 'neutral', settingsComponent: 'sw-workflow-start-settings' },
        },
        { type: WORKFLOW_NODE_TYPES.END, version: 1, presentation: { icon: 'regular-checkmark', tone: 'neutral' } },
        { type: WORKFLOW_NODE_TYPES.SCRIPT, version: 1, presentation: { icon: 'regular-code', tone: 'purple', labelKey: 'sw-workflow.nodes.script' } },
        { type: WORKFLOW_NODE_TYPES.ACTION, version: 1, presentation: { icon: 'regular-cog', tone: 'blue', labelKey: 'sw-workflow.nodes.action' } },
        { type: WORKFLOW_NODE_TYPES.NOTIFICATION, version: 1, presentation: { icon: 'regular-bell', tone: 'blue', labelKey: 'sw-workflow.nodes.notification' } },
        { type: WORKFLOW_NODE_TYPES.SUBPROCESS, version: 1, presentation: { icon: 'regular-flow', tone: 'purple', labelKey: 'sw-workflow.nodes.subprocess' } },
        { type: WORKFLOW_NODE_TYPES.PARALLEL, version: 1, presentation: { icon: 'regular-share', tone: 'green', labelKey: 'sw-workflow.nodes.parallel' } },
        { type: WORKFLOW_NODE_TYPES.JOIN, version: 1, presentation: { icon: 'regular-flow', tone: 'green', labelKey: 'sw-workflow.nodes.join' } },
    ],
    templates: [
        {
            name: 'core:basic',
            titleKey: 'sw-workflow.templates.basic',
            descriptionKey: 'sw-workflow.templates.basicDescription',
            icon: 'regular-flow',
            available: () => true,
            create() {
                const document = createStructuredWorkflowDraft();
                document.nodes[0].config = { formFields: [] };
                return document;
            },
        },
    ],
    layouts: [{ name: 'core:structured', layout: structuredWorkflowLayout, policies: ['core:structured'] }],
    fieldProviders: [{ name: 'core:request', provide: workflowRequestFieldProvider }],
};

/** @private */
export default coreWorkflowStudioExtension;
