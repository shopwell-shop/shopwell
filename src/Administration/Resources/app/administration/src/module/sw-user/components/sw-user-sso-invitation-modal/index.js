import template from './sw-user-sso-invitation-modal.html.twig';

const {
    Data: { Criteria },
} = Shopwell;
const { ShopwellError } = Shopwell.Classes;

/**
 * @private
 * @sw-package framework
 */
export default {
    name: 'sw-user-sso-invitation-modal',
    template,

    emits: ['user-invited', 'invitation-failed', 'modal-close'],

    data() {
        return {
            email: '',
            languageId: null,
            languages: [],
            errors: {
                errorEmail: null,
                errorLanguage: null,
            },
            isLoading: false,
        };
    },

    computed: {
        invitationService() {
            return Shopwell.Service('ssoInvitationService');
        },

        languageRepository() {
            return Shopwell.Service('repositoryFactory').create('language');
        },

        languageCriteria() {
            const registeredLocales = Array.from(Shopwell.Locale.getLocaleRegistry().keys());
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equalsAny('locale.code', registeredLocales));

            return criteria;
        },

        hasError() {
            return this.errors.errorEmail !== null || this.errors.errorLanguage !== null;
        },
    },

    watch: {
        email() {
            this.validateEmail();
        },

        languageId() {
            this.validateLanguage();
        },
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            return this.loadLanguages();
        },

        async loadLanguages() {
            this.languages = await this.languageRepository.search(this.languageCriteria, Shopwell.Context.api);
        },

        async sendInvitation() {
            this.isLoading = true;
            this.validateEmail();
            this.validateLanguage();

            if (this.hasError) {
                this.isLoading = false;
                return;
            }

            const localeId = this.languages.get(this.languageId).localeId;

            try {
                await this.invitationService.inviteUser(this.email, localeId);
                this.$emit('user-invited');
                this.closeModal();
            } catch (e) {
                this.$emit('invitation-failed', e);
            } finally {
                this.isLoading = false;
            }
        },

        closeModal() {
            this.email = '';
            this.$emit('modal-close');
        },

        validateEmail() {
            if (!/[\w.%+-]+@[\w.-]+\.[\w]{2,}/.test(this.email)) {
                this.errors.errorEmail = new ShopwellError({
                    code: 'bd79c0ab-ddba-46cc-a703-a7a4b08de310',
                });

                return;
            }

            this.errors.errorEmail = null;
        },

        validateLanguage() {
            if (this.languageId === null) {
                this.errors.errorLanguage = new ShopwellError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
                return;
            }

            this.errors.errorLanguage = null;
        },
    },
};
