<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SalesChannel;

use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class MockSeoUrlAwareExtension extends Struct
{
    /**
     * @var array<SalesChannelProductEntity>
     */
    protected array $searchResults = [];

    public function addSearchResult(SalesChannelProductEntity $entity): void
    {
        $this->searchResults[] = $entity;
    }
}
