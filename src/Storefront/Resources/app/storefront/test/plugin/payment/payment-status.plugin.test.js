import PaymentStatusPlugin from 'src/plugin/payment/payment-status.plugin';

const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
};

describe('checkout payment confirmation', () => {
    let plugin;

    beforeEach(() => {
        jest.useFakeTimers();
        document.body.innerHTML = `
            <div data-payment-status>
                <div data-payment-status-qr>QR code</div>
                <p data-payment-status-message role="status">Waiting</p>
                <button data-payment-status-check>Check payment</button>
                <a data-payment-status-return href="/checkout/finish">Return</a>
            </div>`;
        window.PluginManager = {
            getPluginInstancesFromElement: jest.fn().mockReturnValue(new Map()),
            getPlugin: jest.fn().mockReturnValue({ get: jest.fn().mockReturnValue([]) }),
        };
        global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ state: 'pending' }) });
    });

    afterEach(() => {
        plugin?.destroy();
        jest.useRealTimers();
        delete global.fetch;
        document.body.innerHTML = '';
    });

    function open() {
        plugin = new PaymentStatusPlugin(document.querySelector('[data-payment-status]'), {
            statusUrl: '/payment/gateway/id/number/status',
            pendingMessage: 'Waiting',
            paidMessage: 'Paid',
            closedMessage: 'Closed',
            failedMessage: 'Failed',
            pollInterval: 100,
        }, 'PaymentStatus');
    }

    test('waits for persisted settlement before following the checkout return link', async () => {
        const follow = jest.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        open();
        await flush();
        expect(follow).not.toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledWith('/payment/gateway/id/number/status', { cache: 'no-store' });

        global.fetch.mockResolvedValue({ ok: true, json: async () => ({ state: 'paid' }) });
        await jest.advanceTimersByTimeAsync(100);

        expect(document.querySelector('[data-payment-status-message]').textContent).toBe('Paid');
        expect(document.querySelector('[data-payment-status-qr]').classList.contains('d-none')).toBe(true);
        expect(follow).toHaveBeenCalledTimes(1);
        await jest.advanceTimersByTimeAsync(1000);
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });

    test.each(['closed', 'failed'])('stops polling and removes an unusable code after %s', async (state) => {
        global.fetch.mockResolvedValue({ ok: true, json: async () => ({ state }) });
        open();
        await flush();
        expect(document.querySelector('[data-payment-status-message]').textContent).toBe(state === 'closed' ? 'Closed' : 'Failed');
        expect(document.querySelector('[data-payment-status-qr]').classList.contains('d-none')).toBe(true);
        await jest.advanceTimersByTimeAsync(1000);
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('recovers from a transient status failure without opening a new trade', async () => {
        global.fetch.mockRejectedValueOnce(new Error('offline'));
        open();
        await flush();
        await jest.advanceTimersByTimeAsync(100);
        expect(global.fetch).toHaveBeenCalledTimes(2);
        expect(document.querySelector('[data-payment-status-qr]').classList.contains('d-none')).toBe(false);
    });

    test('resumes confirmation when the browser restores a cached page', async () => {
        open();
        await flush();
        window.dispatchEvent(new Event('pagehide'));
        window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
        await flush();
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });

    test('allows a manual confirmation and cleans up when leaving the page', async () => {
        open();
        await flush();
        document.querySelector('[data-payment-status-check]').click();
        await flush();
        expect(global.fetch).toHaveBeenCalledTimes(2);
        window.dispatchEvent(new Event('pagehide'));
        await jest.advanceTimersByTimeAsync(1000);
        expect(global.fetch).toHaveBeenCalledTimes(2);
    });
});
