/**
 * @sw-package discovery
 */

import template from './sw-cms-el-form-contact.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    props: ['formSettings'],
    computed: {
        genderOptions() {
            return [
                {
                    id: 1,
                    value: 'default',
                    disabled: true,
                    label: this.$t('sw-cms.elements.form.element.label.genderUndisclosed'),
                },
            ];
        },
    },
};
