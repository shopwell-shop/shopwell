/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';

const graph = {
    id: 'graph',
    schemaVersion: '1.0.0',
    meta: { name: '报销流程', editingMode: 'oa', formSchemaRef: 'expense:claim' },
    nodes: [
        { id: 'node-one', type: 'expense:review', typeVersion: '1.0.0', config: { threshold: 10 }, ports: [] },
        { id: 'node-two', type: 'expense:review', typeVersion: '2.0.0', config: { threshold: 20 }, ports: [] },
    ],
    connections: [],
};
const detail = {
    definition: { id: 'flow', name: '报销流程', createdAt: null, updatedAt: null },
    draft: {
        revision: 3,
        graphJson: JSON.stringify(graph),
        diagnostics: [{ category: 'capability', code: 'unavailable', path: '/nodes/1', message: 'Node type unavailable.' }],
    },
};
const catalog = {
    graphSchema: '{}',
    nodeTypes: [
        { key: 'expense:review@1.0.0', type: 'expense:review', version: '1.0.0', labelSnippet: 'expense.review.v1' },
    ],
    businessTypes: [],
};

async function createWrapper(
    catalogLoad = jest.fn().mockResolvedValue(catalog),
    get = jest.fn().mockResolvedValue({ ...detail.definition, draft: detail.draft }),
) {
    const previewPublication = jest.fn().mockResolvedValue({ contentHash: 'compiled-content' });
    const publish = jest.fn().mockResolvedValue({ id: 'release' });
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-definition-detail', { sync: true }), {
        props: { definitionId: 'flow' },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ get }),
                },
                approvalFlowDesignApiService: {
                    catalog: catalogLoad,
                    diagnose: jest.fn().mockResolvedValue(detail.draft.diagnostics),
                    previewPublication,
                    publish,
                },
                acl: { can: () => true },
            },
            mocks: { $route: { query: {} }, $te: (key: string) => key === 'expense.review.v1' },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-back"/><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-card-view': { template: '<div><slot/></div>' },
                'mt-card': { props: ['title'], template: '<section><h3>{{ title }}</h3><slot/></section>' },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-modal-root': { template: '<div><slot/></div>' },
                'mt-modal': { template: '<div><slot/><slot name="footer"/></div>' },
                'router-link': { template: '<a><slot/></a>' },
                'sw-loader': true,
                'sw-approval-flow-release-list': true,
                'mt-badge': { template: '<span><slot/></span>' },
            },
        },
    });
    await flushPromises();
    return { wrapper, previewPublication, publish };
}

describe('approval definition overview', () => {
    it('publishes only after confirmation, with the revision and hash returned by the preview', async () => {
        const { wrapper, previewPublication, publish } = await createWrapper();
        await wrapper.get('[data-testid="approval-publication-preview"]').trigger('click');
        await flushPromises();
        expect(previewPublication).toHaveBeenCalledWith('flow', 3);
        expect(publish).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="approval-publish"]').trigger('click');
        await flushPromises();
        expect(publish).toHaveBeenCalledWith('flow', 3, 'compiled-content');
        expect(wrapper.text()).toContain('sw-approval-flow.design.published');
    });

    it('reports a missing definition or draft through the normal not-found state', async () => {
        const { wrapper } = await createWrapper(undefined, jest.fn().mockResolvedValue(null));
        expect(wrapper.text()).toContain('sw-approval-flow.design.notFound');
        expect(wrapper.findComponent({ name: 'sw-approval-flow-release-list' }).exists()).toBe(false);
    });

    it('resolves exact versions and retains missing extension nodes without substituting v1', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.text()).toContain('报销流程');
        expect(wrapper.text()).toContain('expense.review.v1');
        expect(wrapper.text()).toContain('expense:review@2.0.0');
        expect(wrapper.text()).toContain('sw-approval-flow.design.nodeUnavailable');
        expect(wrapper.text()).toContain('Node type unavailable.');
        expect(wrapper.findComponent({ name: 'sw-approval-flow-release-list' }).exists()).toBe(true);
    });

    it('keeps the draft readable when the catalog request fails and reports discovery failure separately', async () => {
        const { wrapper } = await createWrapper(jest.fn().mockRejectedValue(new Error('catalog offline')));
        expect(wrapper.text()).toContain('报销流程');
        expect(wrapper.text()).toContain('sw-approval-flow.design.catalogError');
        expect(wrapper.text()).toContain('expense:review@1.0.0');
        expect(wrapper.text()).not.toContain('sw-approval-flow.design.nodeUnavailable');
    });
});
