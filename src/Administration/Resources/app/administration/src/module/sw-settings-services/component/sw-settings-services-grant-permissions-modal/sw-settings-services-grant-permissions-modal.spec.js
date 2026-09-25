import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopwell-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsModal from './index';
import { useShopwellServicesStore } from '../../store/shopwell-services.store';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useShopwellServicesStore = require('../../store/shopwell-services.store').useShopwellServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwellServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopwell.Service('shopwellServicesService').acceptRevision(revision);
            _reloadPageMock();
        },
        revokePermissions: jest.fn(),
        _reloadPage: _reloadPageMock,
    };
});

const createWrapper = async () => {
    return mount(SwSettingsServicesGrantPermissionsModal, {
        global: {
            stubs: {
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
};

describe('src/module/sw-settings-services/component/sw-settings-services-grant-permissions-modal', () => {
    beforeAll(() => {
        Shopwell.Service().register('serviceRegistryClient', () => ({
            getCurrentRevision: jest.fn(async () => ({
                'latest-revision': '2025-06-25',
                'available-revisions': [
                    {
                        revision: '2025-06-25',
                        links: {
                            'feedback-url': 'https://shopwell.cn/feedback',
                            'docs-url': 'https://docs.shopwell.cn/services',
                            'tos-url': 'https://shopwell.cn/agb',
                        },
                    },
                ],
            })),
        }));

        Shopwell.Service().register('shopwellServicesService', () => ({
            acceptRevision: jest.fn(),
        }));
    });

    it('can be opened by the pinia store', async () => {
        const shopwellServicesStore = useShopwellServicesStore();
        expect(shopwellServicesStore.revisions).toBeNull();

        const grantPermissionsModal = await createWrapper();
        const modal = grantPermissionsModal.getComponent(MtModal);

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        shopwellServicesStore.showGrantPermissionsModal = true;
        await flushPromises();

        expect(shopwellServicesStore.revisions).toEqual({
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {
                        'feedback-url': 'https://shopwell.cn/feedback',
                        'docs-url': 'https://docs.shopwell.cn/services',
                        'tos-url': 'https://shopwell.cn/agb',
                    },
                },
            ],
        });

        await modal.getComponent(MtModalClose).trigger('click');

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);
        expect(shopwellServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it('sends grant permissions request', async () => {
        const shopwellServicesStore = useShopwellServicesStore();
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        shopwellServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopwell.Service('shopwellServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const shopwellServicesStore = useShopwellServicesStore();
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        shopwellServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        shopwellServicesStore.revisions = null;

        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(Shopwell.Service('shopwellServicesService').acceptRevision).not.toHaveBeenCalled();
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
