/** @sw-package framework */
import '../service';
import { conditionBranches } from './workflow-condition.util';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';

describe('condition draft configuration', () => {
    it('keeps valid ports visible for incomplete rules while deduplicating and rejecting invalid identifiers', () => {
        const node: WorkflowNode = {
            id: 'condition',
            type: 'core:condition',
            typeVersion: 1,
            config: {
                branches: [
                    { id: 'branch-a' },
                    { id: 'branch-a' },
                    { id: 'fallback' },
                    { id: 'invalid' },
                ],
            },
        };
        const original = JSON.stringify(node);
        expect(conditionBranches(node)).toEqual([
            { id: 'branch-a', label: '', field: '', fieldType: '', operator: 'equals', value: '' },
        ]);
        const ports = Shopwell.Service('workflowNodePresentationService').get(node.type, 1).resolvePorts!(node, {
            name: node.type,
            version: 1,
            kind: 'activity',
            inputPorts: ['in'],
            outputPorts: [],
        });
        expect(ports.outputPorts).toEqual(['branch-a', 'fallback']);
        expect(JSON.stringify(node)).toBe(original);
    });

    it('normalizes incomplete draft fields while retaining the first branch and extension data', () => {
        const node: WorkflowNode = {
            id: 'condition',
            type: 'core:condition',
            typeVersion: 1,
            config: {
                branches: [
                    null,
                    'branch-a',
                    { id: 'branch-a', label: 12, field: null, operator: false, value: 0, extension: { source: 'plugin' } },
                    { id: 'branch-a', label: 'Duplicate must not replace the first entry' },
                    { id: 'branch-b', operator: 'plugin:compare', value: 'kept' },
                ],
            },
        };
        const original = JSON.stringify(node);
        expect(conditionBranches(node)).toEqual([
            {
                id: 'branch-a',
                label: '',
                field: '',
                fieldType: '',
                operator: 'equals',
                value: '',
                extension: { source: 'plugin' },
            },
            { id: 'branch-b', label: '', field: '', fieldType: '', operator: 'plugin:compare', value: 'kept' },
        ]);
        expect(JSON.stringify(node)).toBe(original);
    });

    it.each([
        null,
        {},
        'branches',
        [],
    ])('tolerates a missing draft branch list: %s', (branches) => {
        expect(conditionBranches({ id: 'condition', type: 'core:condition', typeVersion: 1, config: { branches } })).toEqual(
            [],
        );
    });
});
