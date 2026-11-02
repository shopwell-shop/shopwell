/**
 * @sw-package framework
 *
 * The payment module: the payment orders the gateway books and the refunds it takes back.
 *
 * The pages are read-only. Money is moved by the apps that call the gateway, so the operator's work
 * here is to answer "what happened to this payment" - which is why the detail pages are written as a
 * story in time rather than as one tab per table.
 */
import './acl';

import PaymentGatewayService from './service/payment-gateway.api.service';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations -- the module entry point is where its own parts are registered */
Shopwell.Component.register('sw-payment-config-field', () => import('./component/sw-payment-config-field'));
Shopwell.Component.register('sw-payment-identifier', () => import('./component/sw-payment-identifier'));
Shopwell.Component.register('sw-payment-order-summary', () => import('./component/sw-payment-order-summary'));
Shopwell.Component.register('sw-payment-order-activity', () => import('./component/sw-payment-order-activity'));
Shopwell.Component.register('sw-payment-order-list', () => import('./page/sw-payment-order-list'));
Shopwell.Component.register('sw-payment-order-detail', () => import('./page/sw-payment-order-detail'));
Shopwell.Component.register('sw-payment-refund-list', () => import('./page/sw-payment-refund-list'));
Shopwell.Component.register('sw-payment-refund-detail', () => import('./page/sw-payment-refund-detail'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- registered here, read by the settings page
Shopwell.Service().register('paymentGatewayService', () => {
    return new PaymentGatewayService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- the module itself has no export to declare
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
                default(route: { params: Record<string, string> }) {
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
                default(route: { params: Record<string, string> }) {
                    return { paymentRefundId: route.params.id.toLowerCase() };
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
            id: 'sw-payment-orders',
            path: 'sw.payment.index',
            label: 'sw-payment.general.mainMenuItemPaymentOrders',
            parent: 'sw-payment',
            privilege: 'payment_order.viewer',
        },
        {
            id: 'sw-payment-refunds',
            path: 'sw.payment.refund',
            label: 'sw-payment.general.mainMenuItemRefunds',
            parent: 'sw-payment',
            privilege: 'payment_refund.viewer',
        },
    ],
});
