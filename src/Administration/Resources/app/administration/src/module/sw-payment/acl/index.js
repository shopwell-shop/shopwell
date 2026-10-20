/**
 * @sw-package framework
 *
 * The privileges of the generic payment gateway.
 *
 * The payment orders and the refunds are the bookkeeping of the platform: they are written by the
 * gateway while it talks to a channel, never typed by an administrator. The roles below therefore only
 * decide who may look at them - a role without the privileges gets a 403 from the admin api and the
 * respective menu entry is hidden.
 *
 * The transfers are the exception: an administrator books them and releases them, which is why they
 * carry a creator and an approver role. Their pages live in `sw-finance`; the roles stay here, next to
 * the entity they grant access to.
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
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'payment_gateway',
        key: 'payment_transfer',
        roles: {
            viewer: {
                privileges: [
                    'payment_transfer:read',
                    'payment_transfer_recipient:read',
                    'payment_app:read',
                    'state_machine_state:read',
                ],
                dependencies: [],
            },
            /**
             * Books the intent to pay. Nothing is sent to a channel by this role - the row it creates
             * waits for an approver, which is what keeps "prepare a payout" and "release a payout" in
             * two pairs of hands even though one person may hold both roles.
             */
            creator: {
                privileges: [
                    'payment_transfer:create',
                    'payment_transfer_recipient:create',
                ],
                dependencies: ['payment_transfer.viewer'],
            },
            /**
             * The only role that moves money: submitting hands the payout to the channel, rejecting
             * ends it with a reason.
             */
            approver: {
                privileges: [
                    'payment_transfer:update',
                ],
                dependencies: ['payment_transfer.viewer'],
            },
        },
    });
