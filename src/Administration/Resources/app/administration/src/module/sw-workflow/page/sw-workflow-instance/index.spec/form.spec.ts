/** @sw-package framework */
import { flushPromises, type VueWrapper } from '@vue/test-utils';
import { createWrapper, instance, repository } from './create-wrapper';

describe('approval form changes', () => {
    it('sends the editor patch and snapshot version with the current task decision', async () => {
        const wrapper = await createWrapper({
            ...instance,
            formVersion: 3,
            formPermissions: { reason: { access: 'editable', required: true } },
        });
        const editor = wrapper.findComponent({ name: 'sw-workflow-approval-form' }) as VueWrapper;
        editor.vm.$emit('update-patch', { reason: 'Revised evidence' });
        editor.vm.$emit('validity-change', true);
        await flushPromises();
        jest.mocked(repository.approveTask).mockResolvedValue(undefined);

        await wrapper.get('.approval-actions button').trigger('click');
        await flushPromises();

        expect(repository.approveTask).toHaveBeenCalledWith('task', '', {
            formData: { reason: 'Revised evidence' },
            formVersion: 3,
        });
        wrapper.unmount();
    });

    it('waits for valid editable fields before submitting an approval', async () => {
        const wrapper = await createWrapper({
            ...instance,
            formVersion: 0,
            formPermissions: { reason: { access: 'editable', required: true } },
        });

        await wrapper.get('.approval-actions button').trigger('click');
        await flushPromises();

        expect(repository.approveTask).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
