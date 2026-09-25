/**
 * @sw-package framework
 */
import { computed, provide } from 'vue';

/**
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    template: '<slot />',
    inheritAttrs: false,
    setup(_props, { attrs }) {
        Object.keys(attrs).forEach((key) =>
            provide(
                Shopwell.Utils.string.camelCase(key),
                computed(() => attrs[key]),
            ),
        );
        return {};
    },
});
