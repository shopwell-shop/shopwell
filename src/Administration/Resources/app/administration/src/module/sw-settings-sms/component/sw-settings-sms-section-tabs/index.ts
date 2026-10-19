import type { TabItem } from '@shopwell-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-settings-sms-section-tabs.html.twig';

const SECTION_ROUTES: Record<string, string> = {
    gateway: 'sw.settings.sms.gateway',
    template: 'sw.settings.sms.template',
    delivery: 'sw.settings.sms.delivery',
};

/**
 * The tabs that lead from one screen of the module to the other.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-section-tabs',

    template,

    props: {
        active: {
            type: String,
            required: true,
            validator: (value: string) => Object.keys(SECTION_ROUTES).includes(value),
        },
    },

    computed: {
        tabs(): TabItem[] {
            return [
                {
                    label: this.$t('sw-settings-sms.general.tabGateway'),
                    name: SECTION_ROUTES.gateway,
                    onClick: () => {
                        this.navigate('gateway');
                    },
                },
                {
                    label: this.$t('sw-settings-sms.general.tabTemplate'),
                    name: SECTION_ROUTES.template,
                    onClick: () => {
                        this.navigate('template');
                    },
                },
                {
                    label: this.$t('sw-settings-sms.general.tabDelivery'),
                    name: SECTION_ROUTES.delivery,
                    onClick: () => {
                        this.navigate('delivery');
                    },
                },
            ];
        },

        activeRoute(): string {
            return SECTION_ROUTES[this.active];
        },
    },

    methods: {
        navigate(section: string): void {
            void this.$router.push({ name: SECTION_ROUTES[section] });
        },
    },
});
