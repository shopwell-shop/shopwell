import { shallowMount, type VueWrapper } from '@vue/test-utils';

/**
 * @sw-package discovery
 */
describe('sw-category-detail-layout', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('renders the Experience Studio card with its loading state', async () => {
        wrapper = shallowMount(await wrapTestComponent('sw-category-detail-layout', { sync: true }), {
            props: { isLoading: true },
            global: {
                stubs: {
                    'sw-category-layout-card': {
                        name: 'sw-category-layout-card',
                        props: ['isLoading'],
                        template: '<div />',
                    },
                },
            },
        });

        expect(wrapper.getComponent({ name: 'sw-category-layout-card' }).props('isLoading')).toBe(true);
    });
});
