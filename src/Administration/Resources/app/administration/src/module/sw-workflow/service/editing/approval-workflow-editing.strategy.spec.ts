/** @sw-package framework */
import '../extension/approval-workflow.registration';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';
import ApprovalWorkflowEditingStrategy from './approval-workflow-editing.strategy';
import '../../service';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import { createApprovalWorkflow } from '../../util/approval-workflow-template.util';
const type = {
    name: 'core:approval',
    version: 1,
    kind: 'activity' as const,
    inputPorts: ['in'],
    outputPorts: ['approved', 'rejected'],
};
describe('approval editing defaults', () => {
    it('initializes the same single-user settings for inserted nodes and the default template', () => {
        const strategy = Shopwell.Service('workflowNodeEditingService').get(type.name, 1);
        const inserted = strategy.insert(createStructuredWorkflowDraft(), type, 'start-end', 'approval');
        const config = {
            assignee: { source: 'core:user', config: { userId: null } },
            approval: { mode: 'core:single', config: {} },
        };
        expect(inserted.nodes.find((node) => node.id === 'approval')?.config).toEqual(config);
        expect(
            createApprovalWorkflow([type], { translate: (key) => key }).nodes.find((node) => node.id === 'approval')?.config,
        ).toEqual(config);
        expect(inserted.connections.filter((edge) => edge.source === 'approval').map((edge) => edge.sourcePort)).toEqual([
            'approved',
            'rejected',
        ]);
    });
});

describe('approval removal', () => {
    const strategy = new ApprovalWorkflowEditingStrategy();

    it('removes an inserted approval and its private empty rejection end without mutating the draft', () => {
        const draft = createStructuredWorkflowDraft();
        const inserted = strategy.insert(draft, type, 'start-end', 'approval');
        expect(strategy.canRemove(inserted, 'approval')).toBe(true);
        expect(strategy.remove(inserted, 'approval')).toEqual(draft);
        expect(inserted.nodes).toHaveLength(4);
    });

    it('reconnects the incoming edge to the approved successor and preserves its port and continuation', () => {
        const inserted = strategy.insert(createStructuredWorkflowDraft(), type, 'start-end', 'approval');
        const document = new StructuredWorkflowEditingStrategy().insert(
            inserted,
            { ...type, name: 'example:activity', inputPorts: ['review-in'], outputPorts: ['out'] },
            'approval-out-0',
            'successor',
        );
        const next = strategy.remove(document, 'approval');
        expect(next.connections).toEqual([
            { id: 'start-end', source: 'start', sourcePort: 'out', target: 'successor', targetPort: 'review-in' },
            { id: 'successor-out-0', source: 'successor', sourcePort: 'out', target: 'end', targetPort: 'in' },
        ]);
        expect(next.nodes.map((node) => node.id)).toEqual(['start', 'end', 'successor']);
    });

    it.each(['parent', 'ancestor', 'downstream-cycle'])(
        'rejects an approved path returning to its %s without modifying the draft',
        (scenario) => {
            const draft = new StructuredWorkflowEditingStrategy().insert(
                createStructuredWorkflowDraft(),
                { ...type, name: 'example:activity', outputPorts: ['out'] },
                'start-end',
                'parent',
            );
            const document = strategy.insert(draft, type, 'parent-out-0', 'approval');
            const approved = document.connections.find(
                (edge) => edge.source === 'approval' && edge.sourcePort === 'approved',
            )!;
            switch (scenario) {
                case 'parent':
                    approved.target = 'parent';
                    break;
                case 'ancestor':
                    approved.target = 'start';
                    break;
                case 'downstream-cycle':
                    document.connections.push({
                        id: 'end-parent',
                        source: 'end',
                        sourcePort: 'out',
                        target: 'parent',
                        targetPort: 'in',
                    });
                    break;
                default:
                    throw new Error('Unknown cycle scenario');
            }
            const original = JSON.parse(JSON.stringify(document)) as typeof document;
            expect(strategy.canRemove(document, 'approval')).toBe(false);
            expect(strategy.remove(document, 'approval')).toBe(document);
            expect(document).toEqual(original);
        },
    );

    it.each([
        'shared',
        'configured',
        'nonterminal',
        'outgoing',
        'missing-approved',
        'same-target',
        'loop',
    ])('refuses unsafe %s rejection branches', (scenario) => {
        const document = strategy.insert(createStructuredWorkflowDraft(), type, 'start-end', 'approval');
        const rejected = document.connections.find((edge) => edge.sourcePort === 'rejected')!;
        const end = document.nodes.find((node) => node.id === rejected.target)!;
        switch (scenario) {
            case 'shared':
                document.connections.push({
                    id: 'shared',
                    source: 'start',
                    sourcePort: 'out',
                    target: end.id,
                    targetPort: 'in',
                });
                break;
            case 'configured':
                end.config = { label: 'Keep me' };
                break;
            case 'nonterminal':
                end.type = 'example:activity';
                break;
            case 'outgoing':
                document.connections.push({
                    id: 'continuation',
                    source: end.id,
                    sourcePort: 'out',
                    target: 'end',
                    targetPort: 'in',
                });
                break;
            case 'missing-approved':
                document.connections = document.connections.filter((edge) => edge.sourcePort !== 'approved');
                break;
            case 'same-target':
                document.connections.find((edge) => edge.sourcePort === 'approved')!.target = end.id;
                break;
            case 'loop':
                document.connections.find((edge) => edge.sourcePort === 'approved')!.target = 'approval';
                break;
            default:
                throw new Error('Unknown unsafe branch scenario');
        }
        expect(strategy.canRemove(document, 'approval')).toBe(false);
        expect(strategy.remove(document, 'approval')).toBe(document);
    });
});
