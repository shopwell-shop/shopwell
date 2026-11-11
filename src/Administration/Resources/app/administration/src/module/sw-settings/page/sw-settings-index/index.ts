/**
 * @sw-package framework
 */
import { buildSettingsNavigation, type SettingsNavigationItem } from 'src/app/service/settings-menu.helper';
import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

const { hasOwnProperty } = Shopwell.Utils.object;

type SettingsItemHere = SettingsNavigationItem;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['acl', 'feature'],

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
            hideSettingRenameBanner: true,
            searchQuery: '',
        };
    },

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    created() {
        void Shopwell.Store.get('adminMenuConfig').loadVisibleTree();
        if (!Shopwell.Feature.isActive('v6.8.0.0')) {
            void this.getUserConfig();
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        /**
         * Dismissible change notices shown at the top of the settings overview. Each entry carries the
         * `deprecationVersion` it becomes obsolete with. Once that version is reached, DELETE the entry
         * here — it is no longer relevant.
         */
        changeNotices(): { key: string; deprecationVersion: string }[] {
            return [
                {
                    key: 'sw-settings.index.textSettingRenameBanner',
                    deprecationVersion: 'v6.8.0.0',
                },
            ];
        },

        configuredSettingsGroups(): Record<string, SettingsNavigationItem[]> {
            const store = Shopwell.Store.get('adminMenuConfig');
            return buildSettingsNavigation(
                Shopwell.Store.get('settingsItems').settingsGroups,
                store.visibleTree,
                { managedKeys: store.isVisibleLoadFailed ? [] : store.managedKeys, allowUnmanaged: store.allowUnmanaged },
                Shopwell.Store.get('adminMenu').adminModuleNavigation,
            );
        },

        settingsGroups() {
            // Helpers
            const labelOfSetting = (setting: SettingsItemHere) =>
                typeof setting.label === 'string' ? setting.label : (setting.label?.label ?? '');

            const mapSettings =
                (
                    mapper: (settings: SettingsItemHere[], groupName: string) => SettingsItemHere[],
                ): ((entry: [string, SettingsItemHere[]]) => [string, SettingsItemHere[]]) =>
                ([name, settings]) => [name, mapper(settings, name)];

            const filterGroup =
                (
                    predicate: (settings: SettingsItemHere[], groupName: string) => boolean,
                ): ((entry: [string, SettingsItemHere[]]) => boolean) =>
                ([name, settings]) =>
                    predicate(settings, name);

            // Mappers
            const onlySearchResults = mapSettings((settings, groupName) => {
                // if group name is queried => full group
                if (this.itemIsQueried(this.getGroupLabel(groupName))) {
                    return settings;
                }

                // try match each settings label
                return settings.filter((setting) => this.itemIsQueried(this.getLabel(setting)));
            });

            const onlyPrivilegedSettings = mapSettings((settings) =>
                settings.filter((setting) => {
                    if (
                        setting.inheritedMenuPrivileges?.some((privilege) => !this.acl.can(privilege)) ||
                        (setting.menuPrivilege && !this.acl.can(setting.menuPrivilege))
                    ) {
                        return false;
                    }
                    if (!setting.privilege) {
                        return true;
                    }
                    return this.acl.can(setting.privilege);
                }),
            );

            const sortSettings = mapSettings((settings) =>
                settings.sort((a, b) => {
                    if (a.position !== undefined || b.position !== undefined) {
                        return (a.position ?? Number.MAX_SAFE_INTEGER) - (b.position ?? Number.MAX_SAFE_INTEGER);
                    }
                    const labelA = labelOfSetting(a);
                    const labelB = labelOfSetting(b);

                    return this.$t(labelA).localeCompare(this.$t(labelB));
                }),
            );

            // Filters
            const removeEmptyGroups = filterGroup((settings) => settings.length > 0);

            // Doing: Transform the settings
            const settingsGroups = this.configuredSettingsGroups;

            return Object.fromEntries(
                Object.entries(settingsGroups)
                    .map(onlyPrivilegedSettings)
                    .map(onlySearchResults)
                    .map(sortSettings)
                    .filter(removeEmptyGroups),
            );
        },
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async getUserConfig() {
            const config = (await Shopwell.Service('userConfigService').search(['settings.hideRenameBanner']))?.data?.[
                'settings.hideRenameBanner'
            ] as { value?: boolean } | undefined;

            this.hideSettingRenameBanner = !!config?.value;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        async onCloseSettingRenameBanner() {
            this.hideSettingRenameBanner = true;

            await Shopwell.Service('userConfigService').upsert({
                'settings.hideRenameBanner': {
                    value: true,
                },
            });
        },

        hasPluginConfig() {
            return hasOwnProperty(this.settingsGroups, 'plugins') && this.settingsGroups.plugins.length > 0;
        },

        getRouteConfig(settingsItem: SettingsItemHere) {
            if (!hasOwnProperty(settingsItem, 'to')) {
                return {};
            }

            if (typeof settingsItem.to === 'string') {
                return { name: settingsItem.to };
            }

            if (typeof settingsItem.to === 'object') {
                return settingsItem.to;
            }

            return {};
        },

        getLabel(settingsItem: SettingsItemHere) {
            if (!hasOwnProperty(settingsItem, 'label')) {
                return '';
            }

            if (typeof settingsItem.label === 'string') {
                return this.$t(settingsItem.label);
            }

            if (typeof settingsItem.label !== 'object') {
                return '';
            }

            if (!hasOwnProperty(settingsItem.label, 'translated')) {
                return '';
            }

            if (!hasOwnProperty(settingsItem.label, 'label') || typeof settingsItem.label.label !== 'string') {
                return '';
            }

            if (settingsItem.label.translated) {
                return settingsItem.label.label;
            }

            return this.$t(settingsItem.label.label);
        },

        getGroupLabel(settingsGroup: string) {
            const title = this.configuredSettingsGroups[settingsGroup]?.find((item) => item.groupTitle)?.groupTitle;
            if (title) {
                return this.$te(title) ? this.$t(title) : title;
            }
            const upper = settingsGroup.charAt(0).toUpperCase() + settingsGroup.slice(1);
            const key = `sw-settings.index.tab${upper}`;
            return this.$te(key) ? this.$t(key) : settingsGroup;
        },

        itemIsQueried(label: string) {
            const query = this.searchQuery.trim().toLowerCase();
            const item = label.trim().toLowerCase();
            if (query === '') {
                return true;
            }
            return item.includes(query) || query.includes(item);
        },
    },
});
