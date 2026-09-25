<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal
 */
#[Package('framework')]
class HookableEventCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MANIFEST_FIXTURE = __DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml';

    private HookableEventCollector $hookableEventCollector;

    protected function setUp(): void
    {
        $this->hookableEventCollector = static::getContainer()->get(HookableEventCollector::class);
    }

    public function testGetHookableEventNamesWithPrivileges(): void
    {
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges(
            Context::createDefaultContext(),
            Manifest::createFromXmlFile(self::MANIFEST_FIXTURE)
        );
        static::assertNotEmpty($hookableEventNamesWithPrivileges);

        foreach ($hookableEventNamesWithPrivileges as $key => $hookableEventNamesWithPrivilege) {
            static::assertIsArray($hookableEventNamesWithPrivilege);
            static::assertIsString($key);
            static::assertArrayHasKey('privileges', $hookableEventNamesWithPrivilege);
        }
    }

    public function testGetHookableEntities(): void
    {
        $hookableEntities = $this->hookableEventCollector->getHookableEntities();
        static::assertNotEmpty($hookableEntities);

        static::assertContains(ProductDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(ProductPriceDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(MediaDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CategoryDefinition::ENTITY_NAME, $hookableEntities);
        static::assertContains(CustomerDefinition::ENTITY_NAME, $hookableEntities);
    }

    public function testGetHookableEntitiesWithEntityWithAttribute(): void
    {
        // Create test entity with EntityAttribute
        $testEntity = new TestEntityWithAttribute();

        $collector = new HookableEventCollector(
            static::getContainer()->get(BusinessEventCollector::class),
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            new \ArrayIterator([$testEntity]),
            new \ArrayIterator([static::getContainer()->get(CoreHookableEventDescriber::class)])
        );

        $entities = $collector->getHookableEntities();

        static::assertContains('test_entity_with_attr', $entities);
        static::assertCount(1, $entities);
    }
}

/**
 * @internal Test fixture for entity with EntityAttribute
 */
#[EntityAttribute('test_entity_with_attr')]
class TestEntityWithAttribute extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;
}
