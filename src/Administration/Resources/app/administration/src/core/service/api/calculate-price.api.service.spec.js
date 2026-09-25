/**
 * @sw-package checkout
 */
import CalculatePriceService from 'src/core/service/api/calculate-price.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createCalculatePriceService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopwell.Context.api);
    const calculatePriceService = new CalculatePriceService(client, loginService);
    return { calculatePriceService, clientMock };
}

describe('calculatePriceService', () => {
    it('calls the correct api endpoint to calculate prices', async () => {
        const { calculatePriceService, clientMock } = createCalculatePriceService();
        const taxId = Shopwell.Utils.createId();
        const productId = Shopwell.Utils.createId();
        const currencyId = Shopwell.Utils.createId();
        const prices = {
            [productId]: [
                {
                    price: 10,
                    currencyId,
                },
            ],
        };

        clientMock
            .onPost('/api/_action/calculate-prices', {
                taxId,
                prices,
            })
            .reply(200, {
                data: {},
            });

        await calculatePriceService.calculatePrices(taxId, prices);

        expect(clientMock.history.post).toHaveLength(1);
    });
});
