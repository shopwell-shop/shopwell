/**
 * @sw-package framework
 *
 * The finance module: the two places where money leaves the platform and where it enters a customer
 * wallet.
 *
 * It owns no entity of its own - `payment_transfer` stays the entity of the payment gateway and
 * `customer_wallet_transaction` the entity of the wallet ledger. What the module owns is the workspace
 * of the operator who has to decide about that money, which is why the pages live here and the payment
 * orders (bookkeeping, read only) stay in `sw-payment`.
 */
import './acl';

import PaymentTransferApiService from './service/payment-transfer.api.service';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-finance-transfer-list', () => import('./page/sw-finance-transfer-list'));
Shopwell.Component.register('sw-finance-transfer-detail', () => import('./page/sw-finance-transfer-detail'));
Shopwell.Component.register('sw-finance-wallet-transactions-list', () => import('./page/sw-finance-wallet-transactions-list'));
Shopwell.Component.register('sw-finance-transfer-create-modal', () => import('./component/sw-finance-transfer-create-modal'));
Shopwell.Component.register('sw-finance-transfer-approve-modal', () => import('./component/sw-finance-transfer-approve-modal'));
Shopwell.Component.register('sw-finance-transfer-reject-modal', () => import('./component/sw-finance-transfer-reject-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('paymentTransferApiService', () => {
    return new PaymentTransferApiService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-finance', {
    type: 'core',
    name: 'finance',
    title: 'sw-finance.general.mainMenuItemGeneral',
    description: 'sw-finance.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-green-default)',
    icon: 'regular-money-bill',
    favicon: 'icon-module-orders.svg',
    entity: 'payment_transfer',

    routes: {
        transfer: {
            component: 'sw-finance-transfer-list',
            path: 'transfer',
            meta: {
                privilege: 'payment_transfer.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        transferDetail: {
            component: 'sw-finance-transfer-detail',
            path: 'transfer/detail/:id',
            meta: {
                parentPath: 'sw.finance.transfer',
                privilege: 'payment_transfer.viewer',
                appSystem: {
                    view: 'detail',
                },
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { transferId: route.params.id.toLowerCase() };
                },
            },
        },

        walletTransactions: {
            component: 'sw-finance-wallet-transactions-list',
            path: 'wallet-transactions',
            meta: {
                privilege: 'wallet.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-finance',
            label: 'sw-finance.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-green-default)',
            icon: 'regular-money-bill',
            // between the automation module (70) and the extension store (80): money is operated on
            // right after the flows that trigger it, and before the catalogue of extensions
            position: 75,
            // deliberately no privilege: neither of the two children may make the entry disappear, a
            // role with only one of them still has to find its way in
        },
        {
            id: 'sw-finance-transfer',
            path: 'sw.finance.transfer',
            label: 'sw-finance.navigation.transfer',
            parent: 'sw-finance',
            privilege: 'payment_transfer.viewer',
        },
        {
            id: 'sw-finance-wallet-transactions',
            path: 'sw.finance.walletTransactions',
            label: 'sw-finance.navigation.walletTransactions',
            parent: 'sw-finance',
            privilege: 'wallet.viewer',
        },
    ],
});
