<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\SeoUrl;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SeoUrlEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SeoUrlCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): SeoUrlCollection
    {
        return $this->filter(static fn (SeoUrlEntity $seoUrl) => $seoUrl->getSalesChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'seo_url_collection';
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlEntity::class;
    }
}
