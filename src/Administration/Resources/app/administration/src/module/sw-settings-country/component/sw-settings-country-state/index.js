/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-state.html.twig';
import './sw-settings-country-state.scss';

const { Mixin, Data } = Shopwell;
const { Criteria } = Data;

const REPOSITORY_BY_LEVEL = {
    state: 'country_state',
    city: 'country_city',
    district: 'country_district',
};

/**
 * Mirrors the breakpoint at which the three columns are stacked instead of sitting next to each
 * other, see sw-settings-country-state.scss.
 */
const NARROW_VIEWPORT_MAX_WIDTH = 1280;

/**
 * Manages the region tree of a country: states/provinces on the first, cities on the second and
 * districts on the third level. All three columns are rendered from the same template, the level
 * specific behaviour is derived from the `levels` list.
 *
 * @sw-package fundamentals@discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        country: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        countryStateRepository: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            levels: ['state', 'city', 'district'],
            regions: {
                state: [],
                city: [],
                district: [],
            },
            terms: {
                state: null,
                city: null,
                district: null,
            },
            totals: {
                state: 0,
                city: 0,
                district: 0,
            },
            loading: {
                state: false,
                city: false,
                district: false,
            },
            selected: {
                state: null,
                city: null,
            },
            currentRegion: null,
            currentRegionLevel: 'state',
            deleteTarget: null,
            childCounts: {
                city: 0,
                district: 0,
            },
            isLoadingChildCounts: false,
            isDeletingRegion: false,
        };
    },

    computed: {
        isNewCountry() {
            return typeof this.country?.isNew === 'function' && this.country.isNew();
        },

        canEdit() {
            return this.acl.can('country.editor');
        },

        regionTexts() {
            return {
                state: {
                    label: this.$t('sw-settings-country.detail.region.columnState'),
                    addLabel: this.$t('sw-settings-country.detail.region.buttonAddState'),
                    placeholder: this.$t('sw-settings-country.detail.region.placeholderSearchState'),
                },
                city: {
                    label: this.$t('sw-settings-country.detail.region.columnCity'),
                    addLabel: this.$t('sw-settings-country.detail.region.buttonAddCity'),
                    placeholder: this.$t('sw-settings-country.detail.region.placeholderSearchCity'),
                },
                district: {
                    label: this.$t('sw-settings-country.detail.region.columnDistrict'),
                    addLabel: this.$t('sw-settings-country.detail.region.buttonAddDistrict'),
                    placeholder: this.$t('sw-settings-country.detail.region.placeholderSearchDistrict'),
                },
            };
        },

        selectedPath() {
            return [
                this.getRegionName(this.getSelectedRegion('state')),
                this.getRegionName(this.getSelectedRegion('city')),
            ]
                .filter((name) => name.length > 0)
                .join(' › ');
        },
    },

    watch: {
        country() {
            return this.loadAll();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            return this.loadAll();
        },

        loadAll() {
            this.resetRegions();

            if (this.isNewCountry) {
                return Promise.resolve();
            }

            return Promise.all([
                this.loadRegions('state'),
                this.refreshTotals(),
            ]);
        },

        resetRegions() {
            this.regions.state = [];
            this.regions.city = [];
            this.regions.district = [];
            this.selected.state = null;
            this.selected.city = null;
            this.terms.state = null;
            this.terms.city = null;
            this.terms.district = null;
            this.totals.state = 0;
            this.totals.city = 0;
            this.totals.district = 0;
            this.currentRegion = null;
            this.deleteTarget = null;
        },

        getRepository(level) {
            return this.repositoryFactory.create(REPOSITORY_BY_LEVEL[level]);
        },

        /**
         * The parent a region level hangs off: the country for states, the selected state for
         * cities and the selected city for districts.
         */
        getParentId(level) {
            if (level === 'state') {
                return this.country?.id ?? null;
            }

            if (level === 'city') {
                return this.selected.state;
            }

            return this.selected.city;
        },

        getCriteria(level, parentId) {
            const criteria = new Criteria(1, 500);

            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            if (level === 'state') {
                criteria.addFilter(Criteria.equals('countryId', parentId));
            } else if (level === 'city') {
                criteria.addFilter(Criteria.equals('countryStateId', parentId));
            } else {
                criteria.addFilter(Criteria.equals('countryCityId', parentId));
            }

            return criteria;
        },

        loadRegions(level) {
            const parentId = this.getParentId(level);

            if (!parentId) {
                this.regions[level] = [];
                return Promise.resolve();
            }

            this.loading[level] = true;

            return this.getRepository(level)
                .search(this.getCriteria(level, parentId), Shopwell.Context.api)
                .then((result) => {
                    this.regions[level] = result;
                    this.loading[level] = false;
                })
                .catch(() => {
                    this.regions[level] = [];
                    this.loading[level] = false;
                    this.createNotificationError({
                        message: this.$t('sw-settings-country.detail.region.messageLoadError'),
                    });
                });
        },

        refreshTotals() {
            if (this.isNewCountry) {
                return Promise.resolve();
            }

            return Promise.all(this.levels.map((level) => this.countRegions(level)));
        },

        countRegions(level) {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(1);
            criteria.addFilter(Criteria.equals('countryId', this.country.id));

            return this.getRepository(level)
                .search(criteria, Shopwell.Context.api)
                .then((result) => {
                    this.totals[level] = result.total;
                })
                .catch(() => {
                    this.totals[level] = 0;
                });
        },

        onClickRegion(level, region) {
            // There is nothing below the district level, so a click always opens the editor.
            if (level === 'district' || this.isSelected(level, region)) {
                this.onEditRegion(level, region);
                return;
            }

            if (level === 'state') {
                this.selected.state = region.id;
                this.selected.city = null;
                this.regions.city = [];
                this.regions.district = [];
                this.terms.city = null;
                this.terms.district = null;

                this.loadRegions('city').then(() => this.scrollLevelIntoView('city'));
                return;
            }

            this.selected.city = region.id;
            this.regions.district = [];
            this.terms.district = null;

            this.loadRegions('district').then(() => this.scrollLevelIntoView('district'));
        },

        /**
         * On a narrow screen the levels are stacked, so the level that was just revealed sits below
         * the fold. Scrolling it into view keeps the cascade understandable without a tap on a
         * column that the user cannot see yet.
         */
        scrollLevelIntoView(level) {
            const index = this.levels.indexOf(level);

            if (index < 0 || typeof window === 'undefined' || window.innerWidth > NARROW_VIEWPORT_MAX_WIDTH) {
                return;
            }

            const columns =
                typeof document === 'undefined' ? null : document.querySelectorAll('.sw-settings-country-state__column');

            columns?.[index]?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
        },

        onAddRegion(level) {
            if (this.isLevelDisabled(level)) {
                return;
            }

            const region = this.getRepository(level).create(Shopwell.Context.api);

            region.countryId = this.country.id;
            region.position = this.getItems(level).length + 1;
            region.active = true;

            if (level === 'city') {
                region.countryStateId = this.selected.state;
            }

            if (level === 'district') {
                region.countryStateId = this.selected.state;
                region.countryCityId = this.selected.city;
            }

            this.currentRegionLevel = level;
            this.currentRegion = region;
        },

        onEditRegion(level, region) {
            if (!this.canEdit) {
                return;
            }

            // Edit a copy so a cancelled dialog does not modify the list entry.
            const copy = this.getRepository(level).create(Shopwell.Context.api, region.id);
            copy._isNew = false;

            this.currentRegionLevel = level;
            this.currentRegion = Object.assign(copy, region);
        },

        onSaveRegion(region) {
            const level = this.currentRegionLevel;
            const name = this.getRegionName(region);

            return this.getRepository(level)
                .save(region, Shopwell.Context.api)
                .then(() => {
                    this.currentRegion = null;

                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-country.detail.region.messageSaveSuccess', { name }),
                    });

                    return this.loadRegions(level).then(() => this.refreshTotals());
                })
                .catch((error) => {
                    if (error?.response?.data?.errors?.[0]?.code === 'MISSING-SYSTEM-TRANSLATION') {
                        this.createNotificationError({
                            message: this.$t('sw-country-state-detail.createNewStateError'),
                        });
                        return;
                    }

                    this.createNotificationError({
                        message: this.$t('sw-settings-country.detail.region.messageSaveError', { name }),
                    });
                });
        },

        onCancelRegion() {
            this.currentRegion = null;
        },

        openDeleteModal(level, region) {
            this.deleteTarget = { level, region };
            this.childCounts.city = 0;
            this.childCounts.district = 0;
            this.isLoadingChildCounts = true;

            Promise.all(this.getChildCountQueries(level, region))
                .then(() => {
                    this.isLoadingChildCounts = false;
                })
                .catch(() => {
                    this.isLoadingChildCounts = false;
                });
        },

        /**
         * A state cascades into its cities and districts, a city into its districts. The confirm
         * dialog tells the merchant how many records a delete would take with it.
         */
        getChildCountQueries(level, region) {
            const queries = [];

            if (level === 'state') {
                queries.push(this.countChildren('city', 'countryStateId', region.id));
                queries.push(this.countChildren('district', 'countryStateId', region.id));
            }

            if (level === 'city') {
                queries.push(this.countChildren('district', 'countryCityId', region.id));
            }

            return queries;
        },

        countChildren(level, filterName, id) {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(1);
            criteria.addFilter(Criteria.equals(filterName, id));

            return this.getRepository(level)
                .search(criteria, Shopwell.Context.api)
                .then((result) => {
                    this.childCounts[level] = result.total;
                })
                .catch(() => {
                    this.childCounts[level] = 0;
                });
        },

        onCloseDeleteModal() {
            this.deleteTarget = null;
            this.isLoadingChildCounts = false;
        },

        onConfirmDelete() {
            if (!this.deleteTarget) {
                return Promise.resolve();
            }

            const { level, region } = this.deleteTarget;
            const deletedId = region.id;
            const name = this.getRegionName(region);

            this.isDeletingRegion = true;

            return this.getRepository(level)
                .delete(deletedId, Shopwell.Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-country.detail.region.messageDeleteSuccess', { name }),
                    });

                    this.clearSelectionForLevel(level, deletedId);

                    return this.loadRegions(level).then(() => this.refreshTotals());
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-country.detail.region.messageDeleteError', { name }),
                    });
                })
                .finally(() => {
                    this.isDeletingRegion = false;
                    this.onCloseDeleteModal();
                });
        },

        clearSelectionForLevel(level, deletedId) {
            if (level === 'state' && this.selected.state === deletedId) {
                this.selected.state = null;
                this.selected.city = null;
                this.regions.city = [];
                this.regions.district = [];
                return;
            }

            if (level === 'city' && this.selected.city === deletedId) {
                this.selected.city = null;
                this.regions.district = [];
            }
        },

        isSelected(level, region) {
            if (level === 'state') {
                return this.selected.state === region.id;
            }

            if (level === 'city') {
                return this.selected.city === region.id;
            }

            return false;
        },

        isLevelDisabled(level) {
            if (!this.canEdit || this.isNewCountry) {
                return true;
            }

            if (level === 'city') {
                return !this.selected.state;
            }

            if (level === 'district') {
                return !this.selected.city;
            }

            return false;
        },

        isLoadingLevel(level) {
            return this.loading[level];
        },

        getSelectedRegion(level) {
            const id = this.selected[level];

            if (!id) {
                return null;
            }

            return this.regions[level].find((region) => region.id === id) ?? null;
        },

        getItems(level) {
            const items = this.regions[level];
            const term = this.terms[level];

            if (!term || term.length === 0) {
                return items;
            }

            const needle = term.toLowerCase();

            return items.filter((item) => {
                return (
                    this.getRegionName(item).toLowerCase().includes(needle) ||
                    String(this.getRegionCode(level, item)).toLowerCase().includes(needle)
                );
            });
        },

        getRegionName(region) {
            return region?.translated?.name || region?.name || '';
        },

        getRegionCode(level, region) {
            if (!region) {
                return '';
            }

            return (level === 'state' ? region.shortCode : region.code) ?? '';
        },

        getEmptyHint(level) {
            if (this.terms[level]) {
                return this.$t('sw-settings-country.detail.region.hintNoSearchResult', { term: this.terms[level] });
            }

            if (level === 'city' && !this.selected.state) {
                return this.$t('sw-settings-country.detail.region.hintSelectState');
            }

            if (level === 'district' && !this.selected.city) {
                return this.$t('sw-settings-country.detail.region.hintSelectCity');
            }

            if (level === 'state') {
                return this.$t('sw-settings-country.detail.region.hintEmptyState');
            }

            if (level === 'city') {
                return this.$t('sw-settings-country.detail.region.hintEmptyCity', {
                    name: this.getRegionName(this.getSelectedRegion('state')),
                });
            }

            return this.$t('sw-settings-country.detail.region.hintEmptyDistrict', {
                name: this.getRegionName(this.getSelectedRegion('city')),
            });
        },

        getAddButtonTooltip(level) {
            if (!this.canEdit) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    showOnDisabledElements: true,
                };
            }

            if (this.isLevelDisabled(level)) {
                return {
                    message:
                        level === 'city'
                            ? this.$t('sw-settings-country.detail.region.hintSelectState')
                            : this.$t('sw-settings-country.detail.region.hintSelectCity'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },

        getDeleteConfirmMessage() {
            if (!this.deleteTarget) {
                return '';
            }

            const { level, region } = this.deleteTarget;
            const name = this.getRegionName(region);

            if (level === 'state') {
                return this.$t('sw-settings-country.detail.region.textDeleteStateConfirm', {
                    name,
                    city: this.childCounts.city,
                    district: this.childCounts.district,
                });
            }

            if (level === 'city') {
                return this.$t('sw-settings-country.detail.region.textDeleteCityConfirm', {
                    name,
                    district: this.childCounts.district,
                });
            }

            return this.$t('sw-settings-country.detail.region.textDeleteDistrictConfirm', { name });
        },
    },
};
