import Plugin from 'src/plugin-system/plugin.class';

/**
 * Cascading selects for the administrative divisions below the province level.
 *
 * Countries like China administrate their addresses in three levels: province, city and district.
 * The province reuses the country state select, the two lower levels get their own selects which
 * are loaded on demand and stay hidden for every country that does not provide cities.
 *
 * @package discovery
 */
export default class CountryCitySelectPlugin extends Plugin {
    static options = {
        scopeElementSelector: null,
        prefix: null,
        countrySelectSelector: '.country-select',
        countryStateSelectSelector: '.country-state-select',
        countryCitySelectSelector: '.country-city-select',
        countryDistrictSelectSelector: '.country-district-select',
        placeholderOptionSelector: '[data-placeholder-option="true"]',
        cityInputSelector: '[name$="[city]"]',
        initialCountryStateAttribute: 'data-initial-country-state-id',
        initialCountryCityAttribute: 'data-initial-country-city-id',
        initialCountryDistrictAttribute: 'data-initial-country-district-id',
        cityDataRoute: 'frontend.country.city-data',
        districtDataRoute: 'frontend.country.district-data',
        /**
         * The plain city input of the address form becomes redundant as soon as the cascade is
         * shown: the selected city is written into it and the field stops being required, so
         * documents and exports still carry a readable city. Set to false to keep the input
         * untouched.
         */
        syncCityInput: true,
    };

    init() {
        this.scopeElement = this.el;

        if (this.options.scopeElementSelector) {
            this.scopeElement = document.querySelector(this.options.scopeElementSelector) ?? this.el;
        }

        this.countrySelect = this.scopeElement.querySelector(CountryCitySelectPlugin.options.countrySelectSelector);
        this.countryStateSelect = this.scopeElement.querySelector(CountryCitySelectPlugin.options.countryStateSelectSelector);

        if (!this.countryStateSelect) {
            return;
        }

        this.countryCitySelect = this.el.querySelector(CountryCitySelectPlugin.options.countryCitySelectSelector);
        this.countryDistrictSelect = this.el.querySelector(CountryCitySelectPlugin.options.countryDistrictSelectSelector);

        if (!this.countryCitySelect || !this.countryDistrictSelect) {
            return;
        }

        this.cityInput = this.options.syncCityInput
            ? this.scopeElement.querySelector(CountryCitySelectPlugin.options.cityInputSelector)
            : null;

        const initialStateId = this.countryStateSelect.getAttribute(this.options.initialCountryStateAttribute);
        const initialCityId = this.countryCitySelect.getAttribute(this.options.initialCountryCityAttribute);
        const initialDistrictId = this.countryDistrictSelect.getAttribute(this.options.initialCountryDistrictAttribute);

        this.countrySelect?.addEventListener('change', this.onChangeCountry.bind(this));
        this.countryStateSelect.addEventListener('change', this.onChangeState.bind(this));
        this.countryCitySelect.addEventListener('change', this.onChangeCity.bind(this));

        if (initialStateId) {
            this.requestCities(initialStateId, initialCityId, initialDistrictId);
        }
    }

    /**
     * The country state select is repopulated asynchronously, so the cascade is reset instead of
     * following the new states right away.
     */
    onChangeCountry() {
        this.resetLevel(this.countryCitySelect);
        this.resetLevel(this.countryDistrictSelect);
        this.setCascadeVisible(false);
        this.syncCityInput();
    }

    onChangeState(event) {
        const countryStateId = event.target.value;

        this.resetLevel(this.countryCitySelect);
        this.resetLevel(this.countryDistrictSelect);

        if (!countryStateId) {
            this.setCascadeVisible(false);
            this.syncCityInput();

            return;
        }

        this.requestCities(countryStateId);
    }

    onChangeCity(event) {
        const countryCityId = event.target.value;

        this.resetLevel(this.countryDistrictSelect);
        this.syncCityInput();

        if (!countryCityId) {
            this.setDistrictVisible(false);

            return;
        }

        this.requestDistricts(countryCityId);
    }

