/**
 * @sw-package discovery
 */

import './service/export-template.service';
import './product-export-templates';
import './agentic-product-export-templates';
import './service/domain-link.service';
import './service/sales-channel-file.api.service';
import './service/sales-channel-favorites.service';
import './component/structure/sw-admin-menu-extension';
import './acl';

import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register(
    'sw-sales-channel-defaults-select',
    () => import('./component/sw-sales-channel-defaults-select'),
);
Shopwell.Component.register('sw-sales-channel-modal', () => import('./component/sw-sales-channel-modal'));
Shopwell.Component.register('sw-sales-channel-modal-grid', () => import('./component/sw-sales-channel-modal-grid'));
Shopwell.Component.register('sw-sales-channel-modal-detail', () => import('./component/sw-sales-channel-modal-detail'));
Shopwell.Component.register('sw-sales-channel-detail-domains', () => import('./component/sw-sales-channel-detail-domains'));
Shopwell.Component.register(
    'sw-sales-channel-detail-hreflang',
    () => import('./component/sw-sales-channel-detail-hreflang'),
);
Shopwell.Component.register('sw-sales-channel-detail', () => import('./page/sw-sales-channel-detail'));
Shopwell.Component.extend(
    'sw-sales-channel-create',
    'sw-sales-channel-detail',
    () => import('./page/sw-sales-channel-create'),
);
Shopwell.Component.register('sw-sales-channel-list', () => import('./page/sw-sales-channel-list'));
Shopwell.Component.register('sw-sales-channel-detail-base', () => import('./view/sw-sales-channel-detail-base'));
Shopwell.Component.register('sw-sales-channel-detail-products', () => import('./view/sw-sales-channel-detail-products'));
Shopwell.Component.register(
    'sw-sales-channel-detail-agentic-files',
    () => import('./view/sw-sales-channel-detail-agentic-files'),
);
Shopwell.Component.register(
    'sw-sales-channel-detail-agentic-file',
    () => import('./view/sw-sales-channel-detail-agentic-file'),
);
Shopwell.Component.register('sw-sales-channel-detail-analytics', () => import('./view/sw-sales-channel-detail-analytics'));
Shopwell.Component.extend(
    'sw-sales-channel-create-base',
    'sw-sales-channel-detail-base',
    () => import('./view/sw-sales-channel-create-base'),
);
Shopwell.Component.register(
    'sw-sales-channel-detail-product-comparison',
    () => import('./view/sw-sales-channel-detail-product-comparison'),
);
Shopwell.Component.register(
    'sw-sales-channel-detail-product-comparison-preview',
    () => import('./view/sw-sales-channel-detail-product-comparison-preview'),
);
Shopwell.Component.register(
    'sw-sales-channel-detail-agentic-commerce-integration',
    () => import('./view/sw-sales-channel-detail-agentic-commerce-integration'),
);
Shopwell.Component.register(
    'sw-agentic-commerce-tracking-config',
    () => import('./component/sw-agentic-commerce-tracking-config'),
);
Shopwell.Component.register(
    'sw-sales-channel-detail-product-export-insights',
    () => import('./view/sw-sales-channel-detail-product-export-insights'),
);
Shopwell.Component.register(
    'sw-sales-channel-products-assignment-modal',
    () => import('./component/sw-sales-channel-products-assignment-modal'),
);
Shopwell.Component.register(
    'sw-sales-channel-products-assignment-single-products',
    () => import('./component/sw-sales-channel-products-assignment-single-products'),
);
Shopwell.Component.register(
    'sw-sales-channel-products-assignment-dynamic-product-groups',
    () => import('./component/sw-sales-channel-products-assignment-dynamic-product-groups'),
);
Shopwell.Component.register(
    'sw-sales-channel-product-assignment-categories',
    () => import('./component/sw-sales-channel-product-assignment-categories'),
);
Shopwell.Component.register('sw-sales-channel-menu', () => import('./component/structure/sw-sales-channel-menu'));

Shopwell.Component.register('sw-sales-channel-measurement', () => import('./component/sw-sales-channel-measurement'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-sales-channel', {
    type: 'core',
    name: 'sales-channel',
    title: 'sw-sales-channel.general.titleMenuItems',
    description: 'The module for managing Sales Channels.',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-storefront',
    entity: 'sales_channel',

    searchMatcher: (regex, labelType, manifest) => {
        const match = labelType.toLowerCase().match(regex);

        if (!match) {
            return false;
        }

        return [
            {
                name: manifest.name,
                icon: manifest.icon,
                color: manifest.color,
                label: labelType,
                entity: manifest.entity,
                route: manifest.routes.list,
                privilege: manifest.routes.list?.meta.privilege,
            },
        ];
    },

    routes: {
        detail: {
            component: 'sw-sales-channel-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.sales.channel.list',
                privilege: 'sales_channel.viewer',
            },
            redirect: {
                name: 'sw.sales.channel.detail.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                products: {
                    component: 'sw-sales-channel-detail-products',
                    path: 'products',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                agenticFiles: {
                    component: 'sw-sales-channel-detail-agentic-files',
                    path: 'agentic-files',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                agenticFile: {
                    component: 'sw-sales-channel-detail-agentic-file',
                    path: 'agentic-files/:fileName(.*)',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                productComparison: {
                    component: 'sw-sales-channel-detail-product-comparison',
                    path: 'product-comparison',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                analytics: {
                    component: 'sw-sales-channel-detail-analytics',
                    path: 'analytics',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                agenticCommerceIntegration: {
                    component: 'sw-sales-channel-detail-agentic-commerce-integration',
                    path: 'agentic-commerce-integration',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
                productExportInsights: {
                    component: 'sw-sales-channel-detail-product-export-insights',
                    path: 'product-export-insights',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.viewer',
                    },
                },
            },
        },

        create: {
            component: 'sw-sales-channel-create',
            path: 'create/:typeId',
            redirect: {
                name: 'sw.sales.channel.create.base',
            },
            children: {
                base: {
                    component: 'sw-sales-channel-create-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.sales.channel.list',
                        privilege: 'sales_channel.creator',
                    },
                },
            },
        },

        list: {
            component: 'sw-sales-channel-list',
            path: 'list',
            meta: {
                privilege: 'sales_channel.viewer',
            },
        },
    },

    defaultSearchConfiguration,
});
