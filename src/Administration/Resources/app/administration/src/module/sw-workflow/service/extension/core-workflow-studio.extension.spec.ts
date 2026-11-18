/** @sw-package framework */
import '../index';
import coreWorkflowStudioExtension from './core-workflow-studio.extension';

describe('generic workflow capability composition', () => {
    it('advertises only nodes with built-in execution support', () => {
        expect(coreWorkflowStudioExtension.nodes?.map((node) => node.type)).toEqual([
            'core:start',
            'core:end',
            'core:script',
            'core:action',
            'core:notification',
            'core:subprocess',
            'core:parallel',
            'core:join',
        ]);
    });

    it('provides a generic starting template without installing approval capabilities', () => {
        const templates = Shopwell.Service('workflowTemplateService');
        const document = templates.get('core:basic')!.create([], { translate: (key) => key });

        expect(document.nodes.map((node) => node.type)).toEqual(['core:start', 'core:end']);
        expect(document.nodes[0].config).toEqual({ formFields: [] });
        expect(templates.get('core:approval')).toBeUndefined();
        expect(
            Shopwell.Service('workflowNodePresentationService').get('core:approval', 1).settingsComponent,
        ).toBeUndefined();
    });
});
