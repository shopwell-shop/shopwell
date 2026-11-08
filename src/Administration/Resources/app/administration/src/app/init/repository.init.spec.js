/**
 * @sw-package framework
 */
import initializeRepositoryFactory from './repository.init';

const coreEntityName = 'product';
const coreEntityConfig = {
    entity: coreEntityName,
};

const bareConfigName = 'whatever_bare';
const bareCustomEntityConfigName = 'custom_entity_bare';
const customEntityDefinitionBare = {
    entity: bareConfigName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {},
};

const withAdminUiName = 'custom_entity_with_admin_ui';
const customEntityDefinitionWithAdminUi = {
    entity: withAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'admin-ui': {},
    },
};

const shortHandWithAdminUiName = 'ce_with_admin_ui';
const shortHandCustomEntityDefinitionWithAdminUi = {
    entity: shortHandWithAdminUiName,
    properties: {},
    'write-protected': false,
    'read-protected': false,
    flags: {
        'admin-ui': {},
    },
};

const containerWithCustomEntities = {
    httpClient: {
        get() {
            return Promise.resolve({
                data: {
                    [bareCustomEntityConfigName]: customEntityDefinitionBare,
                    [coreEntityName]: coreEntityConfig,
                    [withAdminUiName]: customEntityDefinitionWithAdminUi,
                    [shortHandWithAdminUiName]: shortHandCustomEntityDefinitionWithAdminUi,
                },
            });
        },
    },
};

const factory = {
    entityDefinition: {
        add() {},
    },
};

let customEntityDefinitionStore = [];

const service = {
    customEntityDefinitionService: {
        addDefinition(config) {
            customEntityDefinitionStore.push(config);
        },
    },

    loginService: {
        getToken: () => '',
    },
};

const thisMock = {
    getContainer(containerName) {
        switch (containerName) {
            case 'factory':
                return factory;
            case 'service':
                return service;
            default:
                throw new Error(`Container for ${containerName} isn't mocked`);
        }
    },
    addServiceProvider() {},
};

describe('init/repository', () => {
    beforeEach(() => {
        customEntityDefinitionStore = [];

        Shopwell.Application = thisMock;
    });

    it('should register custom entities to the customEntityDefinitionService', async () => {
        await initializeRepositoryFactory.apply(thisMock, [containerWithCustomEntities]);

        expect(customEntityDefinitionStore).toStrictEqual([
            customEntityDefinitionBare,
            customEntityDefinitionWithAdminUi,
            shortHandCustomEntityDefinitionWithAdminUi,
        ]);
    });
});
