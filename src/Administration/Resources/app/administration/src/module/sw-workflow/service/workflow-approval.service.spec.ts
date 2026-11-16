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
        expect(service.getSource(source.name)).toEqual(source);
        expect(service.modes()).toEqual([mode]);
        expect(service.getMode(mode.name)).toEqual(mode);
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

describe('approval registration lifecycle', () => {
    it('registerSource rejects collisions and supports cleanup, reinstall and stale disposers', () => {
        const service = new WorkflowApprovalService();
        const contribution = {
            name: 'example:source',
            labelKey: 'example.source',
            settingsComponent: 'example-settings',
            summaryComponent: 'example-summary',
        };
        const dispose = service.registerSource(contribution);
        expect(() => service.registerSource({ ...contribution })).toThrow(/Duplicate/);
        expect(service.sources()).toEqual([contribution]);
        dispose();
        expect(service.sources()).toEqual([]);
        const disposeAgain = service.registerSource(contribution);
        dispose();
        expect(service.sources()).toEqual([contribution]);
        disposeAgain();
        expect(service.sources()).toEqual([]);
    });

    it('registerMode rejects collisions and supports cleanup, reinstall and stale disposers', () => {
        const service = new WorkflowApprovalService();
        const contribution = { name: 'example:source', labelKey: 'example.source', descriptionKey: 'example.description' };
        const dispose = service.registerMode(contribution);
        expect(() => service.registerMode({ ...contribution })).toThrow(/Duplicate/);
        expect(service.modes()).toEqual([contribution]);
        dispose();
        expect(service.modes()).toEqual([]);
        const disposeAgain = service.registerMode(contribution);
        dispose();
        expect(service.modes()).toEqual([contribution]);
        disposeAgain();
        expect(service.modes()).toEqual([]);
    });

    it('owns mode opt-ins through cleanup and prevents mutation of registered source definitions', () => {
        const service = new WorkflowApprovalService();
        const source = {
            name: 'example:source',
            labelKey: 'example.source',
            settingsComponent: 'settings',
            summaryComponent: 'summary',
            supportedModes: ['core:single'],
        };
        const mode = { name: 'example:quorum', labelKey: 'example.quorum', descriptionKey: 'example.description' };
        const removeSource = service.registerSource(source);
        const removeMode = service.registerMode(mode);
        const removeSupport = service.registerSupportedMode(source.name, mode.name);
        expect(() => service.registerSupportedMode(source.name, mode.name)).toThrow(/Duplicate/);
        source.supportedModes.push('example:leaked');
        service.getSource(source.name)!.supportedModes!.push('example:mutated');
        expect(service.getSource(source.name)!.supportedModes).toEqual(['core:single', 'example:quorum']);
        removeMode();
        service.registerMode(mode);
        expect(service.getSource(source.name)!.supportedModes).toEqual(['core:single']);
        const removeNewSupport = service.registerSupportedMode(source.name, mode.name);
        removeSupport();
        expect(service.getSource(source.name)!.supportedModes).toEqual(['core:single', 'example:quorum']);
        removeSource();
        service.registerSource({ ...source, supportedModes: ['core:single'] });
        expect(service.getSource(source.name)!.supportedModes).toEqual(['core:single']);
        removeNewSupport();
    });
});
