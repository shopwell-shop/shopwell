/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { TransferApproval } from '../../../service/payment-transfer.api.service';

/**
 * The states as the state machine migration creates them, names included: `Migration1792700010` names a
 * `created` payout 已创建 and a `succeeded` one 打款成功. Those words were written for the payment gateway.
 */
const transferStates = [
    {
        id: 'state-awaiting',
        name: 'awaiting_user_confirm',
        technicalName: 'awaiting_user_confirm',
        translated: { name: '待用户确认' },
    },
    { id: 'state-succeeded', name: 'succeeded', technicalName: 'succeeded', translated: { name: '打款成功' } },
    { id: 'state-created', name: 'created', technicalName: 'created', translated: { name: '已创建' } },
];

/**
 * The words of the finance pages for the states above: the test environment answers every key with the key
 * itself, so the label is only observable with a translation that actually translates.
 */
/** @private */
export const STATE_LABELS: Record<string, string> = {
    'sw-finance.transfer.stateLabel.awaiting_user_confirm': '待用户确认',
    'sw-finance.transfer.stateLabel.succeeded': '成功',
    'sw-finance.transfer.stateLabel.created': '待提交',
};

/** @private */
export const transfer = {
    id: 'transfer-1',
    transferNo: 'T-1',
    outNo: 'O-1',
    provider: 'wechat',
    channel: 'wechat_transfer',
    amount: 1250,
    currency: 'CNY',
    statusId: 'state-awaiting',
    submitAttempts: 2,
    providerRef: 'transfer_bill_no-1',
    providerStageRef: null,
    confirmPayload: { package_info: 'SECRET-PACKAGE-INFO' },
    failureCode: 'NOT_ENOUGH',
    failureReason: '余额不足',
    metadata: { note: '九月供应商结算', rejectReason: null },
    createdAt: '2026-01-02T03:04:05.000Z',
    submittedAt: '2026-01-02T04:05:06.000Z',
    settledAt: null,
    returnedAt: null,
    canceledAt: null,
    expireAt: '2026-01-03T04:05:06.000Z',
    paymentApp: { id: 'app-1', name: '演示应用', technicalName: 'demo' },
    recipient: {
        holderName: '张三',
        identityType: 'wechat_open_id',
        providerAccountId: 'openid-1',
        onboardingStatus: 'registered',
        payoutEnabled: true,
        verifiedAt: '2026-01-01T00:00:00.000Z',
    },
    statusMachineState: { id: 'state-awaiting', technicalName: 'awaiting_user_confirm' },
};

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

/** @private */
export async function createWrapper(
    entity: Record<string, unknown> = transfer,
    can: (privilege: string) => boolean = () => true,
    stateLabels: Record<string, string> = {},
    approval: Partial<TransferApproval> | Error = { status: 'approved', canCancel: true },
): Promise<{
    wrapper: VueWrapper;
    get: jest.Mock;
    stateSearch: jest.Mock;
    getApproval: jest.Mock;
    routerPush: jest.Mock;
}> {
    const routerPush = jest.fn();
    const getApproval = jest.fn(() => (approval instanceof Error ? Promise.reject(approval) : Promise.resolve(approval)));
    const get = jest.fn(() => Promise.resolve(entity));

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)));

    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-detail', { sync: true }), {
        props: {
            transferId: 'transfer-1',
        },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'mt-card': { template: '<div class="mt-card"><slot></slot></div>' },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot></slot></button>' },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="transfer-state" :data-variant="variant"><slot></slot></span>',
                },
                'mt-icon': true,
                'mt-link': { props: ['to'], template: '<a :data-route="to.name"><slot></slot></a>' },
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-empty-state': true,
                'sw-finance-transfer-create-modal': {
                    name: 'sw-finance-transfer-create-modal',
                    template: `<section><button class="reapply-created" @click="$emit('transfer-created', { id: 'new-transfer' })">Created</button></section>`,
                },
                'sw-finance-transfer-approve-modal': {
                    template:
                        '<section class="approve-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
                'sw-finance-transfer-reject-modal': {
                    template:
                        '<section class="reject-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
            },
            provide: {
                paymentTransferApiService: { getApproval },
                repositoryFactory: {
                    create: (entityName: string) => {
                        return entityName === 'state_machine_state' ? { search: stateSearch } : { get };
                    },
                },
                acl: {
                    can,
                },
            },
            mocks: {
                $router: {
                    push: routerPush,
                    replace: jest.fn(),
                },
                // only a test that asserts the rendered words gets a dictionary - the rest of the spec
                // relies on the environment answering every key with the key itself
                ...(Object.keys(stateLabels).length === 0
                    ? {}
                    : {
                          $te: (key: string) => Object.prototype.hasOwnProperty.call(stateLabels, key),
                          $t: (key: string) => stateLabels[key] ?? key,
                      }),
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, get, stateSearch, getApproval, routerPush };
}

/** @private */
export function cleanupWrapper(): void {
    activeWrapper?.unmount();
    activeWrapper = null;
}
