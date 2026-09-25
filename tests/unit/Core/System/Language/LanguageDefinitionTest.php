<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(LanguageDefinition::class)]
class LanguageDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(LanguageDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(LanguageEntity::class, $definition->getEntityClass());
        static::assertSame(LanguageCollection::class, $definition->getCollectionClass());
        static::assertSame('6.0.0.0', $definition->since());
        static::assertNotSame([], $definition->getDefaults());
    }

    public function testDeprecatedImportExportProfileTranslationsFieldIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('importExportProfileTranslations'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedImportExportProfileTranslationsFieldStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('importExportProfileTranslations');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
    }

    private function createDefinition(): LanguageDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [LanguageDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(LanguageDefinition::ENTITY_NAME);
        static::assertInstanceOf(LanguageDefinition::class, $definition);

        return $definition;
    }
}
