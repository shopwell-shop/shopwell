/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

let activeWrapper: VueWrapper | null = null;

async function createWrapper(value: string | null): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-payment-identifier', { sync: true }), {
        props: {
            value,
        },
        global: {
            stubs: {
                'mt-icon': {
                    props: ['name'],
                    template: '<i :class="`icon-${name}`"></i>',
                },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return wrapper;
}

describe('module/sw-payment/component/sw-payment-identifier', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('should render the identifier as selectable text', async () => {
        const wrapper = await createWrapper('2026010222001123456789');

        expect(wrapper.find('.sw-payment-identifier__value').text()).toBe('2026010222001123456789');
        expect(wrapper.find('.sw-payment-identifier__copy').exists()).toBe(true);
    });

    it('should show a dash and no button when the payment has no such number', async () => {
        const wrapper = await createWrapper(null);

        expect(wrapper.find('.sw-payment-identifier__value').text()).toBe('–');
        expect(wrapper.find('.sw-payment-identifier__copy').exists()).toBe(false);
    });

    it('should copy the value as it is and confirm it briefly', async () => {
        jest.useFakeTimers();

        const writeText = jest.fn(() => Promise.resolve());

        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });

        const wrapper = await createWrapper('PAY-1');

        await wrapper.find('.sw-payment-identifier__copy').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('PAY-1');
        expect(wrapper.find('.icon-regular-checkmark').exists()).toBe(true);

        jest.advanceTimersByTime(1500);
        await flushPromises();

        expect(wrapper.find('.icon-regular-copy').exists()).toBe(true);

        jest.useRealTimers();
    });

    /**
     * A browser that denies the clipboard is not an error the operator has to act on - the value is
     * still on screen, so the page only declines to flash the confirmation.
     */
    it('should stay quiet when the browser refuses the clipboard', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: () => Promise.reject(new Error('denied')),
            },
            configurable: true,
        });

        const wrapper = await createWrapper('PAY-1');

        await wrapper.find('.sw-payment-identifier__copy').trigger('click');
        await flushPromises();

        expect(wrapper.find('.icon-regular-copy').exists()).toBe(true);
        expect(wrapper.find('.icon-regular-checkmark').exists()).toBe(false);
        expect(wrapper.find('.sw-payment-identifier__value').text()).toBe('PAY-1');
    });
});
