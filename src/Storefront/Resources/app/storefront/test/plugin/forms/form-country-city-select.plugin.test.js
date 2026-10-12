import CountryCitySelectPlugin from 'src/plugin/forms/form-country-city-select.plugin';
import FormValidation from 'src/helper/form-validation.helper';

/**
 * @package discovery
 */
describe('Form country city select plugin', () => {
    const CITY_ID = '0490081418be4255b87731afc953e901';
    const DISTRICT_ID = '1f0d9b0b5d6c4a2c8b6c1f2b7a3d9e04';

    const template = `
        <form id="registerForm" class="register-billing" action="/register" method="post">
            <div class="form-group col-md-3 col-8">
                <label class="form-label" for="billingAddressAddressCity">City</label>
                <input type="text"
                       class="form-control"
                       id="billingAddressAddressCity"
                       name="billingAddress[city]"
                       data-validation="required">
            </div>

            <select class="country-select" data-initial-country-id="china">
                <option value="china" selected="selected" data-state-required="1" data-display-state-in-registration="1">China</option>
                <option value="germany">Germany</option>
            </select>

            <select class="country-state-select" data-initial-country-state-id>
                <option value="" selected="selected" data-placeholder-option="true">Select state...</option>
            </select>

            <div class="col-12 d-none"
                 data-country-city-select="true"
                 data-country-city-select-options='{"scopeElementSelector":".register-billing","prefix":"billingAddress"}'>
                <div class="row g-2">
                    <div class="form-group col-md-6">
                        <label class="form-label" for="billingAddressAddressCountryCity">City</label>
                        <select class="form-select country-city-select"
                                id="billingAddressAddressCountryCity"
                                name="billingAddress[countryCityId]"
                                data-initial-country-city-id>
                            <option value="" selected="selected" data-placeholder-option="true">Select city...</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label" for="billingAddressAddressCountryDistrict">District</label>
                        <select class="form-select country-district-select"
                                id="billingAddressAddressCountryDistrict"
                                name="billingAddress[countryDistrictId]"
                                data-initial-country-district-id>
                            <option value="" selected="selected" data-placeholder-option="true">Select district...</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    `;

    let fetchCalls = [];

    function createPlugin(pluginOptions = {}) {
        const mockElement = document.querySelector('[data-country-city-select]');

        return new CountryCitySelectPlugin(mockElement, { scopeElementSelector: '.register-billing', ...pluginOptions });
    }

    function flushPromises() {
        return new Promise((resolve) => {
            setTimeout(resolve, 0);
        });
    }

    function selectValue(selector, value) {
        const select = document.querySelector(selector);

        if (!Array.from(select.options).some((option) => option.value === value)) {
            const option = document.createElement('option');
            option.value = value;

            select.append(option);
        }

        select.value = value;
        select.dispatchEvent(new Event('change'));
    }

    function optionValues(selector) {
        return Array.from(document.querySelectorAll(`${selector} option:not([data-placeholder-option])`)).map((option) => option.value);
    }

    beforeEach(() => {
        document.body.innerHTML = template;
        fetchCalls = [];

        window.router = {
            'frontend.country.city-data': '/country/country-city-data',
            'frontend.country.district-data': '/country/country-district-data',
        };

        window.validationMessages = {
            required: 'Input should not be empty.',
        };

        window.formValidation = new FormValidation();

        global.fetch = jest.fn((url) => {
            fetchCalls.push(url);

            const body = url.includes('country-city-data')
                ? {
                    cities: [
                        { id: CITY_ID, translated: { name: '北京市' } },
                    ],
                }
                : {
                    districts: [
                        { id: DISTRICT_ID, translated: { name: '朝阳区' } },
                    ],
                };

            return Promise.resolve({ json: () => Promise.resolve(body) });
        });
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('should instantiate the plugin', async () => {
        const plugin = createPlugin();

        await flushPromises();

        expect(plugin instanceof CountryCitySelectPlugin).toBe(true);
    });

    it('should keep the cascade hidden without an initial state', async () => {
        createPlugin();

        await flushPromises();

        expect(fetchCalls).toHaveLength(0);
        expect(document.querySelector('[data-country-city-select]').classList.contains('d-none')).toBe(true);
    });

    it('should load the cities of an initial state and reveal the cascade', async () => {
        document.querySelector('.country-state-select').setAttribute('data-initial-country-state-id', 'state-beijing');

        createPlugin();

        await flushPromises();

        expect(fetchCalls[0]).toBe('/country/country-city-data?countryStateId=state-beijing');
        expect(optionValues('.country-city-select')).toEqual([CITY_ID]);
        expect(document.querySelector('[data-country-city-select]').classList.contains('d-none')).toBe(false);
    });

    it('should select the initial city and load its districts', async () => {
        document.querySelector('.country-state-select').setAttribute('data-initial-country-state-id', 'state-beijing');
        document.querySelector('.country-city-select').setAttribute('data-initial-country-city-id', CITY_ID);
        document.querySelector('.country-district-select').setAttribute('data-initial-country-district-id', DISTRICT_ID);

        createPlugin();

        await flushPromises();

        expect(fetchCalls).toEqual([
            '/country/country-city-data?countryStateId=state-beijing',
            `/country/country-district-data?countryCityId=${CITY_ID}`,
        ]);
        expect(document.querySelector('.country-city-select').value).toBe(CITY_ID);
        expect(document.querySelector('.country-district-select').value).toBe(DISTRICT_ID);
        expect(document.querySelector('.country-district-select').closest('.form-group').classList.contains('d-none')).toBe(false);
    });

    it('should load the cities when a state is selected', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        expect(fetchCalls[0]).toBe('/country/country-city-data?countryStateId=state-beijing');
        expect(optionValues('.country-city-select')).toEqual([CITY_ID]);
    });

    it('should load the districts when a city is selected', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        selectValue('.country-city-select', CITY_ID);

        await flushPromises();

        expect(fetchCalls[1]).toBe(`/country/country-district-data?countryCityId=${CITY_ID}`);
        expect(optionValues('.country-district-select')).toEqual([DISTRICT_ID]);
        expect(document.querySelector('.country-district-select').closest('.form-group').classList.contains('d-none')).toBe(false);
    });

    it('should reset both levels when the country changes', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        selectValue('.country-city-select', CITY_ID);

        await flushPromises();

        document.querySelector('.country-select').value = 'germany';
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        expect(optionValues('.country-city-select')).toEqual([]);
        expect(optionValues('.country-district-select')).toEqual([]);
        expect(document.querySelector('.country-city-select').disabled).toBe(true);
        expect(document.querySelector('[data-country-city-select]').classList.contains('d-none')).toBe(true);
        expect(document.querySelector('.country-district-select').closest('.form-group').classList.contains('d-none')).toBe(true);
    });

    it('should keep the cascade hidden when the state has no cities', async () => {
        global.fetch = jest.fn((url) => {
            fetchCalls.push(url);

            return Promise.resolve({ json: () => Promise.resolve({ cities: [] }) });
        });

        createPlugin();

        selectValue('.country-state-select', 'state-hamburg');

        await flushPromises();

        expect(document.querySelector('[data-country-city-select]').classList.contains('d-none')).toBe(true);
        expect(document.querySelector('.country-city-select').disabled).toBe(true);
    });

    it('should hide the district select when the city has no districts', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        global.fetch = jest.fn(() => Promise.resolve({ json: () => Promise.resolve({ districts: [] }) }));

        selectValue('.country-city-select', CITY_ID);

        await flushPromises();

        expect(document.querySelector('.country-district-select').closest('.form-group').classList.contains('d-none')).toBe(true);
    });

    it('should take over the plain city input while the cascade is visible', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        const cityInput = document.querySelector('#billingAddressAddressCity');

        expect(cityInput.closest('.form-group').classList.contains('d-none')).toBe(true);
        expect(cityInput.hasAttribute('aria-required')).toBe(false);

        selectValue('.country-city-select', CITY_ID);

        await flushPromises();

        expect(cityInput.value).toBe('北京市');
    });

    it('should give the city input back when the country changes', async () => {
        createPlugin();

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        document.querySelector('.country-select').value = 'germany';
        document.querySelector('.country-select').dispatchEvent(new Event('change'));

        const cityInput = document.querySelector('#billingAddressAddressCity');

        expect(cityInput.closest('.form-group').classList.contains('d-none')).toBe(false);
        expect(cityInput.getAttribute('aria-required')).toBe('true');
    });

    it('should leave the city input untouched with syncCityInput disabled', async () => {
        createPlugin({ syncCityInput: false });

        selectValue('.country-state-select', 'state-beijing');

        await flushPromises();

        const cityInput = document.querySelector('#billingAddressAddressCity');

        expect(cityInput.closest('.form-group').classList.contains('d-none')).toBe(false);
        expect(cityInput.value).toBe('');
    });
});
