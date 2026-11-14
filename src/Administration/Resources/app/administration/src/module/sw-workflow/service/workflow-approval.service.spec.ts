/** @sw-package framework */
import './extension/approval-workflow.registration';
import WorkflowApprovalService from './workflow-approval.service';
import './index';

describe('approval extensions', () => {
    it('reads defaults for an unconfigured node and preserves unknown extension settings', () => {
        const service = new WorkflowApprovalService();
        expect(service.read({ id: 'a', type: 'core:approval', typeVersion: 1, config: {} })).toEqual({
            assignee: { source: 'core:user', config: {} },
            approval: { mode: 'core:single', config: {} },
        });
        const config = {
            assignee: { source: 'custom:manager', config: { level: 2 } },
            approval: { mode: 'custom:all', config: { quorum: 3 } },
        };
        expect(service.read({ id: 'a', type: 'core:approval', typeVersion: 1, config })).toEqual(config);
        expect(service.getSource('custom:manager')).toBeUndefined();
    });
    it('registers alternate sources and approval modes without changing the editor', () => {
        const service = new WorkflowApprovalService();
        const source = {
            name: 'custom:manager',
            labelKey: 'custom.manager',
            settingsComponent: 'custom-manager-settings',
            summaryComponent: 'custom-manager-summary',
        };
        const mode = {
            name: 'custom:all',
            labelKey: 'custom.all',
            descriptionKey: 'custom.allDescription',
            settingsComponent: 'custom-all-settings',
        };
        service.registerSource(source);
        service.registerMode(mode);
        expect(service.sources()).toEqual([source]);
        expect(service.getSource(source.name)).toBe(source);
        expect(service.modes()).toEqual([mode]);
        expect(service.getMode(mode.name)).toBe(mode);
    });
    it('connects the default source and mode to approval presentation extensions', () => {
        const service = Shopwell.Service('workflowApprovalService');
        expect(service.getSource('core:user')?.settingsComponent).toBe('sw-workflow-user-assignee-select');
        expect(service.getMode('core:single')).toBeDefined();
        expect(Shopwell.Service('workflowNodePresentationService').get('core:approval', 1)).toMatchObject({
            settingsComponent: 'sw-workflow-approval-settings',
            nodeComponent: 'sw-workflow-approval-node',
        });
    });
});
