/**
 * @sw-package framework
 */
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { LoginService } from 'src/core/service/login.service';
import PaymentTransferApiService, {
    OUTCOME,
    fromMinorUnits,
    providerLabelKey,
    toMinorUnits,
} from './payment-transfer.api.service';
import type { PaymentTransferPayload } from './payment-transfer.api.service';

/**
 * The config of a request, reduced to the one field these tests look at.
 */
type HeaderConfig = {
    headers?: Record<string, string>;
};

const payload: PaymentTransferPayload = {
    paymentAppId: 'app-1',
    provider: 'alipay',
    channel: 'alipay_transfer',
    recipientId: 'recipient-1',
    identityType: null,
    identityValue: null,
    holderName: null,
    bankInfo: null,
    amount: 1205,
    currency: 'CNY',
    outNo: null,
    note: null,
};

/**
 * The conversion between the yuan an operator types and the fen the server stores is the only place of the
 * administration where an amount is transformed. Everything below is one way to get it wrong.
 */
describe('module/sw-finance/service/payment-transfer.api.service - CNY minor units', () => {
    it.each([
        ['0.1', 10],
        ['0.01', 1],
        ['0.10', 10],
        ['7', 700],
        ['7.5', 750],
        ['12.05', 1205],
        ['100', 10000],
        ['1000.00', 100000],
        [' 12.05 ', 1205],
        [12.05, 1205],
        [7, 700],
    ])('should convert %p into %p fen', (input, expected) => {
        expect(toMinorUnits(input, 'CNY')).toBe(expected);
    });

    /**
     * A third decimal is a typo, not a rounding problem: rounding it away would book an amount the operator
     * did not type.
     */
    it.each([['1.005'], ['0.001'], ['12.345']])('should refuse the number of decimals of %p', (input) => {
        expect(toMinorUnits(input, 'CNY')).toBeNull();
    });

    it.each([
        [''],
        ['   '],
        [null],
        [undefined],
        ['-1'],
        ['-0.01'],
        ['abc'],
        ['1.'],
        ['.5'],
        ['1,5'],
        ['1e3'],
    ])('should refuse %p', (input) => {
        expect(toMinorUnits(input, 'CNY')).toBeNull();
    });

    /**
     * Zero is not an amount. The function answers the one question the form has - "is this payable?" - so
     * that no caller has to repeat the comparison and drift from it.
     */
    it.each([
        ['0'],
        ['0.00'],
        ['0.0'],
        [0],
        ['00'],
    ])('should refuse the empty amount %p', (input) => {
        expect(toMinorUnits(input, 'CNY')).toBeNull();
    });

    it('should convert back for the prefill of a form', () => {
        expect(fromMinorUnits(10, 'CNY')).toBe('0.10');
        expect(fromMinorUnits(1205, 'CNY')).toBe('12.05');
        expect(fromMinorUnits(100000, 'CNY')).toBe('1000.00');
        expect(fromMinorUnits(null, 'CNY')).toBe('');
    });
});

describe('module/sw-finance/service/payment-transfer.api.service - toMinorUnits', () => {
    /**
     * The exponent belongs to the currency: a payout in JPY has no minor unit, so "yuan times a hundred"
     * would book a payout a hundred times as large as the operator typed.
     */
    it.each([
        ['12', 'JPY', 12],
        ['1234', 'JPY', 1234],
        ['7', 'KRW', 7],
        ['12.05', 'CNY', 1205],
        ['1.234', 'KWD', 1234],
        ['1.2', 'KWD', 1200],
    ])('should convert %p %p into %p minor units', (input, currency, expected) => {
        expect(toMinorUnits(input, currency)).toBe(expected);
    });

    it.each([
        ['12.05', 'JPY'],
        ['12.0', 'JPY'],
        ['1.2345', 'KWD'],
        ['1.005', 'CNY'],
    ])('should refuse %p for %p, whose exponent does not allow it', (input, currency) => {
        expect(toMinorUnits(input, currency)).toBeNull();
    });

    it('should convert back with the exponent of the currency', () => {
        expect(fromMinorUnits(1234, 'JPY')).toBe('1234');
        expect(fromMinorUnits(1234, 'KWD')).toBe('1.234');
        expect(fromMinorUnits(1205, 'CNY')).toBe('12.05');
        expect(fromMinorUnits(null, 'JPY')).toBe('');
    });

    it('should stay the CNY conversion when no currency is given', () => {
        expect(toMinorUnits('12.05')).toBe(1205);
        expect(fromMinorUnits(1205)).toBe('12.05');
    });
});

