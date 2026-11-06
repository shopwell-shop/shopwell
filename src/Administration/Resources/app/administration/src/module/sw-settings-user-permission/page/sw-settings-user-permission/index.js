/**
 * @sw-package fundamentals@framework
 */
import template from './sw-settings-user-permission.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            this.$refs.systemConfig
                .saveAll()
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        onSaveFinish() {
            this.isSaveSuccessful = false;
        },

        onChangeLoading(loading) {
            this.isLoading = loading;
        },
    },
};
