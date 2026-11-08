<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class LandingPageContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'landing_page_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'landing_page';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_landing_page_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LandingPageContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LandingPageContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [LandingPageRoute::buildName($entityId)];
    }

    public function getContentLayoutDefaultConfigKey(): string
    {
        return self::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT;
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('landing_page_id', 'landingPageId');
    }
}
