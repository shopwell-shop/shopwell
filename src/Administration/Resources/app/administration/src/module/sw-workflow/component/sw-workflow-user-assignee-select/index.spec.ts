/** @sw-package framework */
import { mount, flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
const id = '11111111111111111111111111111111';
const active = { id, name: 'Alex', username: 'alex', active: true };
async function createWrapper(
    config: Record<string, unknown> = {},
    allowEdit = true,
    record: unknown = active,
    allowed = true,
) {
    const get = jest.fn().mockResolvedValue(record);
    const wrapper = mount(await wrapTestComponent('sw-workflow-user-assignee-select', { sync: true }), {
        props: { config, allowEdit },
        global: {
            provide: { repositoryFactory: { create: () => ({ get }) }, acl: { can: () => allowed } },
            stubs: {
                'sw-entity-single-select': {
                    props: ['value', 'criteria', 'disabled'],
                    emits: ['update:value'],
                    template: '<div class="user-select" />',
                },
            },
        },
    });
    await flushPromises();
    return { wrapper, get };
}
function select(wrapper: VueWrapper) {
    return wrapper.findComponent('.user-select') as VueWrapper;
}
describe('user assignee selection', () => {
    it('filters active accounts and emits only their IDs while preserving opaque config', async () => {
        const { wrapper } = await createWrapper({ custom: 'keep' });
        expect(select(wrapper).props()).toMatchObject({
            criteria: { filters: [{ type: 'equals', field: 'active', value: true }] },
        });
        select(wrapper).vm.$emit('update:value', id, active);
        await flushPromises();
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ userId: id, custom: 'keep' });
        wrapper.unmount();
    });
    it('does not silently clear deleted users or accept disabled selections', async () => {
        const { wrapper } = await createWrapper({ userId: id }, true, null);
        expect(wrapper.find('[role="status"]').text()).toContain('missing');
        select(wrapper).vm.$emit('update:value', null);
        select(wrapper).vm.$emit('update:value', id, { ...active, active: false });
        await flushPromises();
        expect(wrapper.emitted('update-config')).toBeUndefined();
        await wrapper.find('button').trigger('click');
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ userId: null });
        wrapper.unmount();
    });
    it('enforces read-only and user-read permissions', async () => {
        const { wrapper } = await createWrapper({}, false);
        select(wrapper).vm.$emit('update:value', id, active);
        await flushPromises();
        expect(wrapper.emitted('update-config')).toBeUndefined();
        wrapper.unmount();
        const denied = await createWrapper({ userId: id }, false, active, false);
        expect(denied.wrapper.find('.user-select').exists()).toBe(false);
        expect(denied.get).not.toHaveBeenCalled();
        denied.wrapper.unmount();
    });
    it('ignores the real entity selector response after the assignment has been cleared', async () => {
        let resolveSelection!: (value: unknown) => void;
        const get = jest
            .fn()
            .mockResolvedValueOnce(active)
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveSelection = resolve;
                    }),
            );
        const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-user-assignee-select', { sync: true }), {
            props: { config: { userId: id } },
            global: {
                provide: { repositoryFactory: { create: () => ({ get }) }, acl: { can: () => true } },
                stubs: {
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', { sync: true }),
                    'sw-select-base': { template: '<div><slot name="sw-select-selection" :disabled="false" /></div>' },
                    'sw-select-result-list': true,
                },
            },
        });
        await flushPromises();
        expect(get).toHaveBeenCalledTimes(2);
        await wrapper.find('button').trigger('click');
        await wrapper.setProps({ config: { userId: null } });
        resolveSelection(active);
        await flushPromises();
        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).not.toContain('Alex');
        wrapper.unmount();
    });
});
