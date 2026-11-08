/**
 * @sw-package framework
 */

const RepositoryFactory = Shopwell.Classes._private.RepositoryFactory;
const { EntityHydrator, ChangesetGenerator, EntityFactory } = Shopwell.Data;
const ErrorResolverError = Shopwell.Data.ErrorResolver;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeRepositoryFactory(container: InitContainer) {
    const httpClient = container.httpClient;
    const factoryContainer = Shopwell.Application.getContainer('factory');
    const serviceContainer = Shopwell.Application.getContainer('service');

    return httpClient
        .get('_info/entity-schema.json', {
            headers: {
                Authorization: `Bearer ${serviceContainer.loginService.getToken()}`,
            },
        })
        .then(({ data }) => {
            const entityDefinitionFactory = factoryContainer.entityDefinition;
            const customEntityDefinitionService = serviceContainer.customEntityDefinitionService;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            Object.entries(data).forEach(([key, value]) => {
                entityDefinitionFactory.add(key, value);

                if (key.startsWith('custom_entity_') || key.startsWith('ce_')) {
                    // @ts-expect-error - value is defined
                    customEntityDefinitionService.addDefinition(value);
                }
            });

            const hydrator = new EntityHydrator();
            const changesetGenerator = new ChangesetGenerator();
            const entityFactory = new EntityFactory();
            const errorResolver = new ErrorResolverError();

            Shopwell.Application.addServiceProvider('repositoryFactory', () => {
                return new RepositoryFactory(hydrator, changesetGenerator, entityFactory, httpClient, errorResolver);
            });
            Shopwell.Application.addServiceProvider('entityHydrator', () => {
                return hydrator;
            });
            Shopwell.Application.addServiceProvider('entityFactory', () => {
                return entityFactory;
            });
        });
}
