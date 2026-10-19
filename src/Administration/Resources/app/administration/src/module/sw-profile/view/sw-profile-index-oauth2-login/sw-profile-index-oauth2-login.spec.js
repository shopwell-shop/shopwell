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
                    template: '<div class="mt-modal-root"><slot></slot></div>',
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
        expect(wrapper.vm.isLoading).toBe(false);

        const rows = wrapper.findAll('.sw-profile-index-oauth2-login__binding');

        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('["sso.oauth2.channel.google"]');
        expect(rows[1].text()).toContain('["sso.oauth2.channel.wechat"]');

        // Only a channel the administrator is linked to offers the removal.
        const unbindButtons = wrapper.findAll('.sw-profile-index-oauth2-login__binding-unbind');

        expect(unbindButtons).toHaveLength(1);
        expect(wrapper.vm.bindings[0].linked).toBe(true);
        expect(wrapper.vm.bindings[1].linked).toBe(false);
    });

    it('should tell the administrator when the shop offers no channel at all', async () => {
        const { wrapper } = await createWrapper({ bindings: [] });

        expect(wrapper.vm.isLoading).toBe(false);

        expect(wrapper.find('.sw-profile-index-oauth2-login__empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-profile-index-oauth2-login__bindings').exists()).toBe(false);
    });

    it('should resolve the label of a channel through its snippet and fall back to the technical name', async () => {
        const { wrapper } = await createWrapper({ $te: () => false });

        expect(wrapper.vm.channelLabel(googleBinding)).toBe('google');
        expect(wrapper.vm.channelLabel({ technicalName: 'wechat', labelSnippet: null })).toBe('wechat');

        const { wrapper: translatedWrapper } = await createWrapper();

        expect(translatedWrapper.vm.channelLabel(googleBinding)).toBe('["sso.oauth2.channel.google"]');
    });

    it('should warn and stay usable when the bindings cannot be loaded', async () => {
        const { wrapper } = await createWrapper({ listFails: true });
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        // The failed request of `created` happened before the spy was installed, so repeat it.
        await wrapper.vm.loadBindings();

        expect(notification).toHaveBeenCalledWith({
            message: '["sw-profile.oauth2Login.notificationLoadError"]',
        });
        expect(wrapper.vm.bindings).toEqual([]);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.find('.sw-profile-index-oauth2-login__empty-state').exists()).toBe(true);
    });

    it('should open the confirmation for the chosen channel and keep the link when it is dismissed', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();

        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);

        wrapper.vm.onOpenRemoveModal(googleBinding);
        await flushPromises();

        expect(wrapper.vm.removeTarget).toEqual(googleBinding);
        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(true);

        wrapper.vm.onRemoveModalChange(true);
        await flushPromises();
        expect(wrapper.vm.removeTarget).toEqual(googleBinding);

        wrapper.vm.onRemoveModalChange(false);
        await flushPromises();

        expect(wrapper.vm.removeTarget).toBeNull();
        expect(oauth2IdentityService.unbind).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-profile-index-oauth2-login__remove-modal').exists()).toBe(false);
    });

    it('should remove the link of the chosen channel and reload the state afterwards', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();
        const success = jest.spyOn(wrapper.vm, 'createNotificationSuccess');

        wrapper.vm.onOpenRemoveModal(googleBinding);
        await wrapper.vm.onConfirmRemove();

        expect(oauth2IdentityService.unbind).toHaveBeenCalledWith('google');
        expect(success).toHaveBeenCalledWith({
            message: '["sw-profile.oauth2Login.notificationUnbindSuccess"]',
        });
        expect(wrapper.vm.removeTarget).toBeNull();
        expect(wrapper.vm.isUnbinding).toBe(false);

        // Once for `created`, once to refresh the list after the removal.
        expect(oauth2IdentityService.listBindings).toHaveBeenCalledTimes(2);
    });

    it('should keep the confirmation open and warn when the removal fails', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper({ unbindFails: true });
        const error = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.onOpenRemoveModal(googleBinding);
        await wrapper.vm.onConfirmRemove();

        expect(oauth2IdentityService.unbind).toHaveBeenCalledWith('google');
        expect(error).toHaveBeenCalledWith({
            message: '["sw-profile.oauth2Login.notificationUnbindError"]',
        });
        expect(wrapper.vm.removeTarget).toEqual(googleBinding);
        expect(wrapper.vm.isUnbinding).toBe(false);
        expect(oauth2IdentityService.listBindings).toHaveBeenCalledTimes(1);
    });

    it('should not call the server when the confirmation arrives without a channel', async () => {
        const { wrapper, oauth2IdentityService } = await createWrapper();

        await wrapper.vm.onConfirmRemove();

        expect(oauth2IdentityService.unbind).not.toHaveBeenCalled();
        expect(wrapper.vm.isUnbinding).toBe(false);
    });
});
