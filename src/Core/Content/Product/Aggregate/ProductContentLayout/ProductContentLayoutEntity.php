<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected string $productId;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
