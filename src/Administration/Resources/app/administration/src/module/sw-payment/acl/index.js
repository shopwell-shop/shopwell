/**
 * @sw-package framework
 *
 * The privileges of the generic payment gateway.
 *
 * The payment orders and the refunds are the bookkeeping of the platform: they are written by the
 * gateway while it talks to a channel, never typed by an administrator. The roles below therefore only
 * decide who may look at them - a role without the privileges gets a 403 from the admin api and the
 * respective menu entry is hidden.
 */

Shopwell.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'payment_gateway',
        roles: {},
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'payment_gateway',
        key: 'payment_order',
        roles: {
            viewer: {
                privileges: [
                    'payment_order:read',
                    'payment_transaction:read',
                    'payment_refund:read',
                    'payment_notification:read',
                    'payment_webhook_delivery:read',
                    'payment_app:read',
                    'state_machine_state:read',
                ],
                dependencies: [],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'payment_gateway',
        key: 'payment_refund',
        roles: {
            viewer: {
                privileges: [
                    'payment_refund:read',
                    'payment_order:read',
                    'payment_app:read',
                    'state_machine_state:read',
                ],
                dependencies: [],
            },
        },
    });
