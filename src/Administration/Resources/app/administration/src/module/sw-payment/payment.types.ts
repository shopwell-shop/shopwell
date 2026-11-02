/**
 * @sw-package framework
 */

/**
 * The payment entities as the administration reads them.
 *
 * A payment order carries its whole bookkeeping as associations - transactions, notifications,
 * webhook deliveries and refunds -, so a detail page renders every section from a single response.
 * The schema marks those collections as optional because an order that was created a second ago has
 * none of them yet.
 *
 * @private
 */
export type PaymentOrder = EntitySchema.Entity<'payment_order'>;

/**
 * @private
 */
export type PaymentRefund = EntitySchema.Entity<'payment_refund'>;

/**
 * @private
 */
export type PaymentTransaction = EntitySchema.Entity<'payment_transaction'>;

/**
 * @private
 */
export type PaymentNotification = EntitySchema.Entity<'payment_notification'>;

/**
 * @private
 */
export type PaymentWebhookDelivery = EntitySchema.Entity<'payment_webhook_delivery'>;

/**
 * The four kinds of record that tell the story of one payment.
 *
 * @private
 */
export type PaymentActivityKind = 'transaction' | 'notification' | 'webhook' | 'refund';

/**
 * One entry of the activity of a payment order.
 *
 * The four tables are ordered by time and read as one list instead of four tabs: an operator who
 * asks why a payment did not go through needs the notification that arrived after the failed
 * transaction, not the table it happens to live in.
 *
 * The texts are translated where the list is built, so the component that renders it stays a
 * presentation of what it is given and never reaches for a snippet key of its own.
 *
 * @private
 */
export interface PaymentActivity {
    id: string;
    kind: PaymentActivityKind;
    at: string;
    title: string;
    detail: string;
    statusLabel: string;
    statusVariant: string;
    link: { name: string; params: { id: string } } | null;
}

/**
 * A problem the page has to point at before it shows any detail: an invalid signature, a rejected
 * amount, a webhook that never arrived. Silence here is what turns a payment incident into a
 * support ticket.
 *
 * @private
 */
export interface PaymentAlert {
    id: string;
    variant: string;
    message: string;
}
