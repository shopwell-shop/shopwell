<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class TestProductDetailCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'test';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // nth
    }

    public function runGetSlotProduct(CmsSlotEntity $slot, ElementDataCollection $result, string $productId): ?SalesChannelProductEntity
    {
        return $this->getSlotProduct($slot, $result, $productId);
    }
}
