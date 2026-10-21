/**
 * @sw-package checkout
 */
import template from './sw-customer-detail.html.twig';

const { Component } = Shopwell;

interface CustomerDetailTab {
    label: string;
    name: string;
    onClick: () => void;
}

interface DetailRoute {
    name: string;
    params: { id: string };
    query: { edit: boolean };
}

interface SwCustomerDetailThis {
    customerId: string;
    editMode: boolean;
    loyaltyRoute: DetailRoute;
    walletRoute: DetailRoute;
    acl: { can: (privilege: string) => boolean };
    $super: (name: string) => CustomerDetailTab[];
    $router: { push: (location: DetailRoute) => Promise<unknown> };
    $t: (key: string) => string;
}

/**
 * The points and wallet views of a customer live on their own tabs of the customer detail page.
 * Both tabs are added to both tab implementations of the page - the legacy `sw-tabs` through the
 * `sw_customer_detail_content_tab_after` block, the meteor tabs through the `customerDetailTabs`
 * computed - so they show up regardless of the active major feature flag. Each tab is gated by
 * its own ACL privilege.
 *
 * @private
 */
export const customerDetailExtension = {
    template,

    computed: {
        loyaltyRoute(this: SwCustomerDetailThis): DetailRoute {
            return {
                name: 'sw.customer.detail.loyalty',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },

        walletRoute(this: SwCustomerDetailThis): DetailRoute {
            return {
                name: 'sw.customer.detail.wallet',
                params: { id: this.customerId },
                query: { edit: this.editMode },
            };
        },

        customerDetailTabs(this: SwCustomerDetailThis): CustomerDetailTab[] {
            const tabs = this.$super('customerDetailTabs');

            if (this.acl.can('loyalty.viewer')) {
                tabs.push({
                    label: this.$t('sw-customer.detail.tabPoints'),
                    name: this.loyaltyRoute.name,
                    onClick: () => {
                        void this.$router.push(this.loyaltyRoute);
                    },
                });
            }

            if (this.acl.can('wallet.viewer')) {
                tabs.push({
                    label: this.$t('sw-customer.detail.tabWallet'),
                    name: this.walletRoute.name,
                    onClick: () => {
                        void this.$router.push(this.walletRoute);
                    },
                });
            }

            return tabs;
        },
    },
};

Component.override('sw-customer-detail', customerDetailExtension);
