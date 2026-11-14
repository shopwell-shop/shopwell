/** @sw-package framework */
import '../service';
import { conditionBranches, emptyConditionBranch } from './workflow-condition.util';
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

    it('does not treat shared or configured terminal paths as disposable', () => {
        const document = {
            schemaVersion: 1 as const,
            policy: 'core:structured',
            nodes: [{ id: 'end', type: 'core:end', typeVersion: 1, config: { label: '', description: '' } }],
            connections: [{ id: 'a', source: 'condition', sourcePort: 'branch-a', target: 'end', targetPort: 'in' }],
        };
        expect(emptyConditionBranch(document, 'condition', 'branch-a')).toBe(true);
        document.nodes[0].config.label = 'Custom ending';
        expect(emptyConditionBranch(document, 'condition', 'branch-a')).toBe(false);
        document.nodes[0].config.label = '';
        document.connections.push({ ...document.connections[0], id: 'b', source: 'other' });
        expect(emptyConditionBranch(document, 'condition', 'branch-a')).toBe(false);
    });
});
