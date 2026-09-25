<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopwell\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportProfileDefinition::class)]
class ImportExportProfileDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(ImportExportProfileDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(ImportExportProfileEntity::class, $definition->getEntityClass());
    }

    public function testDeprecatedTranslatedLabelIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('label'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedTranslatedLabelStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('label');

        static::assertInstanceOf(TranslatedField::class, $field);
    }

    private function createDefinition(): ImportExportProfileDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ImportExportProfileDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(ImportExportProfileDefinition::ENTITY_NAME);
        static::assertInstanceOf(ImportExportProfileDefinition::class, $definition);

        return $definition;
    }
}
