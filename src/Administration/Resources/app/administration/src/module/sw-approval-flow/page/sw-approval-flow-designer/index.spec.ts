/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import ApprovalDesignerService from '../../designer/approval-designer.service';
import type { ApprovalDesignGraph } from '../../service/approval-flow-design.api.service';

const definitionId = '11111111111111111111111111111111';
const graph: ApprovalDesignGraph = {
    id: 'transfer',
    schemaVersion: '1.0.0',
    meta: { name: 'Transfer review', editingMode: 'free', formSchemaRef: 'payment:transfer' },
    nodes: [
        {
            id: 'approval',
            type: 'core:approval',
            typeVersion: '1.0.0',
            config: {},
            ports: [],
            extensions: {},
            position: { x: 0, y: 0 },
        },
    ],
    connections: [],
    layout: { algorithm: 'core:layered', nodes: [] },
    extensions: {},
};

async function createWrapper(canEdit = true) {
    const saveDraft = jest.fn();
    const component = await wrapTestComponent('sw-approval-flow-designer', { sync: true });
    const wrapper = shallowMount(component, {
        props: { definitionId },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: jest.fn().mockResolvedValue({
                            draft: { revision: 3, graphJson: JSON.stringify(graph) },
                        }),
                    }),
                },
                approvalDesignerService: new ApprovalDesignerService(),
                approvalFlowDesignApiService: {
                    catalog: jest.fn().mockResolvedValue({ nodeTypes: [], businessTypes: [] }),
                    saveDraft,
                },
                acl: { can: () => canEdit },
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-approval-flow-canvas': {
                    name: 'sw-approval-flow-canvas',
                    props: ['document', 'readOnly'],
                    template: `<section class="canvas">
                        <button class="move-node" @click="$emit('move-node', 'approval', { x: 50, y: 0 })">Move</button>
                        <output>{{ document.nodes[0].position.x }}</output>
                    </section>`,
                },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
                'mt-select': true,
                'mt-text-field': true,
                'mt-loader': true,
                'mt-icon': true,
            },
        },
    });
    await flushPromises();

    return { wrapper, saveDraft };
}

function buttonWithLabel(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper'], label: string) {
    const snippet = `sw-approval-flow.designer.${label}`;
    const button = wrapper
        .findAll('button')
        .find((item) => item.text() === snippet || item.attributes('aria-label') === snippet);
    if (!button) {
        throw new Error(`Designer button not found: ${label}`);
    }

    return button;
}

describe('approval flow designer', () => {
    it('keeps the edited document after a revision conflict', async () => {
        const { wrapper, saveDraft } = await createWrapper();
        saveDraft.mockRejectedValue({ response: { status: 409 } });
        const saveButton = buttonWithLabel(wrapper, 'save');

        await wrapper.get('.move-node').trigger('click');
        await saveButton.trigger('click');
        await flushPromises();

        expect(saveDraft).toHaveBeenCalledWith(definitionId, 3, expect.any(String));
        expect(wrapper.get('[role="alert"]').text()).toContain('sw-approval-flow.designer.conflict');
        expect(wrapper.get('output').text()).toBe('50');
        expect(saveButton.attributes('disabled')).toBeUndefined();
    });

    it('keeps saved edits in the undo history and sends the new revision on the next save', async () => {
        const { wrapper, saveDraft } = await createWrapper();
        saveDraft.mockResolvedValue({ definitionId, revision: 4, diagnostics: [] });
        const saveButton = buttonWithLabel(wrapper, 'save');
        const undoButton = buttonWithLabel(wrapper, 'undo');

        await wrapper.get('.move-node').trigger('click');
        await saveButton.trigger('click');
        await flushPromises();
        expect(saveButton.attributes('disabled')).toBeDefined();

        await undoButton.trigger('click');
        await saveButton.trigger('click');
        await flushPromises();
        expect(saveDraft).toHaveBeenLastCalledWith(definitionId, 4, JSON.stringify(graph));
    });

    it('lets viewers inspect the graph and rejects canvas edit events', async () => {
        const { wrapper, saveDraft } = await createWrapper(false);

        await wrapper.get('.move-node').trigger('click');

        expect(wrapper.get('output').text()).toBe('0');
        expect(wrapper.text()).not.toContain('sw-approval-flow.designer.save');
        expect(saveDraft).not.toHaveBeenCalled();
    });
});
