/** @sw-package framework */
import type { VueWrapper } from '@vue/test-utils';
import { mount, flushPromises } from '@vue/test-utils';
const id = '11111111111111111111111111111111';
describe('approver summary', () => {
    it('renders the current name and reports stopped accounts without changing configuration', async () => {
        const get = jest
            .fn()
            .mockResolvedValueOnce({ name: 'Alex', active: true })
            .mockResolvedValueOnce({ name: 'Sam', active: false });
        const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-user-assignee-summary', { sync: true }), {
            props: { config: { userId: id } },
            global: {
                provide: { repositoryFactory: { create: () => ({ get }) }, acl: { can: () => true } },
                mocks: { $t: (key: string, values?: { name: string }) => (values?.name ? `${key}:${values.name}` : key) },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Alex');
        await wrapper.setProps({ config: { userId: '22222222222222222222222222222222' } });
        await flushPromises();
        expect(wrapper.text()).toContain('inactive');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        wrapper.unmount();
    });
});
