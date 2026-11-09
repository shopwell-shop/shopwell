import CustomEntityDefinitionService from 'src/app/service/custom-entity-definition.service';

const bareDefinitionName = 'custom_entity_bare';
const customEntityDefinitionBare = {
    entity: bareDefinitionName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {},
};

const adminUiExampleConfig = {
    icon: 'some-icon',
    navigationParent: 'content',
    position: '50',
    listing: {
        columns: [
            {
                ref: 'title',
            },
            {
                ref: 'description',
            },
            {
                ref: 'position',
                hidden: true,
            },
        ],
    },
    detail: {
        tabs: [
            {
                name: 'main',
                cards: [
                    {
                        name: 'general',
                        fields: [
                            {
                                ref: 'title',
                            },
                            {
                                ref: 'description',
                                helpText: true,
                                placeholder: true,
                            },
                            {
                                ref: 'position',
                            },
                        ],
                    },
                ],
            },
        ],
    },
};

const withAdminUiName = 'custom_entity_with_admin_ui';
const customEntityDefinitionWithAdminUi = {
    entity: withAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'admin-ui': adminUiExampleConfig,
    },
};

const withoutAdminUiName = 'custom_entity_without_admin_ui';
const customEntityDefinitionWithoutAdminUi = {
    entity: withoutAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {},
};

const withAllFlagsName = 'custom_entity_with_all_flags';
const customEntityDefinitionWithAllFlags = {
    entity: withAllFlagsName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'admin-ui': adminUiExampleConfig,
    },
};

function createCustomEntityDefinitionService() {
    const customEntityDefinitionService = new CustomEntityDefinitionService();

    customEntityDefinitionService.addDefinition(customEntityDefinitionBare);
    customEntityDefinitionService.addDefinition(customEntityDefinitionWithAdminUi);
    customEntityDefinitionService.addDefinition(customEntityDefinitionWithoutAdminUi);
    customEntityDefinitionService.addDefinition(customEntityDefinitionWithAllFlags);

    return customEntityDefinitionService;
}

let service;

/**
 * @sw-package framework
 */
describe('src/app/service/custom-entity-definition.service', () => {
    beforeEach(() => {
        service = createCustomEntityDefinitionService();
    });

    it('should get a definition by name', () => {
        expect(service.getDefinitionByName(bareDefinitionName)).toStrictEqual(customEntityDefinitionBare);
    });

    it('should get all definitions', () => {
        expect(service.getAllDefinitions()).toStrictEqual([
            customEntityDefinitionBare,
            customEntityDefinitionWithAdminUi,
            customEntityDefinitionWithoutAdminUi,
            customEntityDefinitionWithAllFlags,
        ]);
    });

    it('should determine whether an entity with an admin-ui definition exists', () => {
        expect(service.hasDefinitionWithAdminUi(bareDefinitionName)).toBe(false);
        expect(service.hasDefinitionWithAdminUi(withAdminUiName)).toBe(true);
        expect(service.hasDefinitionWithAdminUi(withoutAdminUiName)).toBe(false);
        expect(service.hasDefinitionWithAdminUi(withAllFlagsName)).toBe(true);
    });

    it('should produce menu entry definitions for each entity with admin-ui', () => {
        const menuEntry = {
            icon: 'some-icon',
            moduleType: 'plugin',
            parent: 'content',
            path: 'sw.custom.entity.index',
            position: '50',
        };

        expect(service.getMenuEntries()).toStrictEqual([
            {
                ...menuEntry,
                id: `custom-entity/${withAdminUiName}`,
                label: `${withAdminUiName}.moduleTitle`,
                params: {
                    entityName: withAdminUiName,
                },
            },
            {
                ...menuEntry,
                id: `custom-entity/${withAllFlagsName}`,
                label: `${withAllFlagsName}.moduleTitle`,
                params: {
                    entityName: withAllFlagsName,
                },
            },
        ]);
    });
});
