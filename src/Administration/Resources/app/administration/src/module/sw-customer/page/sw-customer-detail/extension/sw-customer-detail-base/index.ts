/**
 * @sw-package checkout
 */
import template from './sw-customer-detail-base.html.twig';

const { Component } = Shopwell;

/**
 * Appends the read-only "membership & assets" overview card to the base tab of the customer
 * detail page. Visibility and the block level ACL gating live inside the
 * `sw-customer-detail-loyalty-overview` component itself.
 */
Component.override('sw-customer-detail-base', {
    template,
});
