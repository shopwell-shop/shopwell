/**
 * @sw-package after-sales
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import OrderReturnApiService, {
    ORDER_RETURN_ACTIONS,
    ORDER_RETURN_STATE_MACHINE,
    ORDER_RETURN_TYPES,
    formatReturnAmount,
    returnStateLabelKey,
    returnStateVariant,
    returnTypeLabelKey,
} from './order-return.api.service';

type Response = { data: Record<string, unknown> };

type PostArguments = [path: string, body: unknown, options: { headers: Record<string, string> }];

type HttpClientMock = {
    get: jest.Mock<Promise<Response>, [path: string, options?: { headers: Record<string, string> }]>;
    post: jest.Mock<Promise<Response>, PostArguments>;
};

type LoginServiceMock = {
    getToken: jest.Mock<string, []>;
};

function createService(): { service: OrderReturnApiService; httpClient: HttpClientMock; loginService: LoginServiceMock } {
    const httpClient: HttpClientMock = {
        get: jest.fn<Promise<Response>, [path: string, options?: { headers: Record<string, string> }]>(),
        post: jest.fn<Promise<Response>, PostArguments>(),
    };

    const loginService: LoginServiceMock = {
        getToken: jest.fn<string, []>(() => 'token-123'),
    };

    const service = new OrderReturnApiService(httpClient as unknown as HttpClient, loginService as unknown as LoginService);

    return { service, httpClient, loginService };
}

describe('src/module/sw-order-return/service/order-return.api.service', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('declares the state machine and the two kinds of a return', () => {
        expect(ORDER_RETURN_STATE_MACHINE).toBe('order_return.state');
        expect(ORDER_RETURN_TYPES.REFUND_ONLY).toBe('refund_only');
        expect(ORDER_RETURN_TYPES.RETURN_REFUND).toBe('return_refund');
    });

    it('only offers a decision in the states that allow it', () => {
        expect(ORDER_RETURN_ACTIONS.approve).toEqual(['requested']);
        expect(ORDER_RETURN_ACTIONS.decline).toEqual(['requested']);
        expect(ORDER_RETURN_ACTIONS.cancel).toEqual(['requested', 'open', 'returning']);
        expect(ORDER_RETURN_ACTIONS.receive).toEqual(['returning']);
        expect(ORDER_RETURN_ACTIONS.refund).toEqual(['received', 'returning']);
    });

    it('builds the auth headers from the token of the login service', () => {
        const { service } = createService();

        expect(service.getAuthHeaders()).toEqual({
            Accept: 'application/json',
            Authorization: 'Bearer token-123',
            'Content-Type': 'application/json',
        });
    });

    it('posts every decision to its own action endpoint', async () => {
        const { service, httpClient } = createService();
        httpClient.post.mockResolvedValue({ data: { orderReturn: { id: 'return-1' } } });

        await service.approve('return-1');
        await service.receive('return-1');
        await service.refund('return-1');
        await service.decline('return-1', 'the goods were damaged');
        await service.cancel('return-1', null);
        await service.addMessage('return-1', 'called the customer');

        expect(httpClient.post.mock.calls.map(([path, body]) => [path, body])).toEqual([
            ['/_action/order-return/return-1/approve', null],
            ['/_action/order-return/return-1/receive', null],
            ['/_action/order-return/return-1/refund', null],
            ['/_action/order-return/return-1/decline', { reason: 'the goods were damaged' }],
            ['/_action/order-return/return-1/cancel', { note: null }],
            ['/_action/order-return/return-1/message', { content: 'called the customer' }],
        ]);

        httpClient.post.mock.calls.forEach(([, , options]) => {
            expect(options).toEqual({ headers: service.getAuthHeaders() });
        });
    });

    it('answers with the return the endpoint decided on', async () => {
        const { service, httpClient } = createService();
        httpClient.post.mockResolvedValue({ data: { orderReturn: { id: 'return-1', state: 'open' } } });

        await expect(service.approve('return-1')).resolves.toEqual({ id: 'return-1', state: 'open' });
    });

    it('answers with null when the endpoint carries no return', async () => {
        const { service, httpClient } = createService();
        httpClient.post.mockResolvedValue({ data: {} });

        await expect(service.approve('return-1')).resolves.toBeNull();
    });

    it('reads the positions a return can still be opened with from the order', async () => {
        const { service, httpClient } = createService();
        const formData = {
            orderNumber: '10001',
            currency: 'CNY',
            decimals: 2,
            positions: [{ lineItemId: 'line-1', label: 'Shoe', quantity: 2, returnable: 1, unitPrice: 19900 }],
            reasons: [{ id: 'reason-1', label: 'broken' }],
        };
        httpClient.get.mockResolvedValue({ data: formData });

        await expect(service.formData('order-1')).resolves.toEqual(formData);
        expect(httpClient.get).toHaveBeenCalledWith('/_action/order-return/order/order-1/positions', {
            headers: service.getAuthHeaders(),
        });
    });

    it('opens a return on behalf of the customer of an order', async () => {
        const { service, httpClient } = createService();
        httpClient.post.mockResolvedValue({ data: { orderReturn: { id: 'return-1' } } });

        const draft = { type: ORDER_RETURN_TYPES.RETURN_REFUND, positions: [{ lineItemId: 'line-1', quantity: 1 }] };

        await expect(service.create('order-1', draft)).resolves.toEqual({ id: 'return-1' });
        expect(httpClient.post).toHaveBeenCalledWith('/_action/order-return/order/order-1', draft, {
            headers: service.getAuthHeaders(),
        });
    });

    it('names the snippet keys of a state and of a type', () => {
        expect(returnStateLabelKey('requested')).toBe('sw-order-return.stateLabel.requested');
        expect(returnStateLabelKey(null)).toBe('sw-order-return.stateLabel.null');
        expect(returnTypeLabelKey('refund_only')).toBe('sw-order-return.typeLabel.refund_only');
    });

    it('maps every state onto a variant the badge knows', () => {
        expect(returnStateVariant('requested')).toBe('attention');
        expect(returnStateVariant('open')).toBe('info');
        expect(returnStateVariant('returning')).toBe('info');
        expect(returnStateVariant('received')).toBe('attention');
        expect(returnStateVariant('refunding')).toBe('attention');
        expect(returnStateVariant('closed')).toBe('positive');
        expect(returnStateVariant('declined')).toBe('critical');
        expect(returnStateVariant('canceled')).toBe('neutral');
        expect(returnStateVariant('something-new')).toBe('neutral');
        expect(returnStateVariant(null)).toBe('neutral');
    });

    it('renders an amount out of the smallest unit of the currency', () => {
        const currencySpy = jest.spyOn(Shopwell.Utils.format, 'currency').mockImplementation((value: number) => `${value}`);

        expect(formatReturnAmount(null, 'CNY')).toBe('–');
        expect(formatReturnAmount(19900, 'CNY')).toBe('199');
        expect(currencySpy).toHaveBeenCalledWith(199, 'CNY', 2);

        currencySpy.mockRestore();
    });
});
