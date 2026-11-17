/** @sw-package framework */
import { mount, flushPromises } from '@vue/test-utils';

const first = '11111111111111111111111111111111';
const missing = '22222222222222222222222222222222';

describe('position assignment summary', () => {
    it('shows the initiating department and retains missing positions in the summary', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-position-assignee-summary', { sync: true }), {
            props: { config: { positionIds: [first, missing] } },
            global: {
                mocks: {
                    $t: (key: string, parameters?: Record<string, unknown>) =>
                        parameters ? `${key} ${JSON.stringify(parameters)}` : key,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: jest.fn().mockResolvedValue([{ id: first, name: 'Finance', active: true }]),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Finance');
        expect(wrapper.text()).toContain('sw-workflow.approval.positions.missing');
        expect(wrapper.text()).toContain('sw-workflow.approval.positions.summary');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        wrapper.unmount();
    });
});
