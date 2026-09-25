<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryDefinition::class)]
class CategoryDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(CategoryDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(CategoryEntity::class, $definition->getEntityClass());
        static::assertSame(CategoryCollection::class, $definition->getCollectionClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    public function testDeprecatedCmsPageIdSwitchedFieldIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('cmsPageIdSwitched'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedCmsPageIdSwitchedFieldStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('cmsPageIdSwitched');

        static::assertInstanceOf(BoolField::class, $field);
    }

    private function createDefinition(): CategoryDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertInstanceOf(CategoryDefinition::class, $definition);

        return $definition;
    }
}
