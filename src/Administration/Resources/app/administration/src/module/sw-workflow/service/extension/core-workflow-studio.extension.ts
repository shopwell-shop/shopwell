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
