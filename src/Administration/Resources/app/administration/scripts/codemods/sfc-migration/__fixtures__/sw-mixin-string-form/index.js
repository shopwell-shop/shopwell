import template from './sw-mixin-string-form.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: ['sw-inline-snippet'],

    computed: {
        // The composable does not expose the mixin's `swInlineSnippetFallbackLocale`, but this
        // declaration shadowed the mixin's anyway, so nothing is lost by migrating.
        swInlineSnippetFallbackLocale() {
            return (fallback) => fallback || Shopwell.Context.app.fallbackLocale;
        },
    },

    methods: {
        formatName() {
            return this.getInlineSnippet('sw-date.longMonth');
        },

        formatRaw() {
            return this.swInlineSnippetFallbackLocale('');
        },
    },
};
