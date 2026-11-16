/** @sw-package framework */
import { computed } from 'vue';
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
        expect(service.getFields(config, 'Reason')).toEqual(config.formFields);
    });
});

describe('request form registration lifecycle', () => {
    it('registerDataSource rejects collisions and supports cleanup, reinstall and stale disposers', () => {
        const service = new WorkflowRequestFormService();
        const contribution = { name: 'example:source', labelKey: 'example.source' };
        const dispose = service.registerDataSource(contribution);
        expect(() => service.registerDataSource({ ...contribution })).toThrow(/Duplicate/);
        expect(service.getDataSources()).toEqual([contribution]);
        dispose();
        expect(service.getDataSources()).toEqual([]);
        const disposeAgain = service.registerDataSource(contribution);
        dispose();
        expect(service.getDataSources()).toEqual([contribution]);
        disposeAgain();
        expect(service.getDataSources()).toEqual([]);
    });

    it('initializes the implicit reason in the requested language without rewriting explicit authored fields', () => {
        const service = new WorkflowRequestFormService();
        expect(service.getFields({}, 'Reason')).toEqual([{ key: 'reason', label: 'Reason', type: 'text', required: false }]);
        expect(service.getFields({}, '申请事由')[0].label).toBe('申请事由');
        const config = { formFields: [{ key: 'reason', label: 'Edited reason', type: 'text', required: false }] };
        expect(service.getFields(config, '申请事由')[0].label).toBe('Edited reason');
    });

    it('updates capabilities already consumed by an open view when a registration is removed', () => {
        const service = new WorkflowRequestFormService();
        const selected = computed(() => service.getDataSources().map((source) => source.name));
        expect(selected.value).toEqual([]);
        const dispose = service.registerDataSource({ name: 'example:dynamic', labelKey: 'example.dynamic' });
        expect(selected.value).toEqual(['example:dynamic']);
        dispose();
        expect(selected.value).toEqual([]);
    });
});
