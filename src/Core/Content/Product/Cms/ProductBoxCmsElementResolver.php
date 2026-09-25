<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cms;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class ProductBoxCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getType(): string
    {
        return 'product-box';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null || $productConfig->isMapped() || $productConfig->getValue() === null) {
            return null;
        }

        $criteria = new Criteria([$productConfig->getStringValue()]);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options.group');

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('product_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $productBox = new ProductBoxStruct();
        $slot->setData($productBox);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null || $productConfig->getValue() === null) {
            return;
        }

        if ($resolverContext instanceof EntityResolverContext && $productConfig->isMapped()) {
            /** @var SalesChannelProductEntity $product */
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());

            $productBox->setProduct($product);
            $productBox->setProductId($product->getId());
        }

        if ($productConfig->isStatic()) {
            $this->resolveProductFromRemote($slot, $productBox, $result, $productConfig->getStringValue(), $resolverContext->getSalesChannelContext());
        }
    }

    private function resolveProductFromRemote(
        CmsSlotEntity $slot,
        ProductBoxStruct $productBox,
        ElementDataCollection $result,
        string $productId,
        SalesChannelContext $salesChannelContext
    ): void {
        $product = $result->get('product_' . $slot->getUniqueIdentifier())?->getEntities()->get($productId);
        if (!$product instanceof SalesChannelProductEntity) {
            return;
        }

        if ($product->getIsCloseout()
            && $product->getStock() <= 0
            && $this->systemConfigService->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelContext->getSalesChannelId())
        ) {
            return;
        }

        $productBox->setProduct($product);
        $productBox->setProductId($product->getId());
    }
}
