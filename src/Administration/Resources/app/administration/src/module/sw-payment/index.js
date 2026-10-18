/**
 * @sw-package framework
 */
import './acl';

import PaymentGatewayService from './service/payment-gateway.api.service';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-payment-config-field', () => import('./component/sw-payment-config-field'));
Shopwell.Component.register('sw-payment-order-list', () => import('./page/sw-payment-order-list'));
Shopwell.Component.register('sw-payment-order-detail', () => import('./page/sw-payment-order-detail'));
Shopwell.Component.register('sw-payment-refund-list', () => import('./page/sw-payment-refund-list'));
Shopwell.Component.register('sw-payment-refund-detail', () => import('./page/sw-payment-refund-detail'));
Shopwell.Component.register('sw-payment-transfer-list', () => import('./page/sw-payment-transfer-list'));
Shopwell.Component.register('sw-payment-transfer-detail', () => import('./page/sw-payment-transfer-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('paymentGatewayService', () => {
    return new PaymentGatewayService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-payment', {
    type: 'core',
    name: 'payment',
    title: 'sw-payment.general.mainMenuItemGeneral',
    description: 'sw-payment.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-cyan-default)',
    icon: 'regular-credit-card',
    favicon: 'icon-module-orders.svg',
    entity: 'payment_order',

    routes: {
        index: {
            component: 'sw-payment-order-list',
            path: 'index',
            meta: {
                privilege: 'payment_order.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        orderDetail: {
            component: 'sw-payment-order-detail',
            path: 'order/detail/:id',
            meta: {
                parentPath: 'sw.payment.index',
                privilege: 'payment_order.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route) {
                    return { paymentOrderId: route.params.id.toLowerCase() };
                },
            },
        },

        refund: {
            component: 'sw-payment-refund-list',
            path: 'refund',
            meta: {
                privilege: 'payment_refund.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        refundDetail: {
            component: 'sw-payment-refund-detail',
            path: 'refund/detail/:id',
            meta: {
                parentPath: 'sw.payment.refund',
                privilege: 'payment_refund.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route) {
                    return { paymentRefundId: route.params.id.toLowerCase() };
                },
            },
        },

        transfer: {
            component: 'sw-payment-transfer-list',
            path: 'transfer',
            meta: {
                privilege: 'payment_transfer.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        transferDetail: {
            component: 'sw-payment-transfer-detail',
            path: 'transfer/detail/:id',
            meta: {
                parentPath: 'sw.payment.transfer',
                privilege: 'payment_transfer.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route) {
                    return { transferId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-payment',
            label: 'sw-payment.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-cyan-default)',
            icon: 'regular-credit-card',
            position: 35,
            privilege: 'payment_order.viewer',
        },
        {
            path: 'sw.payment.index',
            label: 'sw-payment.general.mainMenuItemPaymentOrders',
            parent: 'sw-payment',
            privilege: 'payment_order.viewer',
        },
        {
            path: 'sw.payment.refund',
            label: 'sw-payment.general.mainMenuItemRefunds',
            parent: 'sw-payment',
            privilege: 'payment_refund.viewer',
        },
        {
            path: 'sw.payment.transfer',
            label: 'sw-payment.general.mainMenuItemTransfers',
            parent: 'sw-payment',
            privilege: 'payment_transfer.viewer',
        },
    ],
});
