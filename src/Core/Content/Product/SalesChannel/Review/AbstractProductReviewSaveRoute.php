<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Review;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
abstract class AbstractProductReviewSaveRoute
{
    abstract public function getDecorated(): AbstractProductReviewSaveRoute;

    abstract public function save(string $productId, RequestDataBag $data, SalesChannelContext $context): NoContentResponse;
}
