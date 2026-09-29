<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Util\Filesystem;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopwell\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopwell\Core\System\CustomField\CustomFieldCollection;
use Shopwell\Core\System\CustomField\CustomFieldEntity;
use Shopwell\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldLifecycleHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const MANIFEST = __DIR__ . '/../../Manifest/_fixtures/test/manifest.xml';

    private CustomFieldLifecycleHandler $handler;

    private AppFixture $appFixture;

    /**
     * @var EntityRepository<CustomFieldSetCollection>
     */
    private EntityRepository $customFieldSetRepository;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(CustomFieldLifecycleHandler::class);
        $this->customFieldSetRepository = static::getContainer()->get('custom_field_set.repository');

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
    }

    public function testPersistAddsCustomFields(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());

        $this->handler->install(new AppPersistContext($manifest, $app, Context::createDefaultContext(), $appFilesystem, 'en-GB'));

        $this->assertDefaultCustomFields($app->getId());
    }

    public function testPersistRecreatesDuplicateExistingCustomFieldSets(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());

        $this->createCustomFieldSet($app, Uuid::randomHex(), 'custom_field_test', ['bla_test']);
        $this->createCustomFieldSet($app, Uuid::randomHex(), 'custom_field_test', ['bla_test2']);

        $this->handler->update(new AppPersistContext($manifest, $app, Context::createDefaultContext(), $appFilesystem, 'en-GB'));

        $this->assertDefaultCustomFields($app->getId());
    }

    public function testPersistUpdatesExistingCustomFieldSet(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());
        $customFieldSetId = Uuid::randomHex();

        $this->createCustomFieldSet($app, $customFieldSetId, 'custom_field_test', ['bla_test', 'to_be_deleted'], ['product', 'to be deleted']);
        $this->createCustomFieldSet($app, Uuid::randomHex(), 'to_be_deleted', ['bla_test2']);

        $this->handler->update(new AppPersistContext($manifest, $app, Context::createDefaultContext(), $appFilesystem, 'en-GB'));

        $this->assertDefaultCustomFields($app->getId(), $customFieldSetId);
    }

    /**
     * @param list<string> $customFields
     * @param list<string> $relations
     */
    private function createCustomFieldSet(
        AppEntity $app,
        string $id,
        string $name,
        array $customFields,
        array $relations = ['product']
    ): void {
        $this->customFieldSetRepository->upsert([[
            'id' => $id,
            'name' => $name,
            'appId' => $app->getId(),
            'relations' => array_map(static fn (string $entityName): array => ['entityName' => $entityName], $relations),
            'customFields' => array_map(static fn (string $fieldName): array => [
                'name' => $fieldName,
                'type' => 'text',
            ], $customFields),
        ]], Context::createDefaultContext());
    }

    private function assertDefaultCustomFields(string $appId, ?string $expectedFieldSetId = null): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addAssociation('relations');
        $criteria->addAssociation('customFields');

        $customFieldSets = $this->customFieldSetRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $customFieldSets);

        $customFieldSet = $customFieldSets->first();
        static::assertNotNull($customFieldSet);
        if ($expectedFieldSetId) {
            static::assertSame($expectedFieldSetId, $customFieldSet->getId());
        }

        static::assertSame('custom_field_test', $customFieldSet->getName());
        static::assertCount(2, $customFieldSet->getRelations() ?? []);

        $relations = $customFieldSet->getRelations();
        static::assertNotNull($relations);

        $relatedEntities = array_map(static fn (CustomFieldSetRelationEntity $relation) => $relation->getEntityName(), $relations->getElements());
        static::assertContains('product', $relatedEntities);
        static::assertContains('customer', $relatedEntities);

        static::assertEquals([
            'label' => [
                'zh-CN' => '自定义字段测试',
                'en-GB' => 'Custom field test',
            ],
            'translated' => true,
        ], $customFieldSet->getConfig());
        static::assertTrue($customFieldSet->isGlobal());

        $customFieldCollection = $customFieldSet->getCustomFields();
        static::assertInstanceOf(CustomFieldCollection::class, $customFieldCollection);

        static::assertCount(2, $customFieldCollection);

        $fieldWithoutAllowWrite = $customFieldCollection->filterByProperty('name', 'bla_test')->first();
        static::assertInstanceOf(CustomFieldEntity::class, $fieldWithoutAllowWrite);

        static::assertFalse($fieldWithoutAllowWrite->isAllowCustomerWrite());

        $fieldWithAllowWrite = $customFieldCollection->filterByProperty('name', 'bla_test2')->first();
        static::assertInstanceOf(CustomFieldEntity::class, $fieldWithAllowWrite);

        static::assertTrue($fieldWithAllowWrite->isAllowCustomerWrite());
    }
}
