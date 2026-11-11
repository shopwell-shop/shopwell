/**
 * @sw-package discovery
 */

import template from './sw-admin-menu-extension.html.twig';

const { Component } = Shopwell;

Component.override('sw-admin-menu', {
    template,

    inject: ['acl'],

    computed: {
        layoutMenuEntries() {
            const entries = this.$super('layoutMenuEntries');
            if (!this.isHorizontal || !this.canViewSalesChannels) {
                return entries;
            }

            const module = Shopwell.Module.getModuleByEntityName('sales_channel')?.manifest;
            const channelEntry = {
                id: 'sw-sales-channel-layout',
                path: 'sw.sales.channel.list',
                label: 'sw-sales-channel.general.titleMenuItems',
                icon: module?.icon || 'regular-storefront',
                color: module?.color,
                customPanel: true,
                level: 1,
                children: [],
            };
            const settingsIndex = entries.findIndex((entry) => entry.path === 'sw.settings.index');
            const position = settingsIndex < 0 ? entries.length : settingsIndex;
            return [...entries.slice(0, position), channelEntry, ...entries.slice(position)];
        },

        isSalesChannelActive() {
            return this.$route.meta?.$module?.entity === 'sales_channel';
        },

        hasLayoutSidebar() {
            return (
                this.$super('hasLayoutSidebar') ||
                (this.isHorizontal && this.layoutMode === 'mixed' && this.canViewSalesChannels && this.isSalesChannelActive)
            );
        },

        canViewSalesChannels() {
            return this.acl.can('sales_channel.viewer');
        },
    },
});
