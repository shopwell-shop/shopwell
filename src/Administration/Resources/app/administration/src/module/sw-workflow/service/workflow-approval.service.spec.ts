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
    it('registers operation contributions with owned disposal and preserves an explicit empty selection', () => {
        const service = new WorkflowApprovalService();
        const operation = { name: 'example:consult', labelKey: 'example.consult', actionComponent: 'example-consult' };
        const dispose = service.registerOperation(operation);
        expect(service.operations()).toEqual([operation]);
        expect(() => service.registerOperation(operation)).toThrow();
        const config = {
            assignee: { source: 'core:user', config: {} },
            approval: { mode: 'core:single', config: {} },
            operations: [],
        };
        expect(service.read({ id: 'a', type: 'core:approval', typeVersion: 1, config })).toEqual(config);
        dispose();
        const disposeReplacement = service.registerOperation(operation);
        dispose();
        expect(service.operations()).toEqual([operation]);
        disposeReplacement();
        expect(service.operations()).toEqual([]);
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
    it('offers organizational sources with executable approval modes', () => {
        const service = Shopwell.Service('workflowApprovalService');
        expect(service.getSource('core:supervisor')).toBeUndefined();
        expect(service.getSource('core:department-leaders')?.supportedModes).toEqual([
            'core:all',
            'core:or-sign',
            'core:sequential',
            'core:any',
        ]);
    });
    it('offers distinct executable modes without changing saved first-decision settings', () => {
        const service = Shopwell.Service('workflowApprovalService');

        for (const source of ['core:users', 'core:department-leaders']) {
            for (const mode of [
                'core:all',
                'core:or-sign',
                'core:sequential',
                'core:any',
            ]) {
                expect(service.supportsMode(source, mode, 1)).toBe(true);
                expect(service.getMode(mode)).toBeDefined();
            }
        }

        expect(service.getMode('core:any')?.labelKey).not.toBe(service.getMode('core:or-sign')?.labelKey);
        expect(service.supportsMode('core:user', 'core:sequential', 1)).toBe(false);
        const node = {
            id: 'review',
            type: 'core:approval',
            typeVersion: 1,
            config: {
                assignee: { source: 'core:users', config: { userIds: ['reviewer'] } },
                approval: { mode: 'core:any', config: {} },
            },
        };
        expect(service.read(node)).toEqual(node.config);
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

describe('versioned approval modes', () => {
    it('keeps both implementations and selects an exact saved version', () => {
        const service = new WorkflowApprovalService();
        const original = {
            name: 'example:quorum',
            version: 1,
            labelKey: 'example.original',
            descriptionKey: 'example.hint',
        };
        const updated = { ...original, version: 2, labelKey: 'example.updated' };
        const removeOriginal = service.registerMode(original);
        service.registerMode(updated);

        expect(service.getMode(original.name, 1)).toEqual(original);
        expect(service.getMode(original.name, 2)).toEqual(updated);
        expect(service.getMode(original.name, 3)).toBeUndefined();

        removeOriginal();

        expect(service.getMode(original.name, 1)).toBeUndefined();
        expect(service.getMode(original.name, 2)).toEqual(updated);
    });

    it('keeps version-specific opt-ins independent through registration and cleanup', () => {
        const service = new WorkflowApprovalService();
        service.registerSource({
            name: 'example:team',
            labelKey: 'team',
            settingsComponent: 'settings',
            summaryComponent: 'summary',
            supportedModes: [],
        });
        const mode = { name: 'example:quorum', labelKey: 'quorum', descriptionKey: 'description' };
        service.registerMode({ ...mode, version: 1 });
        service.registerMode({ ...mode, version: 2 });
        service.registerMode({ ...mode, version: 3 });
        const removeV2 = service.registerSupportedMode('example:team', mode.name, 2);

        expect(service.getSource('example:team')!.supportedModes).toEqual(['example:quorum@2']);
        const removeV3 = service.registerSupportedMode('example:team', mode.name, 3);
        expect(service.getSource('example:team')!.supportedModes).toEqual(['example:quorum@2', 'example:quorum@3']);
        removeV2();
        expect(service.getSource('example:team')!.supportedModes).toEqual(['example:quorum@3']);
        removeV3();
        expect(service.getSource('example:team')!.supportedModes).toEqual([]);
    });

    it('retains the saved version and opaque plugin configuration while reading a node', () => {
        const service = new WorkflowApprovalService();
        const config = {
            automation: { decision: 'manual' as const, approveInitiator: true, deduplication: 'adjacent' as const },
            assignee: { source: 'example:reviewers', config: { team: 'finance' } },
            approval: { mode: 'example:quorum', version: 2, config: { required: 2 } },
        };

        expect(service.read({ id: 'review', type: 'core:approval', typeVersion: 1, config })).toEqual(config);
    });
});
