/**
 * @sw-package framework
 */
import type { RevisionData, ServicesRevision } from '../service/service-registry-client';

/**
 * @private
 */
export type PermissionsConsent = {
    identifier: string;
    revision: string;
    consentingUserId: EntityKey<'user'>;
    grantedAt: string;
};

/**
 * @private
 */
export type ServiceConfiguration = {
    permissionsConsent?: PermissionsConsent;
    disabled?: boolean;
};

type ShopwellServicesState = {
    config: ServiceConfiguration | null;
    revisions: RevisionData | null;
    showGrantPermissionsModal: boolean;
};

/**
 * @private
 *
 */
export const useShopwellServicesStore = Shopwell.Store.register('shopwareServices', {
    state: (): ShopwellServicesState => ({
        config: null,
        revisions: null,
        showGrantPermissionsModal: false,
    }),

    getters: {
        consentGiven(): boolean {
            const permissionsConsent = this.config?.permissionsConsent ?? false;

            if (permissionsConsent === false) {
                return false;
            }

            const currentRevision = this.revisions?.['latest-revision'] ?? false;

            if (currentRevision === false) {
                return false;
            }

            return currentRevision === permissionsConsent.revision;
        },
        currentRevision(): ServicesRevision | null {
            if (!this.revisions) {
                return null;
            }

            return (
                this.revisions['available-revisions'].find((revision) => {
                    return revision.revision === this.revisions!['latest-revision'];
                }) ?? null
            );
        },
    },
});
