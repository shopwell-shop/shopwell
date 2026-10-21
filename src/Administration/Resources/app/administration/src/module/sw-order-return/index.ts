/**
 * @sw-package after-sales
 *
 * The returns module: the workspace of the operator who decides about goods coming back and money
 * going out.
 *
 * It owns the entity `order_return` and nothing else - the order it belongs to stays the entity of
 * `sw-order`, the refund it triggers stays the row of the payment gateway. What the module owns is the
 * queue of open cases and the settings page that maintains the reasons a return can be opened with.
 *
 * The queue is the workspace: a return is sorted by deadline and an operator works many of them side by
 * side, not one order at a time. The order detail then answers the other question - what is running on
 * *this* order - with a tab of its own (`sw-order-detail-return`). The two entry points exist for two
 * different jobs and share one service behind them.
 */
import './acl';

import OrderReturnApiService from './service/order-return.api.service';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-order-return-list', () => import('./page/sw-order-return-list'));
Shopwell.Component.register('sw-order-return-detail', () => import('./page/sw-order-return-detail'));
Shopwell.Component.register('sw-order-detail-return', () => import('./page/sw-order-detail-return'));
Shopwell.Component.register('sw-order-return-decline-modal', () => import('./component/sw-order-return-decline-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('orderReturnApiService', () => {
    return new OrderReturnApiService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-order-return', {
    type: 'core',
    name: 'order-return',
    title: 'sw-order-return.general.mainMenuItemGeneral',
    description: 'sw-order-return.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-orange-default)',
    icon: 'regular-undo',
    favicon: 'icon-module-orders.svg',
    entity: 'order_return',

    routes: {
        list: {
            component: 'sw-order-return-list',
            path: 'list',
            meta: {
                privilege: 'order_return.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        detail: {
            component: 'sw-order-return-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.order.return.list',
                privilege: 'order_return.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { orderReturnId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-order-return',
            label: 'sw-order-return.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-undo',
            // between the customers (40) and the content modules (50): a return belongs to the
            // fulfilment half of the menu - orders (30), customers (40), returns (45) - and not to
            // the catalogue half that starts with the CMS (50)
            position: 45,
            // deliberately no privilege: the settings page of the module lives outside this menu, so a
            // role that may only maintain reasons still has to find its way in
        },
        {
            id: 'sw-order-return-list',
            path: 'sw.order.return.list',
            label: 'sw-order-return.navigation.list',
            parent: 'sw-order-return',
            privilege: 'order_return.viewer',
        },
    ],
});
