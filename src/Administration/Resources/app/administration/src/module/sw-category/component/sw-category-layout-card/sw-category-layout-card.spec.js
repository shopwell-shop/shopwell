import { shallowMount } from '@vue/test-utils';

/**
 * @sw-package discovery
 */
describe('sw-category-layout-card', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it.each([true, false])('allows Experience Studio navigation according to its viewer privilege: %s', async (allowed) => {
        const push = jest.fn();

        wrapper = shallowMount(await wrapTestComponent('sw-category-layout-card', { sync: true }), {
            global: {
                mocks: { $router: { push } },
                provide: { acl: { can: (privilege) => allowed && privilege === 'experience_studio.viewer' } },
                stubs: {
                    'mt-card': { template: '<section><slot /></section>' },
                    'mt-button': {
                        name: 'mt-button',
                        props: ['disabled'],
                        template: '<button :disabled="disabled"><slot /></button>',
                    },
                },
            },
        });

        expect(wrapper.get('button').element.disabled).toBe(!allowed);
        await wrapper.get('button').trigger('click');

        expect(push.mock.calls).toEqual(allowed ? [[{ name: 'sw.experience.studio.index' }]] : []);
    });
});
