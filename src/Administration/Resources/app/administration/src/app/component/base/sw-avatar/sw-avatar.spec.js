/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-avatar', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-avatar', { sync: true }));
    });

    it('should change the variant to a square', async () => {
        await wrapper.setProps({
            variant: 'square',
        });

        expect(wrapper.get('span').classes()).toContain('sw-avatar__square');
    });

    it('should show the initials of the first and last word of the name', async () => {
        await wrapper.setProps({
            name: 'Max Mustermann',
        });

        expect(wrapper.get('.sw-avatar__initials').text()).toBe('MM');
    });

    it('should show a single initial when the name only contains one word', async () => {
        await wrapper.setProps({
            name: 'Max',
        });

        expect(wrapper.get('.sw-avatar__initials').text()).toBe('M');
    });

    it('should not show initials when no name is given', async () => {
        await wrapper.setProps({
            name: '',
        });

        expect(wrapper.vm.avatarInitials).toBe('');
    });
});
