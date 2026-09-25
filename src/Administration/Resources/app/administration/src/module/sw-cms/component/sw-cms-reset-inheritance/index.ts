import template from './sw-cms-reset-inheritance.html.twig';
import './sw-cms-reset-inheritance.scss';

const { set, merge } = Shopwell.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export default Shopwell.Component.wrapComponentConfig({
    template,
    mixins: [Shopwell.Mixin.getByName('cms-state')],
    data() {
        return {
            showModal: false,
        };
    },
    computed: {
        cmsPageStore() {
            return Shopwell.Store.get('cmsPage');
        },
        hasOverrides() {
            return !Shopwell.Utils.types.isEmpty(this.contentEntity?.slotConfig);
        },
    },
    methods: {
        async onConfirm() {
            this.showModal = false;

            this.resetSlotOverrides();

            /**
             * Run watchers before removing the slotConfig to ensure sw-cms-form-sync won't
             * override the reset.
             */
            await this.$nextTick();

            set(this.contentEntity!, 'slotConfig', null);
        },
        resetSlotOverrides() {
            this.cmsPageStore.currentPage?.sections?.forEach((section) => {
                section.blocks?.forEach((block) => {
                    block.slots?.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        const origin = slot.getOrigin();
                        merge(slot.config, origin.translated?.config ?? origin.config ?? {});
                    });
                });
            });
        },
    },
});
