/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import type { SalutationFilterEntityType } from '../composables/use-salutation';

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-salutation`; change both together.
 */
export default Shopwell.Mixin.register(
    'salutation',
    defineComponent({
        computed: {
            salutationFilter(): (entity: SalutationFilterEntityType, fallbackSnippet: string) => string {
                return Shopwell.Filter.getByName('salutation');
            },
        },

        methods: {
            salutation(entity: SalutationFilterEntityType, fallbackSnippet = '') {
                return this.salutationFilter(entity, fallbackSnippet);
            },
        },
    }),
);
