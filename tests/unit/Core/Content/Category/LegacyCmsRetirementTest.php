<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryDefinition::class)]
class LegacyCmsRetirementTest extends TestCase
{
    public function testContentOwnersExposeNoLegacyCmsFields(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, ProductDefinition::class, LandingPageDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        foreach (['category', 'product', 'landing_page'] as $entityName) {
            $fields = $registry->getByEntityName($entityName)->getFields();
            foreach (['cmsPage', 'cmsPageId', 'cmsPageVersionId', 'slotConfig', 'cmsPageIdSwitched'] as $field) {
                static::assertNull($fields->get($field), $entityName . '.' . $field);
            }
        }
    }

    public function testPlatformAssociationsDoNotReferenceRetiredCmsEntities(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelDefinition::class, LanguageDefinition::class, AppDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );
        foreach ([
            'sales_channel' => ['homeCmsPage', 'homeCmsPageId', 'homeCmsPageVersionId', 'homeSlotConfig'],
            'language' => ['cmsPageTranslations', 'cmsSlotTranslations', 'appCmsBlockTranslations'],
            'app' => ['cmsBlocks'],
        ] as $entity => $removedFields) {
            $fields = $registry->getByEntityName($entity)->getFields();
            foreach ($removedFields as $field) {
                static::assertNull($fields->get($field), $entity . '.' . $field);
            }
        }
    }
}
