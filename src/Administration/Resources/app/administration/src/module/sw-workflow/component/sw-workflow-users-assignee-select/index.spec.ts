/** @sw-package framework */
import { mount, flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
const first = '11111111111111111111111111111111';
const second = '22222222222222222222222222222222';
const missing = '33333333333333333333333333333333';
const alex = { id: first, username: 'alex', name: 'Alex', active: true };
const sam = { id: second, username: 'sam', name: 'Sam', active: true };
const wrappers: VueWrapper[] = [];

async function createWrapper(userIds: string[] = [], allowEdit = true, records: unknown[] = [alex, sam]) {
    const search = jest.fn().mockResolvedValue(records);
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-users-assignee-select', { sync: true }), {
        props: { config: { userIds, extra: 'keep' }, allowEdit },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search,
                        route: '/user',
                        entityName: 'user',
                        create: (_context: unknown, id: string) => ({ id }),
                    }),
                },
                acl: { can: () => true },
            },
            stubs: {
                'sw-entity-multi-select': {
                    props: ['entityCollection', 'criteria', 'disabled'],
                    emits: ['update:entityCollection'],
                    template: '<div class="people-select" />',
                },
            },
        },
    });
    await flushPromises();
    wrappers.push(wrapper);
    return wrapper;
}
function selector(wrapper: VueWrapper) {
    return wrapper.findComponent('.people-select') as VueWrapper;
}
describe('multiple approver selection', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });
    it('deduplicates IDs and retains opaque configuration', async () => {
        const wrapper = await createWrapper();
        selector(wrapper).vm.$emit('update:entityCollection', [alex, sam, alex]);
        await flushPromises();
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ userIds: [first, second], extra: 'keep' });
        expect(selector(wrapper).props()).toMatchObject({
            criteria: { filters: [{ type: 'equals', field: 'active', value: true }] },
        });
    });
    it('keeps missing people in the selection until explicitly removed', async () => {
        const wrapper = await createWrapper([first, missing], true, [alex]);
        const props = selector(wrapper).props() as { entityCollection: { id: string }[] };
        expect(Array.from(props.entityCollection, (user) => user.id)).toEqual([first, missing]);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        selector(wrapper).vm.$emit('update:entityCollection', [alex]);
        await flushPromises();
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ userIds: [first], extra: 'keep' });
    });
    it('rejects newly selected inactive users and ignores edits in read-only mode', async () => {
        const wrapper = await createWrapper();
        selector(wrapper).vm.$emit('update:entityCollection', [{ ...sam, active: false }]);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        const readOnly = await createWrapper([first], false);
        selector(readOnly).vm.$emit('update:entityCollection', [sam]);
        expect(readOnly.emitted('update-config')).toBeUndefined();
    });
});