    requestCities(countryStateId, selectedCityId = null, selectedDistrictId = null) {
        const route = window.router?.[this.options.cityDataRoute];

        if (!route) {
            return;
        }

        fetch(`${route}?countryStateId=${encodeURIComponent(countryStateId)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then((response) => response.json())
            .then((content) => {
                this.updateCities(content.cities ?? [], selectedCityId);

                if (selectedCityId) {
                    this.requestDistricts(selectedCityId, selectedDistrictId);
                }
            })
            .catch(() => this.setCascadeVisible(false));
    }

    requestDistricts(countryCityId, selectedDistrictId = null) {
        const route = window.router?.[this.options.districtDataRoute];

        if (!route) {
            return;
        }

        fetch(`${route}?countryCityId=${encodeURIComponent(countryCityId)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then((response) => response.json())
            .then((content) => this.updateDistricts(content.districts ?? [], selectedDistrictId))
            .catch(() => this.setDistrictVisible(false));
    }

    updateCities(cities, selectedCityId = null) {
        this.setOptions(this.countryCitySelect, cities, selectedCityId);

        const hasCities = cities.length > 0;

        this.setCascadeVisible(hasCities);
        this.setCityRequired(hasCities);

        if (!hasCities) {
            this.resetLevel(this.countryDistrictSelect);
        }

        this.syncCityInput();
    }

    updateDistricts(districts, selectedDistrictId = null) {
        this.setOptions(this.countryDistrictSelect, districts, selectedDistrictId);

        this.setDistrictVisible(districts.length > 0);
    }

    setOptions(select, entries, selectedId = null) {
        this.removeOptions(select);

        if (entries.length === 0) {
            select.setAttribute('disabled', 'disabled');

            return;
        }

        select.removeAttribute('disabled');

        entries.forEach((entry) => select.append(this.createOption(entry, selectedId)));
    }

    removeOptions(select) {
        select.querySelectorAll(`option:not(${this.options.placeholderOptionSelector})`).forEach((option) => option.remove());
    }

    resetLevel(select) {
        this.removeOptions(select);
        select.value = '';
        select.setAttribute('disabled', 'disabled');
    }

    createOption(entry, selectedId) {
        const option = document.createElement('option');

        option.setAttribute('value', entry.id);
        option.textContent = entry.translated?.name ?? '';

        if (entry.id === selectedId) {
            option.setAttribute('selected', 'selected');
        }

        return option;
    }

    setCascadeVisible(visible) {
        this.el.classList.toggle('d-none', !visible);

        if (!visible) {
            this.setDistrictVisible(false);
        }
    }

    setDistrictVisible(visible) {
        this.countryDistrictSelect.closest('.form-group')?.classList.toggle('d-none', !visible);
    }

    setCityRequired(required) {
        this.setRequired(this.countryCitySelect, required);
    }

    /**
     * The form validation helper cannot mark a field without a label as not required, so both
     * branches are skipped for fields a theme rendered without one.
     */
    setRequired(field, required) {
        if (!window.formValidation || !field?.id || !document.querySelector(`[for="${field.id}"]`)) {
            return;
        }

        if (required) {
            window.formValidation.setFieldRequired(field);

            return;
        }

        window.formValidation.setFieldNotRequired(field);
    }

    /**
     * Mirrors the selected region into the plain city input, which is hidden while the cascade is
     * in charge of the city.
     */
    syncCityInput() {
        if (!this.cityInput) {
            return;
        }

        const cascadeVisible = !this.el.classList.contains('d-none');

        this.cityInput.closest('.form-group')?.classList.toggle('d-none', cascadeVisible);

        this.setRequired(this.cityInput, !cascadeVisible);

        if (cascadeVisible) {
            this.cityInput.value = this.getSelectedName(this.countryCitySelect) ?? this.getSelectedName(this.countryDistrictSelect) ?? '';
        }
    }

    getSelectedName(select) {
        return select.value ? select.options[select.selectedIndex]?.textContent.trim() : null;
    }
}
