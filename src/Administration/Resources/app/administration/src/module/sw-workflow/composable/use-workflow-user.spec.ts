/** @sw-package framework */
import { defineComponent, computed } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import useWorkflowUser from './use-workflow-user';

const first = '11111111111111111111111111111111';
const second = '22222222222222222222222222222222';
function createWrapper(get: jest.Mock, id: string | null = first, allowed = true) {
    return mount(
        defineComponent({
            props: { id: { type: String, default: null } },
            setup(props) {
                return useWorkflowUser(computed(() => props.id));
            },
            template: '<div><span>{{ status }}:{{ name }}</span><button @click="retry">Retry</button></div>',
        }),
        {
            props: { id: id ?? undefined },
            global: {
                provide: {
                    repositoryFactory: { create: () => ({ get }) },
                    acl: { can: () => allowed },
                },
            },
        },
    );
}
describe('workflow user resolution', () => {
    it('uses current display names and distinguishes disabled and deleted users', async () => {
        const get = jest.fn().mockResolvedValue({ id: first, name: 'Alex', username: 'alex', active: true });
        const wrapper = createWrapper(get);
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:Alex');
        get.mockResolvedValue({ id: second, name: 'Sam', active: false });
        await wrapper.setProps({ id: second });
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('inactive:Sam');
        get.mockResolvedValue(null);
        await wrapper.setProps({ id: first });
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('missing:');
        wrapper.unmount();
    });
    it('ignores stale responses after undo or selection changes', async () => {
        let resolveFirst!: (value: unknown) => void;
        const get = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValue({ id: second, name: 'New', active: true });
        const wrapper = createWrapper(get);
        await wrapper.setProps({ id: second });
        await flushPromises();
        resolveFirst({ id: first, name: 'Old', active: true });
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:New');
        wrapper.unmount();
    });
    it('does not request user data without permission or for empty assignments', async () => {
        const get = jest.fn();
        const wrapper = createWrapper(get, first, false);
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('forbidden:');
        expect(get).not.toHaveBeenCalled();
        wrapper.unmount();
        const empty = createWrapper(get, null);
        await flushPromises();
        expect(empty.find('span').text()).toBe('empty:');
        expect(get).not.toHaveBeenCalled();
        empty.unmount();
    });
    it('reports network failure separately and retries without changing the assignment', async () => {
        const get = jest
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({ id: first, username: 'alex', active: true });
        const wrapper = createWrapper(get);
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('error:');
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(wrapper.find('span').text()).toBe('ready:alex');
        wrapper.unmount();
    });
    it('shares current account state between the node and settings and retries both together', async () => {
        const get = jest
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({ id: first, name: 'Alex', active: true });
        const consumer = defineComponent({
            setup() {
                return useWorkflowUser(computed(() => first));
            },
            template: '<div><span>{{ status }}:{{ name }}</span><button @click="retry">Retry</button></div>',
        });
        const wrapper = mount(
            defineComponent({ components: { consumer }, template: '<div><consumer /><consumer /></div>' }),
            {
                global: { provide: { repositoryFactory: { create: () => ({ get }) }, acl: { can: () => true } } },
            },
        );
        await flushPromises();
        expect(get).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('span').map((item) => item.text())).toEqual(['error:', 'error:']);
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('span').map((item) => item.text())).toEqual(['ready:Alex', 'ready:Alex']);
        expect(get).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });
});
