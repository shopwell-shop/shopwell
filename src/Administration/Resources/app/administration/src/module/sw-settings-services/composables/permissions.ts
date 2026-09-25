/**
 * @sw-package framework
 */
import type { HandleMethod } from '@shopwell-ag/meteor-admin-sdk/es/channel';
import useSession from 'src/app/composables/use-session';
import { useShopwellServicesStore } from '../store/shopwell-services.store';

let reloadFn: () => void = () => window.location.reload();

/**
 * Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26).
 * @private
 */
export function _reloadPage() {
    reloadFn();
}

/**
 * For testing only.
 * @private
 */
export function __setReloadFn(fn: () => void) {
    reloadFn = fn;
}

/**
 * @private
 */
export async function grantPermissions() {
    const shopwellServiceStore = useShopwellServicesStore();
    let currentRevision = shopwellServiceStore.currentRevision?.revision;

    if (!currentRevision) {
        const sessionStore = useSession();
        const revisionData = await Shopwell.Service('serviceRegistryClient').getCurrentRevision(
            sessionStore.currentLocale.value ?? 'en-GB',
        );

        shopwellServiceStore.revisions = revisionData;
        currentRevision = shopwellServiceStore.currentRevision?.revision;
    }

    if (!currentRevision) {
        throw new Error('No revision available');
    }

    await Shopwell.Service('shopwellServicesService').acceptRevision(currentRevision);

    _reloadPage();
}

/**
 * @private
 */
export async function revokePermissions() {
    await Shopwell.Service('shopwellServicesService').revokePermissions();

    _reloadPage();
}

function assertServiceOrigin(origin: string): void {
    const matchingExtensions = Object.values(Shopwell.Store.get('extensions').extensionsState).filter((extension) => {
        try {
            return new URL(extension.baseUrl).origin === origin;
        } catch {
            return false;
        }
    });

    if (matchingExtensions.length === 0 || !matchingExtensions.every((extension) => extension.sourceType === 'service')) {
        throw new Error('Only Shopwell Services can access this handler.');
    }
}

/**
 * @private
 */
export const grantPermissionsFromSdk: HandleMethod<'servicePermissionGrant'> = (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    return grantPermissions();
};

/**
 * Resolves to `true` when the Shopwell Services consent is already granted or not needed,
 * i.e. the latest revision has been consented to, or Shopwell Services are disabled.
 *
 * @private
 */
export const isPermissionGrantedFromSdk: HandleMethod<'servicePermissionIsGranted'> = async (_message, { _event_ }) => {
    assertServiceOrigin(_event_.origin);

    const shopwellServicesStore = useShopwellServicesStore();

    if (!shopwellServicesStore.config) {
        shopwellServicesStore.config = await Shopwell.Service('shopwellServicesService').getServicesContext();
    }

    if (shopwellServicesStore.config?.disabled) {
        return true;
    }

    if (!shopwellServicesStore.revisions) {
        const locale = useSession().currentLocale.value ?? 'en-GB';
        shopwellServicesStore.revisions = await Shopwell.Service('serviceRegistryClient').getCurrentRevision(locale);
    }

    return shopwellServicesStore.consentGiven;
};
