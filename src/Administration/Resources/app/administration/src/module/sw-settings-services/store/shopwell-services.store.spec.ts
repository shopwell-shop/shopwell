import { createPinia, setActivePinia } from 'pinia';
import { useShopwellServicesStore } from './shopwell-services.store';

describe('src/module/sw-settings-services/store/shopwell-services.store.ts', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('initializes the store with initial state', () => {
        const shopwellServicesStore = useShopwellServicesStore();

        expect(shopwellServicesStore.config).toBeNull();
        expect(shopwellServicesStore.revisions).toBeNull();
        expect(shopwellServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it.each([
        [undefined, null, false],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id' as EntityKey<'user'>,
                grantedAt: '2025-07-08T00:00:00Z',
            },
            null,
            false,
        ],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id' as EntityKey<'user'>,
                grantedAt: '2025-07-08T00:00:00Z',
            },
            {
                'latest-revision': '2025-08-08',
                'available-revisions': [],
            },
            false,
        ],
        [
            {
                identifier: 'id',
                revision: '2025-07-08',
                consentingUserId: 'user-id' as EntityKey<'user'>,
                grantedAt: '2025-07-08T00:00:00Z',
            },
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [],
            },
            true,
        ],
    ])('determines the consent given state', (permissionsConsent, revisions, isConsentGiven) => {
        const shopwellServicesStore = useShopwellServicesStore();

        shopwellServicesStore.config = { permissionsConsent };
        shopwellServicesStore.revisions = revisions;

        expect(shopwellServicesStore.consentGiven).toBe(isConsentGiven);
    });

    it.each([
        [null, null],
        [
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [],
            },
            null,
        ],
        [
            {
                'latest-revision': '2025-07-08',
                'available-revisions': [
                    {
                        revision: '2025-07-08',
                        links: {
                            'feedback-url': 'https://example.com/feedback',
                            'docs-url': 'https://example.com/docs',
                            'tos-url': 'https://example.com/tos',
                        },
                    },
                    {
                        revision: '2025-01-01',
                        links: {
                            'feedback-url': 'https://example.com/feedback',
                            'docs-url': 'https://example.com/docs',
                            'tos-url': 'https://example.com/tos',
                        },
                    },
                ],
            },
            {
                revision: '2025-07-08',
                links: {
                    'feedback-url': 'https://example.com/feedback',
                    'docs-url': 'https://example.com/docs',
                    'tos-url': 'https://example.com/tos',
                },
            },
        ],
    ])('determines the current permissions revision', (revisions, currentRevision) => {
        const shopwellServicesStore = useShopwellServicesStore();

        shopwellServicesStore.revisions = revisions;

        expect(shopwellServicesStore.currentRevision).toEqual(currentRevision);
    });
});
