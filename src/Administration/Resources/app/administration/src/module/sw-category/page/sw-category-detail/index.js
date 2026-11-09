import './store';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

const { Context, Mixin } = Shopwell;
const { Criteria, ChangesetGenerator, EntityCollection } = Shopwell.Data;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'seoUrlService',
        'customFieldDataProviderService',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('category.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'cancelEdit',
    },

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
        landingPageId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            term: '',
            isLoading: false,
            isCustomFieldLoading: false,
            isSaveSuccessful: false,
            isMobileViewport: null,
            splitBreakpoint: 1024,
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            currentLanguageId: Shopwell.Context.api.languageId,
            forceDiscardChanges: false,
            categoryCheckedItem: 0,
            landingPageCheckedItem: 0,
            entryPointOverwriteConfirmed: false,
            entryPointOverwriteSalesChannels: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        changesetGenerator() {
            return new ChangesetGenerator();
        },

        showEmptyState() {
            return !this.category && !this.landingPage;
        },

        identifier() {
            return this.category ? this.placeholder(this.category, 'name') : '';
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        landingPage() {
            if (!Shopwell.Store.get('swCategoryDetail')) {
                return {};
            }

            return Shopwell.Store.get('swCategoryDetail').landingPage;
        },

        category() {
            if (!Shopwell.Store.get('swCategoryDetail')) {
                return {};
            }

            return Shopwell.Store.get('swCategoryDetail').category;
        },

        showEntryPointOverwriteModal() {
            return this.entryPointOverwriteSalesChannels !== null && this.entryPointOverwriteSalesChannels.length;
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'category'));

            return criteria;
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetLandingPageCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'landing_page'));

            return criteria;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        pageClasses() {
            return {
                'has--category': !!this.category,
                'is--mobile': !!this.isMobileViewport,
            };
        },

        tooltipSave() {
            if (!this.acl.can('category.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        landingPageTooltipSave() {
            if (!this.acl.can('landing_page.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('landing_page.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        categoryCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

            criteria
                .addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels')
                .addAssociation('translations');

            return criteria;
        },

        landingPageCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('tags');
            criteria.addAssociation('salesChannels');
            criteria.addAssociation('translations');

            return criteria;
        },

        assetFilter() {
            return Shopwell.Filter.getByName('asset');
        },
    },

    watch: {
        landingPageId() {
            this.setLandingPage();
        },

        categoryId() {
            this.setCategory();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteLeave(to, from, next) {
        if (this.forceDiscardChanges) {
            this.forceDiscardChanges = false;
            Shopwell.Store.get('shopwellApps').selectedIds = [];
            next();

            return;
        }

        if (!this.category) {
            Shopwell.Store.get('shopwellApps').selectedIds = [];
            next();

            return;
        }

        /*
         * Generate change set for category and delete `id` and `versionId` to only consider actual changes.
         * A new version without changes should not trigger the navigation guard.
         */
        const { changes, deletionQueue } = this.changesetGenerator.generate(this.category);
        if (changes === null) {
            Shopwell.Store.get('shopwellApps').selectedIds = [];
            next();

            return;
        }

        const keysToDelete = ['id', 'versionId'];
        const changedKeys = Object.keys(changes).filter((key) => !keysToDelete.includes(key));
        const hasDeletions = deletionQueue.length > 0;

        if (changedKeys.length === 0 && !hasDeletions) {
            Shopwell.Store.get('shopwellApps').selectedIds = [];
            next();

            return;
        }

        this.isDisplayingLeavePageWarning = true;
        this.nextRoute = to;
        next(false);
    },

    methods: {
        createdComponent() {
            Shopwell.ExtensionAPI.publishData({
                id: 'sw-category-detail__category',
                path: 'category',
                scope: this,
            });

            this.isLoading = true;
            this.checkViewport();
            this.registerListener();

            if (this.categoryId !== null) {
                this.setCategory();

                return;
            }

            this.setLandingPage();
        },

        categoryCheckedElementsCount(count) {
            this.categoryCheckedItem = count;
        },

        landingPageCheckedElementsCount(count) {
            this.landingPageCheckedItem = count;
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport,
            });
        },

        onSearch(value) {
            if (value.length === 0) {
                value = undefined;
            }
            this.term = value;
        },

        checkViewport() {
            this.isMobileViewport = this.$device.getViewportWidth() < this.splitBreakpoint;
        },

        async setLandingPage() {
            this.isLoading = true;

            try {
                if (this.landingPageId === null) {
                    Shopwell.Store.get('shopwellApps').selectedIds = [];

                    Shopwell.Store.get('swCategoryDetail').landingPage = null;

                    return;
                }

                Shopwell.Store.get('shopwellApps').selectedIds = [this.landingPageId];
                await Shopwell.Store.get('swCategoryDetail').loadActiveLandingPage({
                    repository: this.landingPageRepository,
                    apiContext: Shopwell.Context.api,
                    id: this.landingPageId,
                    criteria: this.landingPageCriteria,
                });

                await this.loadLandingPageCustomFieldSet();
            } catch {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        setCategory() {
            this.isLoading = true;

            if (this.categoryId === null) {
                Shopwell.Store.get('shopwellApps').selectedIds = [];

                Shopwell.Store.get('swCategoryDetail').category = null;

                this.isLoading = false;
                return;
            }

            Shopwell.Store.get('shopwellApps').selectedIds = [this.categoryId];
            Shopwell.Store.get('swCategoryDetail')
                .loadActiveCategory({
                    repository: this.categoryRepository,
                    apiContext: Shopwell.Context.api,
                    id: this.categoryId,
                    criteria: this.categoryCriteria,
                })
                .then(() => {
                    return Promise.resolve();
                })
                .then(this.loadCustomFieldSet)
                .then(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldDataProviderService
                .getCustomFieldSets('category', false, null)
                .then((customFieldSet) => {
                    Shopwell.Store.get('swCategoryDetail').customFieldSets = customFieldSet;
                })
                .finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        loadLandingPageCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldDataProviderService
                .getCustomFieldSets('landing_page', false, null)
                .then((customFieldSet) => {
                    Shopwell.Store.get('swCategoryDetail').customFieldSets = customFieldSet;
                })
                .finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        onSaveCategories() {
            return this.categoryRepository.save(this.category);
        },

        openChangeModal(destination) {
            this.nextRoute = destination;
            this.isDisplayingLeavePageWarning = true;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            // Discard all category related errors that may have occurred
            Shopwell.Store.get('error').removeApiError('category');

            this.forceDiscardChanges = true;
            this.isDisplayingLeavePageWarning = false;

            this.$nextTick(() => {
                this.$router.push({
                    name: destination.name,
                    params: destination.params,
                });
            });
        },

        cancelEdit() {
            this.resetCategory();
        },

        resetCategory() {
            this.$router.push({ name: 'sw.category.index' });
        },

        onChangeLanguage(newLanguageId) {
            this.currentLanguageId = newLanguageId;

            if (this.landingPageId !== null) {
                this.setLandingPage();
            }

            this.setCategory();
        },

        abortOnLanguageChange() {
            if (this.landingPage) {
                return this.landingPage ? this.categoryRepository.hasChanges(this.landingPage) : false;
            }

            return this.category ? this.categoryRepository.hasChanges(this.category) : false;
        },

        saveOnLanguageChange() {
            if (this.landingPage) {
                return this.onSaveLandingPage();
            }

            return this.onSave();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            this.isSaveSuccessful = false;

            if (!this.entryPointOverwriteConfirmed) {
                this.checkForEntryPointOverwrite();
                if (this.showEntryPointOverwriteModal) {
                    return Promise.resolve();
                }
            }

            this.isLoading = true;

            try {
                await this.updateSeoUrls();

                await this.categoryRepository.save(this.category, { ...Shopwell.Context.api });

                this.isSaveSuccessful = true;
                this.entryPointOverwriteConfirmed = false;
                return this.setCategory();
            } catch (error) {
                this.isLoading = false;
                this.entryPointOverwriteConfirmed = false;

                if (!error.response?.data?.errors) {
                    this.createNotificationError({
                        message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                }

                return Promise.reject(error);
            }
        },

        checkForEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api);

            this.category.navigationSalesChannels.forEach((salesChannel) => {
                if (salesChannel.navigationCategoryId !== null && salesChannel.navigationCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.footerSalesChannels.forEach((salesChannel) => {
                if (salesChannel.footerCategoryId !== null && salesChannel.footerCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.serviceSalesChannels.forEach((salesChannel) => {
                if (salesChannel.serviceCategoryId !== null && salesChannel.serviceCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });
        },

        cancelEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
        },

        confirmEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
            this.entryPointOverwriteConfirmed = true;
            this.$nextTick(() => {
                this.onSave();
            });
        },

        onSaveLandingPage() {
            this.isSaveSuccessful = false;

            if (this.landingPageId !== 'create') {
                if (this.landingPage.salesChannels.length === 0) {
                    this.addLandingPageSalesChannelError();

                    return Promise.resolve();
                }
            }

            this.isLoading = true;
            return this.landingPageRepository
                .save(this.landingPage, Shopwell.Context.api)
                .then(() => {
                    this.isSaveSuccessful = true;

                    if (this.landingPageId === 'create') {
                        this.$router.push({
                            name: 'sw.category.landingPageDetail',
                            params: { id: this.landingPage.id },
                        });
                        return Promise.resolve();
                    }

                    return this.setLandingPage();
                })
                .catch(() => {
                    this.isLoading = false;

                    if (this.landingPage.salesChannels.length === 0) {
                        this.addLandingPageSalesChannelError();

                        return;
                    }

                    this.createNotificationError({
                        message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                });
        },

        addLandingPageSalesChannelError() {
            const shopwellError = new Shopwell.Classes.ShopwellError({
                code: 'landing_page_sales_channel_blank',
                detail: 'This value should not be blank.',
                status: '400',
            });

            Shopwell.Store.get('error').addApiError({
                expression: `landing_page.${this.landingPage.id}.salesChannels`,
                error: shopwellError,
            });

            this.createNotificationError({
                message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });
        },

        updateSeoUrls() {
            if (!Shopwell.Store.list().includes('swSeoUrl')) {
                return Promise.resolve();
            }

            const seoUrls = Shopwell.Store.get('swSeoUrl').newOrModifiedUrls;

            return Promise.all(
                seoUrls.map((seoUrl) => {
                    if (seoUrl.seoPathInfo) {
                        seoUrl.isModified = true;
                        return this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId).catch((error) => {
                            if (error.response?.data?.errors) {
                                error.response.data.errors.forEach((apiError) => {
                                    const messageKey = `global.error-codes.${apiError.detail}`;
                                    const params = apiError.meta?.parameters || {};
                                    const translatedMessage = this.$t(messageKey, params);

                                    const errorMessage =
                                        translatedMessage !== messageKey
                                            ? translatedMessage
                                            : apiError.detail ||
                                              apiError.title ||
                                              this.$t('global.notification.unspecifiedSaveErrorMessage');

                                    this.createNotificationError({
                                        message: errorMessage,
                                    });
                                });
                            } else {
                                this.createNotificationError({
                                    message: error.message || this.$t('global.notification.unspecifiedSaveErrorMessage'),
                                });
                            }

                            return Promise.reject(error);
                        });
                    }

                    return Promise.resolve();
                }),
            );
        },

        onLandingPageDelete() {
            Shopwell.Store.get('swCategoryDetail').landingPagesToDelete = null;
        },

        onCategoryDelete() {
            Shopwell.Store.get('swCategoryDetail').categoriesToDelete = null;
        },
    },
};
