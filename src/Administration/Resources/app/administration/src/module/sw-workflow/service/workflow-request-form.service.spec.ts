/** @sw-package framework */
import WorkflowRequestFormService from './workflow-request-form.service';

describe('request form option source registry', () => {
    it('lets business modules contribute source choices without changing form settings', () => {
        const service = new WorkflowRequestFormService();
        service.registerDataSource({ name: 'inventory:warehouse', labelKey: 'inventory.warehouse' });
        expect(service.getDataSources()).toEqual([{ name: 'inventory:warehouse', labelKey: 'inventory.warehouse' }]);
        const config = {
            formFields: [
                { key: 'target', label: 'Warehouse', type: 'text', required: true, dataSource: 'inventory:warehouse' },
            ],
        };
        expect(service.getFields(config)).toEqual(config.formFields);
    });
});
