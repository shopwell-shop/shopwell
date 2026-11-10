/**
 * @sw-package framework
 */
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import { createWrapper, saveSpy } from './create-wrapper';
describe('new organization unit persistence', () => {
    it('serializes an unsaved root unit with its institution and without a parent', async () => {
        const wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await wrapper.findAll('.tree-add-root')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('QA研发中心');
        saveSpy.mockRejectedValueOnce(new Error('Keep draft'));
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();
        const calls = saveSpy.mock.calls as [EntitySchema.Entity<'organization_unit'>][];
        const changes = new ChangesetGenerator().generate(calls[0][0]);
        wrapper.unmount();
        expect(changes).toEqual({
            changes: { id: calls[0][0].id, organizationId: 'org-id', name: 'QA研发中心', active: true },
            deletionQueue: [],
        });
    });
});
