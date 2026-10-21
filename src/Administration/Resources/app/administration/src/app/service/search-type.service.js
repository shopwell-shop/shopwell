/**
 * @module app/service/search-type
 * @sw-package inventory
 */

/**
 *
 * @memberOf module:core/service/search-type
 * @constructor
 * @method createSearchTypeService
 * @returns {Object}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createSearchTypeService() {
    const typeStore = {
        product: {
            entityName: 'product',
            placeholderSnippet: 'sw-product.general.placeholderSearchBar',
            listingRoute: 'sw.product.index',
        },
        category: {
            entityName: 'category',
            placeholderSnippet: 'sw-category.general.placeholderSearchBar',
            listingRoute: 'sw.category.index',
        },
        landing_page: {
            entityName: 'landing_page',
            placeholderSnippet: 'sw-landing-page.general.placeholderSearchBar',
            listingRoute: 'sw.category.index',
        },
        customer: {
            entityName: 'customer',
            placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
            listingRoute: 'sw.customer.index',
        },
        order: {
            entityName: 'order',
            placeholderSnippet: 'sw-order.general.placeholderSearchBar',
            listingRoute: 'sw.order.index',
        },
        media: {
            entityName: 'media',
            placeholderSnippet: 'sw-media.general.placeholderSearchBar',
            listingRoute: 'sw.media.index',
        },
        cms_page: {
            entityName: 'cms_page',
            placeholderSnippet: 'sw-cms.general.placeholderSearchBar',
            listingRoute: 'sw.cms.index',
            hideOnGlobalSearchBar: true,
        },
        customer_group: {
            entityName: 'customer_group',
            placeholderSnippet: 'sw-settings-customer-group.general.placeholderSearchBar',
            listingRoute: 'sw.settings.customer.group.index',
            hideOnGlobalSearchBar: true,
        },
        product_manufacturer: {
            entityName: 'product_manufacturer',
            placeholderSnippet: 'sw-manufacturer.general.placeholderSearchBar',
            listingRoute: 'sw.manufacturer.index',
            hideOnGlobalSearchBar: true,
        },
        newsletter_recipient: {
            entityName: 'newsletter_recipient',
            placeholderSnippet: 'sw-newsletter-recipient.general.placeholderSearchBar',
            listingRoute: 'sw.newsletter.recipient.index',
            hideOnGlobalSearchBar: true,
        },
        payment_method: {
            entityName: 'payment_method',
            placeholderSnippet: 'sw-settings-payment.general.placeholderSearchBar',
            listingRoute: 'sw.settings.payment.index',
            hideOnGlobalSearchBar: true,
        },
        shipping_method: {
            entityName: 'shipping_method',
            placeholderSnippet: 'sw-settings-shipping.general.placeholderSearchBar',
            listingRoute: 'sw.settings.shipping.index',
            hideOnGlobalSearchBar: true,
        },
        product_stream: {
            entityName: 'product_stream',
            placeholderSnippet: 'sw-product-stream.general.placeholderSearchBar',
            listingRoute: 'sw.product.stream.index',
            hideOnGlobalSearchBar: true,
        },
        promotion: {
            entityName: 'promotion',
            placeholderSnippet: 'sw-promotion-v2.list.placeholderSearchBar',
            listingRoute: 'sw.promotion.v2.index',
            hideOnGlobalSearchBar: true,
        },
        property_group: {
            entityName: 'property_group',
            placeholderSnippet: 'sw-property.general.placeholderSearchBar',
            listingRoute: 'sw.property.index',
            hideOnGlobalSearchBar: true,
        },
        sales_channel: {
            entityName: 'sales_channel',
            placeholderSnippet: 'sw-sales-channel.general.placeholderSearchBar',
            listingRoute: 'sw.sales.channel.index',
            hideOnGlobalSearchBar: true,
        },
        rule: {
            entityName: 'rule',
            placeholderSnippet: 'sw-settings-rule.general.placeholderSearchBar',
            listingRoute: 'sw.settings.rule.index',
        },
        flow: {
            entityName: 'flow',
            placeholderSnippet: 'sw-flow.general.placeholderSearchBar',
            listingRoute: 'sw.flow.index',
        },
        payment_order: {
            entityName: 'payment_order',
            placeholderSnippet: 'sw-payment.order.list.placeholderSearchBar',
            listingRoute: 'sw.payment.index',
            hideOnGlobalSearchBar: true,
        },
        payment_refund: {
            entityName: 'payment_refund',
            placeholderSnippet: 'sw-payment.refund.list.placeholderSearchBar',
            listingRoute: 'sw.payment.refund',
            hideOnGlobalSearchBar: true,
        },
        payment_transfer: {
            entityName: 'payment_transfer',
            placeholderSnippet: 'sw-payment.transfer.list.placeholderSearchBar',
            listingRoute: 'sw.payment.transfer',
            hideOnGlobalSearchBar: true,
        },
        customer_wallet_transaction: {
            entityName: 'customer_wallet_transaction',
            placeholderSnippet: 'sw-finance.walletTransactions.list.placeholderSearchBar',
            listingRoute: 'sw.finance.walletTransactions',
            hideOnGlobalSearchBar: true,
        },
        sms_gateway: {
            entityName: 'sms_gateway',
            placeholderSnippet: 'sw-settings-sms.gateway.placeholderSearchBar',
            listingRoute: 'sw.settings.sms.gateway',
            hideOnGlobalSearchBar: true,
        },
        sms_template: {
            entityName: 'sms_template',
            placeholderSnippet: 'sw-settings-sms.template.placeholderSearchBar',
            listingRoute: 'sw.settings.sms.template',
            hideOnGlobalSearchBar: true,
        },
        sms_delivery: {
            entityName: 'sms_delivery',
            placeholderSnippet: 'sw-settings-sms.delivery.placeholderSearchBar',
            listingRoute: 'sw.settings.sms.delivery',
            hideOnGlobalSearchBar: true,
        },
        order_return: {
            entityName: 'order_return',
            placeholderSnippet: 'sw-order-return.list.placeholderSearchBar',
            listingRoute: 'sw.order.return.list',
            hideOnGlobalSearchBar: true,
        },
        order_return_reason: {
            entityName: 'order_return_reason',
            placeholderSnippet: 'sw-settings-order-return.reason.placeholderSearchBar',
            listingRoute: 'sw.settings.order.return.reason',
            hideOnGlobalSearchBar: true,
        },
    };

    let $typeStore = {};

    $typeStore = {
        all: {
            entityName: '',
            placeholderSnippet: '',
            listingRoute: '',
        },
        ...typeStore,
    };

    return {
        getTypeByName,
        upsertType,
        getTypes,
        removeType,
    };

    function getTypeByName(type) {
        return $typeStore[type];
    }

    function upsertType(name, configuration) {
        $typeStore[name] = { ...$typeStore[name], ...configuration };
    }

    function getTypes() {
        return $typeStore;
    }

    function removeType(name) {
        delete $typeStore[name];
    }
}
