/**
 * @sw-package framework
 */
import './init/services.init';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-bulk-edit-product', () => import('./page/sw-bulk-edit-product'));
Shopwell.Component.register('sw-bulk-edit-order', () => import('./page/sw-bulk-edit-order'));
Shopwell.Component.register('sw-bulk-edit-customer', () => import('./page/sw-bulk-edit-customer'));
Shopwell.Component.register(
    'sw-bulk-edit-order-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents'),
);
Shopwell.Component.register(
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-order-documents-generate-cancellation-invoice',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-cancellation-invoice'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-order-documents-generate-delivery-note',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-delivery-note'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-order-documents-generate-credit-note',
    'sw-bulk-edit-order-documents-generate-invoice',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-credit-note'),
);
Shopwell.Component.register(
    'sw-bulk-edit-order-documents-download-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-download-documents'),
);
Shopwell.Component.register(
    'sw-bulk-edit-order-documents-delete-documents',
    () => import('./component/sw-bulk-edit-order/sw-bulk-edit-order-documents-delete-documents'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-custom-fields',
    'sw-custom-field-set-renderer',
    () => import('./component/sw-bulk-edit-custom-fields'),
);
Shopwell.Component.register('sw-bulk-edit-change-type', () => import('./component/sw-bulk-edit-change-type'));
Shopwell.Component.register(
    'sw-bulk-edit-change-type-field-renderer',
    () => import('./component/sw-bulk-edit-change-type-field-renderer'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-form-field-renderer',
    'sw-form-field-renderer',
    () => import('./component/sw-bulk-edit-form-field-renderer'),
);
Shopwell.Component.register(
    'sw-bulk-edit-product-visibility',
    () => import('./component/product/sw-bulk-edit-product-visibility'),
);
Shopwell.Component.register('sw-bulk-edit-product-media', () => import('./component/product/sw-bulk-edit-product-media'));
Shopwell.Component.extend(
    'sw-bulk-edit-product-media-form',
    'sw-product-media-form',
    () => import('./component/product/sw-bulk-edit-product-media-form'),
);
Shopwell.Component.extend(
    'sw-bulk-edit-product-description',
    'sw-text-editor',
    () => import('./component/product/sw-bulk-edit-product-description'),
);
Shopwell.Component.register('sw-bulk-edit-save-modal', () => import('./component/sw-bulk-edit-save-modal'));
Shopwell.Component.register('sw-bulk-edit-save-modal-confirm', () => import('./component/sw-bulk-edit-save-modal-confirm'));
Shopwell.Component.register('sw-bulk-edit-save-modal-process', () => import('./component/sw-bulk-edit-save-modal-process'));
Shopwell.Component.register('sw-bulk-edit-save-modal-success', () => import('./component/sw-bulk-edit-save-modal-success'));
Shopwell.Component.register(
    'sw-bulk-edit-document-generation-failed-list',
    () => import('./component/sw-bulk-edit-document-generation-failed-list'),
);
Shopwell.Component.register('sw-bulk-edit-save-modal-error', () => import('./component/sw-bulk-edit-save-modal-error'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopwell;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-bulk-edit', {
    type: 'core',
    name: 'bulk-edit',
    title: 'global.default.bulkEdit',
    description: 'sw-bulk-edit.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        product: {
            component: 'sw-bulk-edit-product',
            path: 'product/:parentId/:includesDigital',
            meta: {
                parentPath: 'sw.product.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.product.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
        order: {
            component: 'sw-bulk-edit-order',
            path: 'order/:excludeDelivery',
            meta: {
                parentPath: 'sw.order.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.order.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },

        customer: {
            component: 'sw-bulk-edit-customer',
            path: 'customer',
            meta: {
                parentPath: 'sw.customer.index',
            },
            children: {
                save: {
                    component: 'sw-bulk-edit-save-modal',
                    path: 'save',
                    redirect: {
                        name: 'sw.bulk.edit.customer.save.confirm',
                    },
                    children: {
                        confirm: {
                            component: 'sw-bulk-edit-save-modal-confirm',
                            path: 'confirm',
                        },
                        process: {
                            component: 'sw-bulk-edit-save-modal-process',
                            path: 'process',
                        },
                        success: {
                            component: 'sw-bulk-edit-save-modal-success',
                            path: 'success',
                        },
                        error: {
                            component: 'sw-bulk-edit-save-modal-error',
                            path: 'error',
                        },
                    },
                },
            },
        },
    },
});
