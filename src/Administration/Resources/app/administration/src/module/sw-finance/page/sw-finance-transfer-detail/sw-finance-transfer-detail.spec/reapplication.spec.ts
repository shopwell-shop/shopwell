/** @sw-package framework */
import { transfer, createWrapper, cleanupWrapper } from './create-wrapper';

describe('transfer reapplication', () => {
    afterEach(() => {
        cleanupWrapper();
    });

    it('shows the refusal and original reviewer, then opens the shared editable form', async () => {
        const { wrapper, routerPush } = await createWrapper(
            transfer,
            () => true,
            {},
            {
                status: 'rejected',
                canReapply: true,
                instanceId: 'instance-1',
                detailRoute: { name: 'sw.workflow.instance', params: { id: 'instance-1' } },
            },
        );
        expect(wrapper.text()).toContain('Invoice missing');
        expect(wrapper.text()).toContain('Finance reviewer');
        await wrapper.get('.sw-finance-transfer-detail__button-reapply').trigger('click');
        const modal = wrapper.findComponent({ name: 'sw-finance-transfer-create-modal' });
        expect(modal.attributes('source-transfer-id')).toBe('transfer-1');
        await wrapper.get('.reapply-created').trigger('click');
        await flushPromises();
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.finance.transferDetail',
            params: { id: 'new-transfer' },
        });
    });

    it('does not offer reapplication without server eligibility or creator privilege', async () => {
        const { wrapper } = await createWrapper(transfer, () => false, {}, { status: 'rejected', canReapply: true });
        expect(wrapper.find('.sw-finance-transfer-detail__button-reapply').exists()).toBe(false);
    });
});
