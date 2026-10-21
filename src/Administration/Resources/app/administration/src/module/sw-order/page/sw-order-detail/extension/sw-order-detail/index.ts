/**
 * @sw-package after-sales
 */
import template from './sw-order-detail.html.twig';

const { Component } = Shopwell;

interface OrderDetailTab {
    label: string;
    name: string;
    onClick: () => void;
}

interface DetailRoute {
    name: string;
    params: { id: string };
}

interface SwOrderDetailThis {
    orderId: string;
    acl: { can: (privilege: string) => boolean };
    returnTabRoute: DetailRoute;
    $super: (name: string) => OrderDetailTab[];
    $router: { push: (location: DetailRoute) => Promise<unknown> };
    $t: (key: string) => string;
}

/**
 * The returns of an order, as a tab of the order detail page.
 *
 * A return is worked in a queue of its own - it is sorted by deadline and an operator works many of
 * them side by side - which is why the module has its own menu. What the queue can not answer is the
 * question the operator has when the customer is on the phone: "what is running on this order?" That
 * question is answered where the order is read, so the order detail gets a tab that lists the returns
 * of the order and opens a new one.
 *
 * The tab is added to both tab implementations of the page - the legacy `sw-tabs` through the
 * `sw_order_detail_content_tabs_extension` block, the meteor tabs through the `orderDetailTabs`
 * computed - so it shows up regardless of the active major feature flag. It is gated by the viewer
 * privilege of the module: a role that may not see returns does not get the tab either.
 *
 * @private
 */
export const orderDetailReturnTab = {
    template,

    computed: {
        returnTabRoute(this: SwOrderDetailThis): DetailRoute {
            return {
                name: 'sw.order.detail.returns',
                params: { id: this.orderId },
            };
        },

        orderDetailTabs(this: SwOrderDetailThis): OrderDetailTab[] {
            const tabs = this.$super('orderDetailTabs');

            if (!this.acl.can('order_return.viewer')) {
                return tabs;
            }

            tabs.push({
                label: this.$t('sw-order.detail.tabReturns'),
                name: this.returnTabRoute.name,
                onClick: () => {
                    void this.$router.push(this.returnTabRoute);
                },
            });

            return tabs;
        },
    },
};

Component.override('sw-order-detail', orderDetailReturnTab);
