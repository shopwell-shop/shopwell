/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const payload = {
    paymentAppId: 'app',
    accountId: 'account',
    provider: 'alipay',
    channel: 'alipay_h5',
    recipientId: 'recipient',
    amount: 500001,
    currency: 'CNY',
};
const preview = {
    available: true,
    reason: null,
    releaseId: 'release',
    releaseName: 'Finance',
    revision: 2,
    steps: [
        {
            nodeId: 'finance',
            type: 'approval',
            name: 'Finance review',
            rule: 'single',
            assignees: [{ id: 'reviewer', name: 'Alice' }],
            branch: null,
        },
        { nodeId: 'policy', type: 'condition', name: 'Amount policy', rule: null, assignees: [], branch: 'Large transfer' },
    ],
};
let wrapper: VueWrapper;

async function createWrapper(
    previewApproval = jest.fn().mockResolvedValue(preview),
    initialPayload: typeof payload | null = payload,
) {
    wrapper = mount(await wrapTestComponent('sw-finance-transfer-approval-preview', { sync: true }), {
        props: { payload: initialPayload },
        global: {
            mocks: {
                $te: () => true,
                $t: (key: string, values?: Record<string, unknown>) =>
                    values ? `${key} ${Object.values(values).join(' ')}` : key,
            },
            provide: { paymentTransferApiService: { previewApproval } },
            stubs: {
                'mt-banner': { template: '<div><slot/></div>' },
                'mt-button': { template: '<button><slot/></button>' },
            },
        },
    });
    return previewApproval;
}

async function finishDebounce() {
    jest.advanceTimersByTime(300);
    await flushPromises();
}

describe('transfer approval preview', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
    });

    it('waits for complete input and renders only the path returned by the server', async () => {
        const request = await createWrapper(jest.fn().mockResolvedValue(preview), null);
        await finishDebounce();
        expect(request).not.toHaveBeenCalled();
        await wrapper.setProps({ payload });
        await finishDebounce();
        expect(request).toHaveBeenCalledWith(payload);
        expect(wrapper.findAll('li').map((item) => item.attributes('data-node-id'))).toEqual(['finance', 'policy']);
        expect(wrapper.text()).toContain('Alice');
        expect(wrapper.text()).toContain('Large transfer');
        expect(wrapper.text()).toContain('Finance');
    });

    it('clears stale paths immediately and ignores late responses for earlier amounts', async () => {
        let resolveFirst!: (value: typeof preview) => void;
        const request = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValueOnce({ ...preview, steps: [] });
        await createWrapper(request);
        await finishDebounce();
        await wrapper.setProps({ payload: { ...payload, amount: 1000 } });
        expect(wrapper.findAll('li')).toHaveLength(0);
        await finishDebounce();
        resolveFirst(preview);
        await flushPromises();
        expect(wrapper.findAll('li')).toHaveLength(0);
        expect(request).toHaveBeenCalledTimes(2);
    });

    it('shows a live reviewer failure without presenting an executable path', async () => {
        await createWrapper(
            jest.fn().mockResolvedValue({ ...preview, available: false, reason: 'approval_assignee_forbidden', steps: [] }),
        );
        await finishDebounce();
        expect(wrapper.text()).toContain('sw-finance.transfer.workflow.checks.approval_assignee_forbidden');
        expect(wrapper.findAll('li')).toHaveLength(0);
        expect(wrapper.emitted('update:blocked')?.at(-1)).toEqual([true]);
    });

    it('allows retry after a network failure', async () => {
        const request = await createWrapper(
            jest.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValueOnce(preview),
        );
        await finishDebounce();
        expect(wrapper.text()).toContain('sw-finance.transfer.approvalPreview.loadFailed');
        await wrapper.get('.sw-finance-transfer-approval-preview__retry').trigger('click');
        await finishDebounce();
        expect(request).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Alice');
    });

    it('keeps submission available when an extension runtime does not provide previews', async () => {
        await createWrapper(
            jest.fn().mockResolvedValue({ ...preview, available: false, reason: 'preview_unavailable', steps: [] }),
        );
        await finishDebounce();
        expect(wrapper.text()).toContain('sw-finance.transfer.workflow.checks.preview_unavailable');
        expect(wrapper.emitted('update:blocked')?.at(-1)).toEqual([false]);
    });

    it('cancels a scheduled request when the form closes', async () => {
        const request = await createWrapper();
        wrapper.unmount();
        await finishDebounce();
        expect(request).not.toHaveBeenCalled();
    });
});
