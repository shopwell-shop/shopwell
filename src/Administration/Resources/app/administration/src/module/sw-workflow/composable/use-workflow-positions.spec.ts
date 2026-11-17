/** @sw-package framework */
import { defineComponent, ref } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import useWorkflowPositions from './use-workflow-positions';

const first = '11111111111111111111111111111111';
const second = '22222222222222222222222222222222';

describe('workflow position labels', () => {
    it('ignores stale responses after the configured positions change', async () => {
        const config = ref<Record<string, unknown>>({ positionIds: [first] });
        let resolveFirst: (value: unknown[]) => void = () => {};
        const search = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValueOnce([{ id: second, name: 'Finance', active: true }]);
        let state: ReturnType<typeof useWorkflowPositions>;
        const wrapper = mount(
            defineComponent({
                setup() {
                    state = useWorkflowPositions(config);
                    return state;
                },
                template: '<div />',
            }),
            { global: { provide: { repositoryFactory: { create: () => ({ search }) }, acl: { can: () => true } } } },
        );
        config.value = { positionIds: [second] };
        await flushPromises();
        resolveFirst([{ id: first, name: 'Outdated', active: true }]);
        await flushPromises();
        expect(state!.positions.value.map((position) => position.id)).toEqual([second]);
        expect(state!.status.value).toBe('ready');
        wrapper.unmount();
    });
});
