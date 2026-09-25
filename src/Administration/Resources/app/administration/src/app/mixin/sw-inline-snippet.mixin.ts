/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-inline-snippet`; change both together.
 */
export default Shopwell.Mixin.register(
    'sw-inline-snippet',
    defineComponent({
        computed: {
            swInlineSnippetLocale(): string {
                return Shopwell.Store.get('session').currentLocale as unknown as string;
            },

            swInlineSnippetFallbackLocale(): string {
                return Shopwell.Context.app.fallbackLocale as unknown as string;
            },
        },

        methods: {
            getInlineSnippet(value: { [key: string]: string }) {
                if (Shopwell.Utils.types.isEmpty(value)) {
                    return '';
                }
                if (value[this.swInlineSnippetLocale]) {
                    return value[this.swInlineSnippetLocale];
                }
                if (value[this.swInlineSnippetFallbackLocale]) {
                    return value[this.swInlineSnippetFallbackLocale];
                }
                if (Shopwell.Utils.types.isObject(value)) {
                    const locale = Object.keys(value).find((key) => {
                        return value[key] !== '';
                    });

                    if (locale !== undefined) {
                        return value[locale];
                    }
                }

                return value;
            },
        },
    }),
);
