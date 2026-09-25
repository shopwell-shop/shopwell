import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsModal from './index';
import { useShopwellServicesStore } from '../../store/shopware-services.store';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useShopwellServicesStore = require('../../store/shopware-services.store').useShopwellServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwellServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopwell.Service('shopwareServicesService').acceptRevision(revision);
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

        Shopwell.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
        }));
    });

    it('can be opened by the pinia store', async () => {
        const shopwareServicesStore = useShopwellServicesStore();
        expect(shopwareServicesStore.revisions).toBeNull();

        const grantPermissionsModal = await createWrapper();
        const modal = grantPermissionsModal.getComponent(MtModal);

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();

        expect(shopwareServicesStore.revisions).toEqual({
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
        expect(shopwareServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it('sends grant permissions request', async () => {
        const shopwareServicesStore = useShopwellServicesStore();
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopwell.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const shopwareServicesStore = useShopwellServicesStore();
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        shopwareServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        shopwareServicesStore.revisions = null;

        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(Shopwell.Service('shopwareServicesService').acceptRevision).not.toHaveBeenCalled();
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
