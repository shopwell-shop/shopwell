/**
 * @sw-package framework
 */
import PaymentGatewayService, {
    PAYMENT_ORDER_STATE_MACHINE,
    PAYMENT_REFUND_STATE_MACHINE,
    createPaymentStateCriteria,
    findPaymentState,
    findPaymentStateLabel,
    findPaymentStateVariant,
    formatPaymentAmount,
    formatPayload,
    getCurrencyFractionDigits,
    statusVariant,
} from './payment-gateway.api.service';

const paidState = {
    id: 'state-paid',
    name: 'paid',
    technicalName: 'paid',
    translated: { name: '已支付' },
};

const failedState = {
    id: 'state-failed',
    name: 'failed',
    technicalName: 'failed',
    translated: {},
};

describe('module/sw-payment/service/payment-gateway.api.service', () => {
    describe('state machines', () => {
        it('should keep the machines of the gateway apart from the order transaction state', () => {
            expect(PAYMENT_ORDER_STATE_MACHINE).toBe('payment_order.state');
            expect(PAYMENT_REFUND_STATE_MACHINE).toBe('payment_refund.state');
        });

        it('should build a criteria that reads the states of one machine', () => {
            const criteria = createPaymentStateCriteria(PAYMENT_ORDER_STATE_MACHINE);

            expect(criteria.filters).toHaveLength(1);
            expect(criteria.filters[0].field).toBe('stateMachine.technicalName');
            expect(criteria.filters[0].value).toBe(PAYMENT_ORDER_STATE_MACHINE);
            expect(criteria.sortings).toHaveLength(1);
            expect(criteria.sortings[0].field).toBe('technicalName');
            expect(criteria.sortings[0].order).toBe('ASC');
        });
    });

    describe('state helpers', () => {
        it('should find a state by its id', () => {
            expect(findPaymentState([paidState], 'state-paid')).toBe(paidState);
            expect(findPaymentState([paidState], 'state-unknown')).toBeNull();
        });

        it('should prefer the translated name of a state and fall back to the technical one', () => {
            expect(findPaymentStateLabel([paidState], 'state-paid')).toBe('已支付');
            expect(findPaymentStateLabel([failedState], 'state-failed')).toBe('failed');
            expect(findPaymentStateLabel([paidState], 'state-unknown')).toBe('');
        });

        it('should map the technical name of a state onto a badge variant', () => {
            expect(findPaymentStateVariant([paidState], 'state-paid')).toBe('positive');
            expect(findPaymentStateVariant([failedState], 'state-failed')).toBe('critical');
            // a state the administration does not know still renders a styled badge
            expect(findPaymentStateVariant([paidState], 'state-unknown')).toBe('neutral');
        });

        it('should map the status of a transaction onto a badge variant', () => {
            expect(statusVariant('success')).toBe('positive');
            expect(statusVariant('pending')).toBe('attention');
            expect(statusVariant('invalid_signature')).toBe('critical');
            expect(statusVariant('whatever')).toBe('neutral');
        });
    });

    describe('amounts', () => {
        it('should know the fraction digits of a currency', () => {
            expect(getCurrencyFractionDigits('CNY')).toBe(2);
            expect(getCurrencyFractionDigits('JPY')).toBe(0);
            expect(getCurrencyFractionDigits(null)).toBe(2);
            expect(getCurrencyFractionDigits('not-a-currency')).toBe(2);
        });

        it('should render an amount of the smallest unit', () => {
            expect(formatPaymentAmount(1234, 'CNY')).toBe(Shopwell.Utils.format.currency(12.34, 'CNY', 2));
            expect(formatPaymentAmount(1234, 'JPY')).toBe(Shopwell.Utils.format.currency(1234, 'JPY', 0));
        });

        it('should render nothing for an amount that is not set', () => {
            expect(formatPaymentAmount(null, 'CNY')).toBe('');
            expect(formatPaymentAmount(undefined, 'CNY')).toBe('');
        });
    });

    describe('payload formatting', () => {
        it('should pretty print a payload', () => {
            expect(formatPayload({ trade_no: '123' })).toBe(JSON.stringify({ trade_no: '123' }, null, 2));
        });

        it('should render nothing for a payload that is not set or empty', () => {
            expect(formatPayload(null)).toBe('');
            expect(formatPayload(undefined)).toBe('');
            expect(formatPayload({})).toBe('');
        });
    });

    describe('http client', () => {
        function createService(data = {}) {
            const httpClient = {
                get: jest.fn(() => Promise.resolve({ data })),
                post: jest.fn(() => Promise.resolve({ data })),
                delete: jest.fn(() => Promise.resolve({ data })),
            };
            const loginService = {
                getToken: jest.fn(() => 'the-token'),
            };

            return {
                httpClient,
                service: new PaymentGatewayService(httpClient, loginService),
            };
        }

        const headers = {
            Accept: 'application/json',
            Authorization: 'Bearer the-token',
            'Content-Type': 'application/json',
        };

        it('should read the payment interfaces the shop can be configured with', async () => {
            const { service, httpClient } = createService({ providers: [{ technicalName: 'alipay' }] });

            await expect(service.listProviders()).resolves.toEqual([{ technicalName: 'alipay' }]);
            expect(httpClient.get).toHaveBeenCalledWith('/_action/payment/providers', { headers });
        });

        it('should resolve with an empty list when the response carries no interfaces', async () => {
            const { service } = createService(undefined);

            await expect(service.listProviders()).resolves.toEqual([]);
        });

        it('should store the credentials of an interface', async () => {
            const { service, httpClient } = createService({ provider: { technicalName: 'alipay' } });

            await expect(service.saveProvider('alipay', { enabled: true })).resolves.toEqual({
                technicalName: 'alipay',
            });
            expect(httpClient.post).toHaveBeenCalledWith(
                '/_action/payment/providers/alipay',
                { enabled: true },
                { headers },
            );
        });

        it('should reset the credentials of an interface', async () => {
            const { service, httpClient } = createService({ provider: { technicalName: 'alipay' } });

            await expect(service.resetProvider('alipay')).resolves.toEqual({ technicalName: 'alipay' });
            expect(httpClient.delete).toHaveBeenCalledWith('/_action/payment/providers/alipay', { headers });
        });

        it('should store the parameters of one channel of an interface', async () => {
            const { service, httpClient } = createService({ channel: { channel: 'wechat_h5' } });

            await expect(service.saveProviderChannel('wechat', 'wechat_h5', { enabled: true })).resolves.toEqual({
                channel: 'wechat_h5',
            });
            expect(httpClient.post).toHaveBeenCalledWith(
                '/_action/payment/providers/wechat/channels/wechat_h5',
                { enabled: true },
                { headers },
            );
        });

        it('should reset the parameters of one channel of an interface', async () => {
            const { service, httpClient } = createService({ channel: { channel: 'wechat_h5' } });

            await expect(service.resetProviderChannel('wechat', 'wechat_h5')).resolves.toEqual({ channel: 'wechat_h5' });
            expect(httpClient.delete).toHaveBeenCalledWith('/_action/payment/providers/wechat/channels/wechat_h5', {
                headers,
            });
        });

        it('should read the apps of the gateway', async () => {
            const { service, httpClient } = createService({ apps: [{ id: 'app-1' }] });

            await expect(service.listApps()).resolves.toEqual([{ id: 'app-1' }]);
            expect(httpClient.get).toHaveBeenCalledWith('/_action/payment/apps', { headers });
        });

        it('should create or update an app', async () => {
            const { service, httpClient } = createService({ app: { id: 'app-1' }, appSecret: 'the-secret' });

            await expect(service.saveApp({ technicalName: 'demo' })).resolves.toEqual({
                app: { id: 'app-1' },
                appSecret: 'the-secret',
            });
            expect(httpClient.post).toHaveBeenCalledWith('/_action/payment/apps', { technicalName: 'demo' }, { headers });
        });

        it('should delete an app', async () => {
            const { service, httpClient } = createService(null);

            await service.deleteApp('app-1');

            expect(httpClient.delete).toHaveBeenCalledWith('/_action/payment/apps/app-1', { headers });
        });
    });
});
