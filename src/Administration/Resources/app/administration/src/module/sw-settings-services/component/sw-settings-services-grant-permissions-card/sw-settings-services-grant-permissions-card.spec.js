import { mount } from '@vue/test-utils';
import SwSettingsServicesGrantPermissionsCard from './index';
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

describe('src/module/sw-settings-services/component/sw-settings-services-permissions-card', () => {
    beforeAll(() => {
        Shopwell.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(() => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'user-id',
                    grantedAt: '2025-07-08',
                },
            })),
        }));
    });

    it('has a linkt to docs page', async () => {
        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
            },
        });

        expect(permissionsCard.get('a').attributes('href')).toBe(
            'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
        );
    });

    it('send permissions accepted request', async () => {
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const shopwareServicesStore = useShopwellServicesStore();
        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {},
                },
            ],
        };

        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
            },
        });

        await permissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Shopwell.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const notificationStore = Shopwell.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const shopwareServicesStore = useShopwellServicesStore();
        shopwareServicesStore.revisions = null;

        const permissionsCard = await mount(SwSettingsServicesGrantPermissionsCard, {
            props: {
                docsLink: 'https://docs.shopwell.cn/en/shopware-6-en/shopware-services',
            },
        });

        await permissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(permissionsCard.emitted('service-permissions-granted')).toBeUndefined();
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
