import './acl';

import './mixin/cart-notification.mixin';

import defaultSearchConfiguration from './default-search-configuration';
import DocumentV2Service from './service/documentV2.service';

/**
 * @sw-package checkout
 */

const { Application, Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-order-list', () => import('./page/sw-order-list'));
Shopwell.Component.register('sw-order-detail', () => import('./page/sw-order-detail'));
Shopwell.Component.register('sw-order-create', () => import('./page/sw-order-create'));
Shopwell.Component.register('sw-order-detail-general', () => import('./view/sw-order-detail-general'));
Shopwell.Component.register('sw-order-detail-details', () => import('./view/sw-order-detail-details'));
Shopwell.Component.register('sw-order-detail-documents', () => import('./view/sw-order-detail-documents'));
Shopwell.Component.register('sw-order-create-base', () => import('./view/sw-order-create-base'));
Shopwell.Component.register('sw-order-create-initial', () => import('./view/sw-order-create-initial'));
Shopwell.Component.register('sw-order-create-general', () => import('./view/sw-order-create-general'));
Shopwell.Component.register('sw-order-create-details', () => import('./view/sw-order-create-details'));
Shopwell.Component.register(
    'sw-order-nested-line-items-modal',
    () => import('./component/sw-order-nested-line-items-modal'),
);
Shopwell.Component.register('sw-order-nested-line-items-row', () => import('./component/sw-order-nested-line-items-row'));
Shopwell.Component.register('sw-order-line-items-grid', () => import('./component/sw-order-line-items-grid'));
Shopwell.Component.register(
    'sw-order-line-items-grid-sales-channel',
    () => import('./component/sw-order-line-items-grid-sales-channel'),
);
Shopwell.Component.register('sw-order-delivery-metadata', () => import('./component/sw-order-delivery-metadata'));
Shopwell.Component.register('sw-order-customer-comment', () => import('./component/sw-order-customer-comment'));
Shopwell.Component.register('sw-order-product-select', () => import('./component/sw-order-product-select'));
Shopwell.Component.register('sw-order-saveable-field', () => import('./component/sw-order-saveable-field'));
Shopwell.Component.register('sw-order-address-modal', () => import('./component/sw-order-address-modal'));
Shopwell.Component.register('sw-order-address-selection', () => import('./component/sw-order-address-selection'));
Shopwell.Component.register('sw-order-leave-page-modal', () => import('./component/sw-order-leave-page-modal'));
Shopwell.Component.register(
    'sw-order-save-changes-beforehand-modal',
    () => import('./component/sw-order-save-changes-beforehand-modal'),
);
Shopwell.Component.register(
    'sw-order-state-change-modal-attach-documents',
    () => import('./component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents'),
);
Shopwell.Component.register('sw-order-state-history-card', () => import('./component/sw-order-state-history-card'));
Shopwell.Component.register(
    'sw-order-state-history-card-entry',
    () => import('./component/sw-order-state-history-card-entry'),
);
Shopwell.Component.register('sw-order-state-history-modal', () => import('./component/sw-order-state-history-modal'));
Shopwell.Component.register('sw-order-state-change-modal', () => import('./component/sw-order-state-change-modal'));
Shopwell.Component.register('sw-order-state-select-v2', () => import('./component/sw-order-state-select-v2'));
Shopwell.Component.register('sw-order-details-state-card', () => import('./component/sw-order-details-state-card'));
Shopwell.Component.register('sw-order-inline-field', () => import('./component/sw-order-inline-field'));

/**
 * @deprecated tag:v6.8.0 - File will be removed. No longer used.
 */
Shopwell.Component.register('sw-order-user-card', () => import('./component/sw-order-user-card'));
Shopwell.Component.register('sw-order-document-card', () => import('./component/sw-order-document-card'));
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.register(
    'sw-order-document-settings-modal',
    () => import('./component/sw-order-document-settings-modal'),
);
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.extend(
    'sw-order-document-settings-invoice-modal',
    'sw-order-document-settings-modal',
    () => import('./component/sw-order-document-settings-invoice-modal'),
);
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.extend(
    'sw-order-document-settings-storno-modal',
    'sw-order-document-settings-modal',
    () => import('./component/sw-order-document-settings-storno-modal'),
);
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.extend(
    'sw-order-document-settings-delivery-note-modal',
    'sw-order-document-settings-modal',
    () => import('./component/sw-order-document-settings-delivery-note-modal'),
);
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.extend(
    'sw-order-document-settings-credit-note-modal',
    'sw-order-document-settings-modal',
    () => import('./component/sw-order-document-settings-credit-note-modal'),
);
Shopwell.Component.register('sw-order-create-details-header', () => import('./component/sw-order-create-details-header'));
Shopwell.Component.register('sw-order-create-details-body', () => import('./component/sw-order-create-details-body'));
Shopwell.Component.register('sw-order-create-details-footer', () => import('./component/sw-order-create-details-footer'));
Shopwell.Component.register('sw-order-create-address-modal', () => import('./component/sw-order-create-address-modal'));
Shopwell.Component.register('sw-order-new-customer-modal', () => import('./component/sw-order-new-customer-modal'));
Shopwell.Component.register('sw-order-promotion-field', () => import('./component/sw-order-promotion-field'));
Shopwell.Component.extend(
    'sw-order-promotion-tag-field',
    'sw-tagged-field',
    () => import('./component/sw-order-promotion-tag-field'),
);
Shopwell.Component.register(
    'sw-order-create-invalid-promotion-modal',
    () => import('./component/sw-order-create-invalid-promotion-modal'),
);
Shopwell.Component.register('sw-order-create-promotion-modal', () => import('./component/sw-order-create-promotion-modal'));
Shopwell.Component.register('sw-order-create-general-info', () => import('./component/sw-order-create-general-info'));
/**
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
Shopwell.Component.register(
    'sw-order-select-document-type-modal',
    () => import('./component/sw-order-select-document-type-modal'),
);
Shopwell.Component.register('sw-order-general-info', () => import('./component/sw-order-general-info'));
Shopwell.Component.register('sw-order-send-document-modal', () => import('./component/sw-order-send-document-modal'));
Shopwell.Component.register('sw-order-create-initial-modal', () => import('./component/sw-order-create-initial-modal'));
Shopwell.Component.register('sw-order-customer-grid', () => import('./component/sw-order-customer-grid'));
Shopwell.Component.register('sw-order-create-options', () => import('./component/sw-order-create-options'));
Shopwell.Component.register('sw-order-create-document-modal', () => import('./component/sw-order-create-document-modal'));
Shopwell.Component.register('sw-order-upload-document-modal', () => import('./component/sw-order-upload-document-modal'));
Shopwell.Component.register(
    'sw-order-customer-address-select',
    () => import('./component/sw-order-customer-address-select'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-order', {
    type: 'core',
    name: 'order',
    title: 'sw-order.general.mainMenuItemGeneral',
    description: 'sw-order.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-purple-default)',
    icon: 'regular-shopping-bag',
    favicon: 'icon-module-orders.svg',
    entity: 'order',

    routes: {
        index: {
            components: {
                default: 'sw-order-list',
            },
            path: 'index',
            meta: {
                privilege: 'order.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-order-create',
            path: 'create',
            redirect: {
                name: 'sw.order.create.initial',
            },
            meta: {
                privilege: 'order.creator',
            },
            children: orderCreateChildren(),
        },

        detail: {
            component: 'sw-order-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.order.detail.general',
            },
            meta: {
                privilege: 'order.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            children: orderDetailChildren(),
            props: {
                default: ($route) => {
                    return { orderId: $route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-order',
            label: 'sw-order.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-purple-default)',
            icon: 'regular-shopping-bag',
            position: 30,
            privilege: 'order.viewer',
        },
        {
            path: 'sw.order.index',
            label: 'sw-order.general.mainMenuItemList',
            parent: 'sw-order',
            privilege: 'order.viewer',
        },
    ],

    defaultSearchConfiguration,
});

function orderDetailChildren() {
    return {
        general: {
            component: 'sw-order-detail-general',
            path: 'general',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
        details: {
            component: 'sw-order-detail-details',
            path: 'details',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
        documents: {
            component: 'sw-order-detail-documents',
            path: 'documents',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.viewer',
            },
        },
    };
}

function orderCreateChildren() {
    return {
        initial: {
            component: 'sw-order-create-initial',
            path: 'initial',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.creator',
            },
        },
        general: {
            component: 'sw-order-create-general',
            path: 'general',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.creator',
            },
        },
        details: {
            component: 'sw-order-create-details',
            path: 'details',
            meta: {
                parentPath: 'sw.order.index',
                privilege: 'order.creator',
            },
        },
    };
}

Application.addServiceProvider('documentV2Service', (container) => {
    return new DocumentV2Service(container.documentV2ApiService);
});
