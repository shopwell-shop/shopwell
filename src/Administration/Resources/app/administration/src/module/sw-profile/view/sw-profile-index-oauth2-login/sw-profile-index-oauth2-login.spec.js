/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

const googleBinding = {
    technicalName: 'google',
    labelSnippet: 'sso.oauth2.channel.google',
    icon: 'regular-globe',
    linked: true,
};

const wechatBinding = {
    technicalName: 'wechat',
    labelSnippet: 'sso.oauth2.channel.wechat',
    icon: null,
    linked: false,
};

async function createWrapper({
    bindings = [googleBinding, wechatBinding],
    listFails = false,
    unbindFails = false,
    $te = () => true,
} = {}) {
    const oauth2IdentityService = {
        listBindings: jest.fn(() => {
            if (listFails) {
                return Promise.reject(new Error('Failed to load the bindings'));
            }

            return Promise.resolve(bindings);
        }),
        unbind: jest.fn(() => {
            if (unbindFails) {
                return Promise.reject(new Error('Failed to remove the binding'));
            }

            return Promise.resolve();
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-profile-index-oauth2-login', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
                $te,
            },
            provide: {
                oauth2IdentityService,
            },
            stubs: {
                // The modal teleports into the body and traps the focus, the view only needs its slots.
                'mt-modal-root': {
                    template: `<div class="mt-modal-root"><button class="keep-open" @click="$emit('change', true)"/><button class="dismiss" @click="$emit('change', false)"/><slot></slot></div>`,
                    props: ['isOpen'],
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, oauth2IdentityService };
}

describe('src/module/sw-profile/view/sw-profile-index-oauth2-login', () => {
    it('should list every channel the shop offers and mark the linked one', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();

        expect(oauth2IdentityService.listBindings).toHaveBeenCalledTimes(1);
        expect(wrapper.getComponent({ name: 'mt-card' }).props('isLoading')).toBe(false);

        const rows = wrapper.findAll('.sw-profile-index-oauth2-login__binding');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('["sso.oauth2.channel.google"]');
        expect(rows[1].text()).toContain('["sso.oauth2.channel.wechat"]');

        // Only a channel the administrator is linked to offers the removal.
        const unbindButtons = wrapper.findAll('.sw-profile-index-oauth2-login__binding-unbind');

        expect(unbindButtons).toHaveLength(1);
        expect(rows[0].text()).toContain('["sw-profile.oauth2Login.linked"]');
        expect(rows[1].text()).toContain('["sw-profile.oauth2Login.notLinked"]');
    });

    it('should tell the administrator when the shop offers no channel at all', async () => {
        const { wrapper } = await createWrapper({ bindings: [] });

        expect(wrapper.getComponent({ name: 'mt-card' }).props('isLoading')).toBe(false);

        expect(wrapper.find('.sw-profile-index-oauth2-login__empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-profile-index-oauth2-login__bindings').exists()).toBe(false);
    });

    it('should resolve the label of a channel through its snippet and fall back to the technical name', async () => {
        const { wrapper } = await createWrapper({ $te: () => false });

        expect(wrapper.findAll('.sw-profile-index-oauth2-login__binding-label')[0].text()).toBe('google');
        expect(wrapper.findAll('.sw-profile-index-oauth2-login__binding-label')[1].text()).toBe('wechat');

        const { wrapper: translatedWrapper } = await createWrapper();

        expect(translatedWrapper.findAll('.sw-profile-index-oauth2-login__binding-label')[0].text()).toBe(
            '["sso.oauth2.channel.google"]',
        );
    });

    it('should warn and stay usable when the bindings cannot be loaded', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper({ listFails: true });

        expect(notification).toHaveBeenCalledWith(
            expect.objectContaining({
                message: '["sw-profile.oauth2Login.notificationLoadError"]',
            }),
        );
        expect(wrapper.getComponent({ name: 'mt-card' }).props('isLoading')).toBe(false);
        expect(wrapper.find('.sw-profile-index-oauth2-login__empty-state').exists()).toBe(true);
    });

    it('should open the confirmation for the chosen channel and keep the link when it is dismissed', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();

        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);

        await wrapper.get('.sw-profile-index-oauth2-login__binding-unbind').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-profile-index-oauth2-login__remove-modal').text()).toContain('sso.oauth2.channel.google');
        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(true);

        await wrapper.get('.keep-open').trigger('click');
        await flushPromises();
        expect(wrapper.get('.sw-profile-index-oauth2-login__remove-modal').text()).toContain('sso.oauth2.channel.google');

        await wrapper.get('.dismiss').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);
        expect(oauth2IdentityService.unbind).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);
    });

    it('should remove the link of the chosen channel and reload the state afterwards', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();
        const success = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('.sw-profile-index-oauth2-login__binding-unbind').trigger('click');
        await wrapper.get('mt-modal-action-stub').trigger('click');
        await flushPromises();

        expect(oauth2IdentityService.unbind).toHaveBeenCalledWith('google');
        expect(success).toHaveBeenCalledWith(
            expect.objectContaining({
                message: '["sw-profile.oauth2Login.notificationUnbindSuccess"]',
            }),
        );
        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);
        expect(wrapper.find('mt-modal-action-stub[is-loading="true"]').exists()).toBe(false);

        // Once for `created`, once to refresh the list after the removal.
        expect(oauth2IdentityService.listBindings).toHaveBeenCalledTimes(2);
    });

    it('should keep the confirmation open and warn when the removal fails', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper({ unbindFails: true });
        const error = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('.sw-profile-index-oauth2-login__binding-unbind').trigger('click');
        await wrapper.get('mt-modal-action-stub').trigger('click');
        await flushPromises();

        expect(oauth2IdentityService.unbind).toHaveBeenCalledWith('google');
        expect(error).toHaveBeenCalledWith(
            expect.objectContaining({
                message: '["sw-profile.oauth2Login.notificationUnbindError"]',
            }),
        );
        expect(wrapper.get('.sw-profile-index-oauth2-login__remove-modal').text()).toContain('sso.oauth2.channel.google');
        expect(wrapper.find('mt-modal-action-stub[is-loading="true"]').exists()).toBe(false);
        expect(oauth2IdentityService.listBindings).toHaveBeenCalledTimes(1);
    });

    it('should expose no confirmation before a channel is chosen', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();

        expect(wrapper.find('mt-modal-action-stub').exists()).toBe(false);

        expect(oauth2IdentityService.unbind).not.toHaveBeenCalled();
        expect(wrapper.find('mt-modal-action-stub[is-loading="true"]').exists()).toBe(false);
    });
});
