/** @sw-package framework */
import { mount, flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const first = '11111111111111111111111111111111';
const missing = '22222222222222222222222222222222';
const position = { id: first, name: 'Finance', active: true };
const wrappers: VueWrapper[] = [];

async function createWrapper(ids: string[], allowed = true, allowEdit = true, fail = false) {
    const search = fail ? jest.fn().mockRejectedValue(new Error('offline')) : jest.fn().mockResolvedValue([position]);
    const wrapper = mount(await wrapTestComponent('sw-workflow-position-assignee-select', { sync: true }), {
        props: { config: { positionIds: ids, extra: 'keep' }, allowEdit },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ search, route: '/position', create: (_context: unknown, id: string) => ({ id }) }),
                },
                acl: { can: () => allowed },
            },
            stubs: {
                'sw-entity-multi-select': {
                    props: ['entityCollection', 'criteria', 'disabled'],
                    emits: ['update:entityCollection'],
                    template: '<div class="position-select" />',
                },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return { wrapper, search };
}

describe('position approver selection', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('keeps missing positions visible until explicitly removed', async () => {
        const { wrapper } = await createWrapper([first, missing]);
        const select = wrapper.findComponent('.position-select') as VueWrapper;
        expect(
            Array.from((select.props() as { entityCollection: { id: string }[] }).entityCollection, (item) => item.id),
        ).toEqual([
            first,
            missing,
        ]);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        select.vm.$emit('update:entityCollection', [position]);
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ positionIds: [first], extra: 'keep' });
    });

    it('does not read forbidden entities or accept forged changes', async () => {
        const { wrapper, search } = await createWrapper([first], false);
        expect(search).not.toHaveBeenCalled();
        expect(wrapper.find('.position-select').exists()).toBe(false);
        expect(wrapper.emitted('update-config')).toBeUndefined();
    });

    it('preserves selections on failed requests and rejects new inactive positions', async () => {
        const { wrapper } = await createWrapper([missing], true, true, true);
        const select = wrapper.findComponent('.position-select') as VueWrapper;
        expect(
            Array.from((select.props() as { entityCollection: { id: string }[] }).entityCollection, (item) => item.id),
        ).toEqual([missing]);
        expect(wrapper.find('[role="status"]').exists()).toBe(true);
        select.vm.$emit('update:entityCollection', [{ ...position, active: false }]);
        expect(wrapper.emitted('update-config')).toBeUndefined();
    });

    it('prevents changes in read-only mode', async () => {
        const { wrapper } = await createWrapper([first], true, false);
        (wrapper.findComponent('.position-select') as VueWrapper).vm.$emit('update:entityCollection', []);
        expect(wrapper.emitted('update-config')).toBeUndefined();
    });
});
