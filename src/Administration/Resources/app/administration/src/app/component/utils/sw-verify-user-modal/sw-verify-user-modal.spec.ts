/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('sw-verify-user-modal', () => {
    it('preserves the session lifetime when applying a verified token', async () => {
        const expiresAt = Date.now() + 300_000;
        const setBearerAuthentication = jest.fn<void, [{ access: string; refresh: string; expiry: number }]>();
        const wrapper = mount(await wrapTestComponent('sw-verify-user-modal', { sync: true }), {
            global: {
                provide: {
                    loginService: {
                        verifyUserToken: jest.fn().mockResolvedValue('verified-token'),
                        getBearerAuthentication: () => ({ access: 'original', refresh: 'refresh', expiry: expiresAt }),
                        setBearerAuthentication,
                    },
                },
                stubs: { 'sw-modal': { template: '<div><slot/><slot name="modal-footer"/></div>' } },
            },
        });
        await wrapper.find('input').setValue('password');
        await wrapper.findAll('button').at(-1)!.trigger('click');
        await flushPromises();
        expect(setBearerAuthentication).toHaveBeenCalledTimes(1);
        const auth = setBearerAuthentication.mock.calls[0][0];
        expect(auth.access).toBe('verified-token');
        expect(auth.refresh).toBe('refresh');
        const expiry = auth.expiry;
        expect(expiry).toBeGreaterThan(290);
        expect(expiry).toBeLessThanOrEqual(300);
        expect(wrapper.emitted('verified')).toHaveLength(1);
    });
});
