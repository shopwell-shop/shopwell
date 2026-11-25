/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';

describe('approval status', () => {
    it.each([
        [
            'completed',
            'approved',
            'positive',
            'approved',
        ],
        [
            'completed',
            'rejected',
            'critical',
            'rejected',
        ],
        [
            'running',
            'approved',
            'info',
            'running',
        ],
        [
            'completed',
            null,
            'neutral',
            'completed',
        ],
        [
            'withdrawn',
            null,
            'neutral',
            'withdrawn',
        ],
    ])('renders %s/%s without confusing node and request outcomes', async (status, outcome, variant, label) => {
        const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-status-badge', { sync: true }), {
            props: { status, outcome },
            global: {
                stubs: { 'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot/></span>' } },
            },
        });
        expect(wrapper.text()).toBe(`sw-approval-flow.status.${label}`);
        expect(wrapper.find('span').attributes('data-variant')).toBe(variant);
    });
});
