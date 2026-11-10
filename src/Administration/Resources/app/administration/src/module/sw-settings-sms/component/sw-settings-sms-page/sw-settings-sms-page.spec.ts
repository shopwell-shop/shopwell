/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { Component } from 'vue';

describe('SMS page layout', () => {
    it('keeps navigation on list pages and provides a return action on detail pages', async () => {
        const push = jest.fn();
        const wrapper = mount(
            (await wrapTestComponent('sw-settings-sms-page', { sync: true })) as Component<{
                activeSection: string;
                backRoute?: string;
            }>,
            {
                props: { activeSection: 'gateway' },
                slots: { content: '<div class="page-content">Content</div>' },
                global: {
                    mocks: { $router: { push } },
                    stubs: {
                        'sw-page': { template: '<div><slot name="smart-bar-actions"/><slot name="content"/></div>' },
                        'sw-card-view': { template: '<main><slot/></main>' },
                        'sw-settings-sms-section-tabs': true,
                        'mt-button': { template: '<button><slot/></button>' },
                    },
                },
            },
        );

        expect(wrapper.findComponent({ name: 'sw-settings-sms-section-tabs' }).exists()).toBe(true);
        expect(wrapper.find('.page-content').exists()).toBe(true);
        await wrapper.setProps({ backRoute: 'sw.settings.sms.gateway' });
        expect(wrapper.findComponent({ name: 'sw-settings-sms-section-tabs' }).exists()).toBe(false);
        await wrapper.get('.sw-settings-sms-page__cancel').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.sms.gateway' });
        wrapper.unmount();
    });
});
