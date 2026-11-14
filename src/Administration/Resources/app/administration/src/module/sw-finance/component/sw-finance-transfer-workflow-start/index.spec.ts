/** @sw-package framework */
import { mount } from '@vue/test-utils';

describe('finance workflow request entry', () => {
    it.each([true, false])('requires transfer creator permission: %s', async (allowed) => {
        const push = jest.fn();
        const wrapper = mount(await wrapTestComponent('sw-finance-transfer-workflow-start', { sync: true }), {
            global: {
                provide: { acl: { can: (privilege: string) => allowed && privilege === 'payment_transfer.creator' } },
                mocks: { $router: { push } },
            },
        });
        expect(wrapper.get('button').attributes('disabled') !== undefined).toBe(!allowed);
        await wrapper.get('button').trigger('click');
        expect(push.mock.calls).toEqual(allowed ? [[{ name: 'sw.finance.transfer', query: { create: '1' } }]] : []);
        wrapper.unmount();
    });
});
