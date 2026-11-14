/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const first = '11111111111111111111111111111111';
const second = '22222222222222222222222222222222';
const third = '33333333333333333333333333333333';
const wrappers: VueWrapper[] = [];
function translate(key: string, values: Record<string, string | number> = {}): string {
    if (key.endsWith('.separator')) return ', ';
    if (key.endsWith('.more')) return `${values.names} and others (${values.count} total)`;
    if (key.endsWith('.inactiveMember')) return `${values.name} (inactive)`;
    if (key.endsWith('.missingMember')) return `Unavailable member ${values.position}`;
    if (key.endsWith('.unavailableMembers')) return `${values.count} unavailable`;
    return key;
}
async function createWrapper(config: Record<string, unknown>, search: jest.Mock, allowed = true) {
    /** @private */
    Shopwell.Component.register('sw-workflow-users-assignee-summary', () => import('./index'));
    const wrapper = shallowMount(await Shopwell.Component.build('sw-workflow-users-assignee-summary'), {
        props: { config },
        global: {
            provide: { repositoryFactory: { create: () => ({ search }) }, acl: { can: () => allowed } },
            mocks: { $t: translate },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}
describe('multiple approver summary', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('shows the first two names in configured order with total count and a complete tooltip', async () => {
        const config = Object.freeze({
            userIds: Object.freeze([
                second,
                first,
                third,
                first,
            ]),
            approvalMode: 'any',
        });
        const wrapper = await createWrapper(
            config,
            jest.fn().mockResolvedValue([
                { id: first, name: 'Alex', active: true },
                { id: second, name: 'Sam', active: true },
                { id: third, username: 'lee', active: true },
            ]),
        );
        await flushPromises();
        expect(wrapper.text()).toBe('Sam, Alex and others (3 total)');
        expect(wrapper.attributes('title')).toBe('Sam, Alex, lee');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        expect(config.userIds).toEqual([
            second,
            first,
            third,
            first,
        ]);
    });
    it('matches uppercase assignments to current accounts without showing duplicate members', async () => {
        const id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        const wrapper = await createWrapper(
            { userIds: [id.toUpperCase(), id] },
            jest.fn().mockResolvedValue([
                { id, name: 'Alex', active: true },
            ]),
        );
        await flushPromises();
        expect(wrapper.text()).toBe('Alex');
        expect(wrapper.attributes('title')).toBe('Alex');
    });
    it('marks inactive and missing members without showing IDs', async () => {
        const wrapper = await createWrapper(
            {
                userIds: [
                    second,
                    first,
                    third,
                    'broken',
                ],
            },
            jest.fn().mockResolvedValue([
                { id: first, name: 'Alex', active: true },
                { id: second, name: 'Sam', active: false },
            ]),
        );
        await flushPromises();
        expect(wrapper.text()).toContain('Sam (inactive)');
        expect(wrapper.text()).toContain('3 unavailable');
        expect(wrapper.attributes('title')).toContain('Unavailable member 3');
        expect(wrapper.attributes('title')).toContain('Unavailable member 4');
        expect(wrapper.html()).not.toContain(third);
        expect(wrapper.html()).not.toContain('broken');
    });
    it.each([{}, { userIds: 'bad' }, { userIds: [null, 4] }])(
        'handles malformed and empty selections: %j',
        async (config) => {
            const search = jest.fn();
            const wrapper = await createWrapper(config, search);
            expect(wrapper.text()).toContain('status.empty');
            expect(search).not.toHaveBeenCalled();
        },
    );
    it('renders loading, failed and forbidden states without exposing account IDs', async () => {
        let reject!: (error: Error) => void;
        const search = jest.fn(
            () =>
                new Promise((_, rejectPromise) => {
                    reject = rejectPromise;
                }),
        );
        const wrapper = await createWrapper({ userIds: [first] }, search);
        expect(wrapper.text()).toContain('status.loading');
        reject(new Error('offline'));
        await flushPromises();
        expect(wrapper.text()).toContain('status.error');
        expect(wrapper.html()).not.toContain(first);
        const forbidden = await createWrapper({ userIds: [first] }, search, false);
        expect(forbidden.text()).toContain('status.forbidden');
        expect(search).toHaveBeenCalledTimes(1);
    });
});
