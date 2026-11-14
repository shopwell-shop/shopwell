/** @sw-package framework */
import '../index';
import './approval-workflow.registration';
import type { WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

describe('builtin workflow presentations', () => {
    it('keeps approval and automation visually distinct', () => {
        const service = Shopwell.Service('workflowNodePresentationService');
        expect(service.get('core:approval', 1).icon).not.toBe(service.get('core:action', 1).icon);
        expect(service.get('core:approval', 1).summaryKey).toBeDefined();
        expect(service.get('example:unknown', 1).icon).toBe('regular-flow');
    });
});

describe('builtin approval runtime', () => {
    it('registers the core approval start action independently of OA', () => {
        expect(Shopwell.Service('workflowReleaseActionService').get('core:approval')).toBe('sw-workflow-start');
        expect(Shopwell.Service('workflowReleaseActionService').get('swag-oa:approval')).toBeNull();
    });
});

describe('condition presentation capabilities', () => {
    it('rebuilds outgoing ports and labels when the configured branches change', () => {
        const resolvePorts = Shopwell.Service('workflowNodePresentationService').get('core:condition', 1).resolvePorts;
        const type: WorkflowNodeTypeSpecification = {
            name: 'core:condition',
            version: 1,
            kind: 'activity',
            inputPorts: ['in'],
            outputPorts: ['fallback'],
        };
        const condition: WorkflowNode = {
            id: 'condition',
            type: 'core:condition',
            typeVersion: 1,
            config: {
                branches: [
                    {
                        id: 'branch-large-order',
                        label: 'Large order',
                        field: 'request.amount',
                        operator: 'greaterThan',
                        value: '5000',
                    },
                ],
            },
        };

        expect(resolvePorts).toBeDefined();
        expect(resolvePorts?.(condition, type)?.outputPorts).toEqual(['branch-large-order', 'fallback']);

        condition.config = {
            branches: [
                {
                    id: 'branch-manual-review',
                    label: 'Manual review',
                    field: 'request.amount',
                    operator: 'greaterThan',
                    value: '10000',
                },
            ],
        };

        const ports = resolvePorts?.(condition, type);
        expect(ports?.outputPorts).toEqual(['branch-manual-review', 'fallback']);
        expect(ports?.labels).toEqual({ 'branch-manual-review': '1. Manual review' });
    });
});
