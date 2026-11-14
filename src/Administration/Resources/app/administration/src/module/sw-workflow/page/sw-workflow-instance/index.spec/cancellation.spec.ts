/** @sw-package framework */
import { repository, createWrapper, instance } from './create-wrapper';

describe('workflow initiator cancellation', () => {
    it('requires a reason and preserves the dialog after a failed cancellation', async () => {
        const wrapper = await createWrapper({ ...instance, canCancel: true });
        await wrapper
            .findAll('button')
            .filter((button) => button.text().endsWith('.cancel'))[0]
            .trigger('click');
        const confirm = wrapper.get('.sw-workflow-instance__cancel-confirm');
        expect(confirm.attributes('disabled')).toBeDefined();
        await wrapper.get('textarea').setValue(' Duplicate ');
        jest.mocked(repository.cancelInstance).mockRejectedValueOnce(new Error('Unavailable'));
        await confirm.trigger('click');
        await flushPromises();
        expect(repository.cancelInstance).toHaveBeenCalledWith('instance', 'Duplicate');
        expect(wrapper.find('.cancel-modal').exists()).toBe(true);
        jest.mocked(repository.cancelInstance).mockResolvedValueOnce(undefined);
        jest.mocked(repository.getInstance).mockResolvedValueOnce({ ...instance, status: 'CANCEL', canCancel: false });
        await confirm.trigger('click');
        await flushPromises();
        expect(wrapper.find('.cancel-modal').exists()).toBe(false);
        expect(wrapper.text()).toContain('sw-workflow.runtime.status.canceled');
        wrapper.unmount();
    });
});
