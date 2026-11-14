/** @sw-package framework */
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import { structuredWorkflowLayout } from '../../util/structured-workflow-layout.util';
import workflowRequestFieldProvider from '../field/workflow-request-field.provider';

/** Basic editing capabilities have no dependency on approval behavior. @private */
const coreWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:studio',
    nodes: [
        {
            type: 'core:start',
            version: 1,
            presentation: { icon: 'regular-play', tone: 'neutral', settingsComponent: 'sw-workflow-start-settings' },
        },
        { type: 'core:end', version: 1, presentation: { icon: 'regular-checkmark', tone: 'neutral' } },
        {
            type: 'core:action',
            version: 1,
            presentation: { icon: 'regular-cog', tone: 'blue', summaryKey: 'sw-workflow.summaries.action' },
        },
        {
            type: 'core:human-task',
            version: 1,
            presentation: { icon: 'regular-user', tone: 'purple', summaryKey: 'sw-workflow.summaries.humanTask' },
        },
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