describe('module/sw-finance/service/payment-transfer.api.service', () => {
    function createService(): {
        service: PaymentTransferApiService;
        get: jest.Mock<Promise<unknown>, [url: string, config?: HeaderConfig]>;
        post: jest.Mock<Promise<unknown>, [url: string, data?: unknown, config?: HeaderConfig]>;
        loginService: { getToken: jest.Mock<string, []> };
    } {
        const get = jest.fn<Promise<unknown>, [url: string, config?: HeaderConfig]>(() =>
            Promise.resolve({ data: { apps: [], defaultAppId: null } }),
        );
        const post = jest.fn<Promise<unknown>, [url: string, data?: unknown, config?: HeaderConfig]>(() =>
            Promise.resolve({ data: { outcome: OUTCOME.ACCEPTED, transfer: null } }),
        );
        const loginService = { getToken: jest.fn<string, []>(() => 'token-1') };

        const service = new PaymentTransferApiService(
            { get, post } as unknown as HttpClient,
            loginService as unknown as LoginService,
        );

        return { service, get, post, loginService };
    }

    it('should name itself so it can be injected', () => {
        const { service } = createService();

        expect(service.name).toBe('paymentTransferApiService');
    });

    it('should send the bearer token with every call', async () => {
        const { service, get, post, loginService } = createService();

        await service.getOptions();
        await service.getRecipients('app-1', 'alipay');
        await service.createTransfer(payload);
        await service.submitTransfer('transfer-1');
        await service.cancelTransfer('transfer-1', 'wrong account');

        expect(loginService.getToken).toHaveBeenCalledTimes(5);

        get.mock.calls.forEach((call) => {
            expect(call[1]?.headers?.Authorization).toBe('Bearer token-1');
        });

        post.mock.calls.forEach((call) => {
            expect(call[2]?.headers?.Authorization).toBe('Bearer token-1');
        });
    });

    it('should read the options of the create modal', async () => {
        const { service, get } = createService();

        await service.getOptions();

        expect(get).toHaveBeenCalledWith('/_action/payment/transfers/options', expect.anything());
    });

    it('should ask for the recipients of one app and one interface', async () => {
        const { service, get } = createService();

        await service.getRecipients('app-1', 'alipay');

        expect(get).toHaveBeenCalledWith('/_action/payment/transfers/recipients', {
            headers: expect.anything() as unknown,
            params: { paymentAppId: 'app-1', provider: 'alipay' },
        });
    });

    it('should book a transfer request without touching a channel', async () => {
        const { service, post } = createService();

        await service.createTransfer(payload);

        expect(post).toHaveBeenCalledWith('/_action/payment/transfers', payload, expect.anything());
    });

    /**
     * The one protocol decision that makes "pay out of another merchant account" unwritable: the submission
     * carries no body, so it can not name an app.
     */
    it('should submit a transfer without a payload', async () => {
        const { service, post } = createService();

        await service.submitTransfer('transfer-1');

        expect(post).toHaveBeenCalledWith('/_action/payment/transfers/transfer-1/submit', null, expect.anything());
    });

    it('should always send the reason of a rejection', async () => {
        const { service, post } = createService();

        await service.cancelTransfer('transfer-1', 'wrong account');

        expect(post).toHaveBeenCalledWith(
            '/_action/payment/transfers/transfer-1/cancel',
            { reason: 'wrong account' },
            expect.anything(),
        );
    });

    it('should report the outcome of a submission as the server named it', async () => {
        const { service } = createService();

        const result = await service.submitTransfer('transfer-1');

        expect(result.outcome).toBe(OUTCOME.ACCEPTED);
    });

    it('should keep the vocabulary of the outcomes in one place', () => {
        expect(OUTCOME).toEqual({
            ACCEPTED: 'accepted',
            FAILED: 'failed',
            UNKNOWN: 'unknown',
            CANCELED: 'canceled',
        });
    });

    it('should build the snippet key of an interface name', () => {
        expect(providerLabelKey('alipay')).toBe('sw-settings-payment-provider.provider.alipay');
    });
});
