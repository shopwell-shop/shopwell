/**
 * @sw-package innovation
 */

import ShopwellDiscountCampaignService from 'src/app/service/discount-campaign.service';

jest.useFakeTimers();

describe('marketing.store', () => {
    let store = Shopwell.Store.get('marketing');

    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopwell.Service().register('shopwellDiscountCampaignService', () => {
            return new ShopwellDiscountCampaignService();
        });
    });

    beforeEach(() => {
        store = Shopwell.Store.get('marketing');
        store.campaign = {};

        jest.setSystemTime(new Date('2000-01-31').getTime());
    });

    it('has initial state', () => {
        expect(store.campaign).toStrictEqual({});
    });

    it('should set a new campaign', () => {
        Shopwell.Store.get('marketing').setCampaign({
            name: 'Example campaign',
            components: {},
        });

        expect(Shopwell.Store.get('marketing').campaign).toEqual({
            name: 'Example campaign',
            components: {},
        });
    });

    it('should return the active campaign when times match', () => {
        // set date in active campaign time
        jest.setSystemTime(new Date('2005-08-17').getTime());

        Shopwell.Store.get('marketing').setCampaign({
            name: 'Active campaign',
            startDate: '2005-08-15T15:52:01',
            endDate: '2005-08-20T15:52:01',
        });

        const activeCampaign = Shopwell.Store.get('marketing').getActiveCampaign;
        expect(activeCampaign?.name).toBe('Active campaign');
    });

    it('should return null when times does not match', () => {
        // set date outside the active campaign time
        jest.setSystemTime(new Date('2005-08-21').getTime());

        Shopwell.Store.get('marketing').setCampaign({
            name: 'Inactive campaign',
            startDate: '2005-08-15T15:52:01',
            endDate: '2005-08-20T15:52:01',
        });

        const activeCampaign = Shopwell.Store.get('marketing').getActiveCampaign;
        expect(activeCampaign).toBeNull();
    });

    it('should return the correct component for the store banner when time match', () => {
        // set date in active campaign time
        jest.setSystemTime(new Date('2005-08-17').getTime());

        const storeBanner = {
            background: {
                color: '#ffffff',
                image: 'http://www.company.org/cum/sonoras',
                position: 'string',
            },
            content: {
                textColor: '#000000',
                headline: {
                    'de-DE': 'string (max 40 Zeichen)',
                    'en-GB': 'string (max 40 characters)',
                },
                description: {
                    'de-DE': 'string (max 90 Zeichen)',
                    'en-GB': 'string (max 90 characters)',
                },
                cta: {
                    category: 'CategoryXY',
                    'de-DE': 'string (max 40 Zeichen)',
                    'en-GB': 'string (max 40 characters)',
                },
            },
        };

        Shopwell.Store.get('marketing').setCampaign({
            name: 'Active campaign',
            startDate: '2005-08-15T15:52:01',
            endDate: '2005-08-20T15:52:01',
            components: { storeBanner: storeBanner },
        });

        const storeComponent = Shopwell.Store.get('marketing').getActiveCampaignDataForComponent('storeBanner');
        expect(storeComponent).toEqual({
            campaignName: 'Active campaign',
            component: storeBanner,
        });
    });

    it('should return null for the store banner when time does not match', () => {
        // set date in active campaign time
        jest.setSystemTime(new Date('2005-08-21').getTime());

        const storeBanner = {
            background: {
                color: '#ffffff',
                image: 'http://www.company.org/cum/sonoras',
                position: 'string',
            },
            content: {
                textColor: '#000000',
                headline: {
                    'de-DE': 'string (max 40 Zeichen)',
                    'en-GB': 'string (max 40 characters)',
                },
                description: {
                    'de-DE': 'string (max 90 Zeichen)',
                    'en-GB': 'string (max 90 characters)',
                },
                cta: {
                    category: 'CategoryXY',
                    'de-DE': 'string (max 40 Zeichen)',
                    'en-GB': 'string (max 40 characters)',
                },
            },
        };

        Shopwell.Store.get('marketing').setCampaign({
            name: 'Active campaign',
            startDate: '2005-08-15T15:52:01',
            endDate: '2005-08-20T15:52:01',
            components: {
                storeBanner: storeBanner,
            },
        });

        const storeComponent = Shopwell.Store.get('marketing').getActiveCampaignDataForComponent('storeBanner');
        expect(storeComponent).toEqual({
            campaignName: undefined,
            component: null,
        });
    });
});
