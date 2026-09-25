/**
 * @sw-package framework
 */
import { watch } from 'vue';
/* Is covered by E2E tests */
import { publish } from '@shopwell-ag/meteor-admin-sdk/es/channel';
import '../store/context.store';
import useSession from '../composables/use-session';
import useTheme from '../composables/use-theme';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeContext(): void {
    // Handle incoming context requests from the ExtensionAPI
    Shopwell.ExtensionAPI.handle('contextCurrency', () => {
        return {
            systemCurrencyId: Shopwell.Context.app.systemCurrencyId ?? '',
            systemCurrencyISOCode: Shopwell.Context.app.systemCurrencyISOCode ?? '',
        };
    });

    Shopwell.ExtensionAPI.handle('contextEnvironment', () => {
        return Shopwell.Context.app.environment ?? 'production';
    });

    Shopwell.ExtensionAPI.handle('contextLanguage', () => {
        return {
            languageId: Shopwell.Context.api.languageId ?? '',
            systemLanguageId: Shopwell.Context.api.systemLanguageId ?? '',
        };
    });

    Shopwell.ExtensionAPI.handle('contextLocale', () => {
        return {
            fallbackLocale: Shopwell.Context.app.fallbackLocale ?? '',
            locale: Shopwell.Store.get('session').currentLocale ?? '',
        };
    });

    Shopwell.ExtensionAPI.handle('contextShopwellVersion', () => {
        return Shopwell.Context.app.config.version ?? '';
    });

    Shopwell.ExtensionAPI.handle('contextTheme', () => {
        return useTheme().resolvedTheme.value;
    });

    Shopwell.ExtensionAPI.handle('contextUserTimezone', () => {
        return Shopwell.Store.get('session').currentUser?.timeZone ?? 'UTC';
    });

    Shopwell.ExtensionAPI.handle('contextModuleInformation', (_, additionalInformation) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            return {
                modules: [],
            };
        }

        const modules = Shopwell.Store.get('extensionSdkModules').getRegisteredModuleInformation(
            extension.baseUrl,
        ) as Array<{
            displaySearchBar: boolean;
            heading: string;
            id: string;
            locationId: string;
        }>;

        return {
            modules,
        };
    });

    Shopwell.ExtensionAPI.handle('contextIsService', (_, { _event_ }) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        return extension?.sourceType === 'service';
    });

    Shopwell.ExtensionAPI.handle('contextUserInformation', (_, { _event_ }) => {
        const appOrigin = _event_.origin;
        const extension = Object.entries(Shopwell.Store.get('extensions').extensionsState).find((ext) => {
            return ext[1].baseUrl.startsWith(appOrigin);
        });

        if (!extension) {
            return Promise.reject(new Error(`Could not find a extension with the given event origin "${_event_.origin}"`));
        }

        if (!(extension[1]?.permissions?.read as string[])?.includes('user')) {
            return Promise.reject(new Error(`Extension "${extension[0]}" does not have the permission to read users`));
        }

        const currentUser = Shopwell.Store.get('session').currentUser;

        return Promise.resolve({
            aclRoles: currentUser?.aclRoles as unknown as Array<{
                name: string;
                type: string;
                id: EntityKey<'acl_role'>;
                privileges: Array<string>;
            }>,
            active: !!currentUser?.active,
            admin: !!currentUser?.admin,
            avatarId: currentUser?.avatarId ?? '',
            email: currentUser?.email ?? '',
            firstName: currentUser?.firstName ?? '',
            id: currentUser?.id ?? '',
            lastName: currentUser?.lastName ?? '',
            localeId: currentUser?.localeId ?? '',
            title: currentUser?.title ?? '',
            // @ts-expect-error - type is not defined in entity directly
            type: (currentUser?.type as unknown as string) ?? '',
            username: currentUser?.username ?? '',
        });
    });

    Shopwell.ExtensionAPI.handle('contextAppInformation', (_, { _event_ }) => {
        const appOrigin = _event_.origin;
        const extensionEntry = Object.entries(Shopwell.Store.get('extensions').extensionsState).find((ext) => {
            return ext[1].baseUrl.startsWith(appOrigin);
        });

        if (extensionEntry === undefined) {
            return {
                name: 'unknown',
                type: 'app' as const,
                version: '0.0.0',
                inAppPurchases: [],
                privileges: {},
            };
        }

        const [extensionName, extension] = extensionEntry;

        return {
            name: extensionName,
            type: extension.type,
            version: extension.version ?? '',
            inAppPurchases: Shopwell.InAppPurchase.getByExtension(extension.name),
            privileges: extension.permissions,
        };
    });

    const contextStore = Shopwell.Store.get('context');

    watch(
        () => {
            return {
                languageId: contextStore.api.languageId,
                systemLanguageId: contextStore.api.systemLanguageId,
            };
        },
        ({ languageId, systemLanguageId }, { languageId: oldLanguageId, systemLanguageId: oldSystemLanguageId }) => {
            if (languageId === oldLanguageId && systemLanguageId === oldSystemLanguageId) {
                return;
            }

            void publish('contextLanguage', {
                languageId: languageId ?? '',
                systemLanguageId: systemLanguageId ?? '',
            });
        },
    );

    watch(
        () => {
            return {
                fallbackLocale: contextStore.app.fallbackLocale,
            };
        },
        ({ fallbackLocale }, { fallbackLocale: oldFallbackLocale }) => {
            if (fallbackLocale === oldFallbackLocale) {
                return;
            }

            void publish('contextLocale', {
                locale: Shopwell.Store.get('session').currentLocale ?? '',
                fallbackLocale: fallbackLocale ?? '',
            });
        },
    );

    Shopwell.Vue.watch(useSession().currentLocale, (locale) => {
        void publish('contextLocale', {
            locale: locale ?? '',
            fallbackLocale: contextStore.app.fallbackLocale ?? '',
        });
    });

    watch(useTheme().resolvedTheme, (resolvedTheme) => {
        void publish('contextTheme', resolvedTheme);
    });

    Shopwell.ExtensionAPI.handle('windowGetId', () => {
        if (!contextStore.app.windowId) {
            contextStore.app.windowId = Shopwell.Utils.createId();
        }

        return contextStore.app.windowId;
    });

    Shopwell.ExtensionAPI.handle('contextShopId', () => {
        return contextStore.app.config.shopId;
    });
}
