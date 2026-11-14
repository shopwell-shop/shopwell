/** @sw-package framework */
import { computed, defineComponent, ref } from 'vue';
import type { PropType } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import useWorkflowUsers from './use-workflow-users';
import type Criteria from 'src/core/data/criteria.data';

const first = '11111111111111111111111111111111';
const second = '22222222222222222222222222222222';
const wrappers: VueWrapper[] = [];
const consumer = defineComponent({
    props: { ids: { type: Array as PropType<string[]>, required: true } },
    setup(props) {
        return useWorkflowUsers(computed(() => props.ids));
    },
    template:
        '<div><span>{{ status }}:{{ users.map(user => user.name).join(",") }}</span><button @click="retry">Retry</button></div>',
});
function createWrapper(search: jest.Mock, ids = [first], allowed = ref(true), factory = { create: () => ({ search }) }) {
    const wrapper = mount(consumer, {
        props: { ids },
        global: { provide: { repositoryFactory: factory, acl: { can: () => allowed.value } } },
    });
    wrappers.push(wrapper);
    return wrapper;
}
describe('workflow users resolution', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('batches unique valid IDs and preserves assignment order including inactive accounts', async () => {
        const search = jest.fn().mockResolvedValue([
            { id: first, name: 'Alex', active: true },
            { id: second, name: 'Sam', active: false },
        ]);
        const wrapper = createWrapper(search, [
            second,
            'invalid',
            first,
            second,
        ]);
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:Sam,Alex');
        expect(search).toHaveBeenCalledTimes(1);
        expect((search.mock.calls[0] as [Criteria])[0]).toEqual(
            expect.objectContaining({ ids: [first, second], totalCountMode: 0 }),
        );
        expect((search.mock.calls[0] as [Criteria])[0].filters).toEqual([]);
    });
    it('resolves mixed-case UUIDs and shares requests with lowercase selections', async () => {
        const id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        const search = jest.fn().mockResolvedValue([{ id, name: 'Alex', active: true }]);
        const factory = { create: () => ({ search }) };
        const node = createWrapper(search, [id.toUpperCase(), id], ref(true), factory);
        const settings = createWrapper(search, [id], ref(true), factory);
        await flushPromises();
        expect(node.find('span').text()).toBe('ready:Alex');
        expect(settings.find('span').text()).toBe('ready:Alex');
        expect(search).toHaveBeenCalledTimes(1);
        expect((search.mock.calls[0] as [Criteria])[0].ids).toEqual([id]);
    });
    it('avoids requests for empty, invalid and forbidden assignments', async () => {
        const search = jest.fn();
        expect(createWrapper(search, []).find('span').text()).toBe('empty:');
        expect(createWrapper(search, ['invalid']).find('span').text()).toBe('ready:');
        const forbidden = createWrapper(search, [first], ref(false));
        expect(forbidden.find('span').text()).toBe('forbidden:');
        await forbidden.find('button').trigger('click');
        expect(search).not.toHaveBeenCalled();
    });
    it('shares results and retries across consumers with the same unordered ID set', async () => {
        const search = jest
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue([
                { id: first, name: 'Alex', active: true },
                { id: second, name: 'Sam', active: true },
            ]);
        const factory = { create: () => ({ search }) };
        const node = createWrapper(search, [first, second], ref(true), factory);
        const settings = createWrapper(search, [second, first, second], ref(true), factory);
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(1);
        expect(node.find('span').text()).toBe('error:');
        expect(settings.find('span').text()).toBe('error:');
        await settings.find('button').trigger('click');
        await flushPromises();
        expect(node.find('span').text()).toBe('ready:Alex,Sam');
        expect(settings.find('span').text()).toBe('ready:Sam,Alex');
        await node.setProps({ ids: [second, first] });
        expect(search).toHaveBeenCalledTimes(2);
    });
    it('ignores late results after selection changes and overlapping retries', async () => {
        let resolveOld!: (users: unknown[]) => void;
        let resolveRetry!: (users: unknown[]) => void;
        const search = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveOld = resolve;
                    }),
            )
            .mockResolvedValueOnce([{ id: second, name: 'New', active: true }])
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveRetry = resolve;
                    }),
            )
            .mockResolvedValueOnce([{ id: second, name: 'Latest', active: true }]);
        const wrapper = createWrapper(search);
        await wrapper.setProps({ ids: [second] });
        await flushPromises();
        resolveOld([{ id: first, name: 'Old', active: true }]);
        await wrapper.find('button').trigger('click');
        await wrapper.find('button').trigger('click');
        await flushPromises();
        resolveRetry([{ id: second, name: 'Stale retry', active: true }]);
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:Latest');
    });
    it('clears account data when permission is revoked and reloads when granted', async () => {
        const search = jest.fn().mockResolvedValue([{ id: first, name: 'Alex', active: true }]);
        const allowed = ref(true);
        const wrapper = createWrapper(search, [first], allowed);
        await flushPromises();
        allowed.value = false;
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('forbidden:');
        await wrapper.find('button').trigger('click');
        expect(search).toHaveBeenCalledTimes(1);
        allowed.value = true;
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:Alex');
        expect(search).toHaveBeenCalledTimes(2);
    });
    it('loads at most 100 IDs in each sequential batch', async () => {
        const ids = Array.from({ length: 101 }, (_, index) => (index + 1).toString(16).padStart(32, '0'));
        let resolveBatch!: (users: unknown[]) => void;
        const search = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveBatch = resolve;
                    }),
            )
            .mockResolvedValueOnce([{ id: ids[100], name: 'Last', active: true }]);
        const wrapper = createWrapper(search, ids);
        expect(search).toHaveBeenCalledTimes(1);
        expect((search.mock.calls[0] as [Criteria])[0].ids).toHaveLength(100);
        expect(wrapper.find('span').text()).toBe('loading:');
        resolveBatch([{ id: ids[0], name: 'First', active: true }]);
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(2);
        expect((search.mock.calls[1] as [Criteria])[0].ids).toEqual([ids[100]]);
        expect(wrapper.find('span').text()).toBe('ready:First,Last');
    });
    it('stops loading batches after the last consumer unmounts and discards the cache', async () => {
        const ids = Array.from({ length: 101 }, (_, index) => (index + 1).toString(16).padStart(32, '0'));
        let resolveBatch!: (users: unknown[]) => void;
        const search = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveBatch = resolve;
                    }),
            )
            .mockResolvedValue([]);
        const factory = { create: () => ({ search }) };
        const node = createWrapper(search, ids, ref(true), factory);
        const settings = createWrapper(search, ids, ref(true), factory);
        node.unmount();
        expect(search).toHaveBeenCalledTimes(1);
        settings.unmount();
        resolveBatch([]);
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(1);
        createWrapper(search, ids, ref(true), factory);
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(3);
    });
});
