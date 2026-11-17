/** @sw-package framework */
import approvalWorkflowStudioExtension from './approval-workflow-studio.extension';

describe('approval studio default templates', () => {
    it('provides the basic approval template without advertising an unimplemented expense business', () => {
        expect(approvalWorkflowStudioExtension.templates?.map((template) => template.name)).toEqual(['core:approval']);
    });
});
