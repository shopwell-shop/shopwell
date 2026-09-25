<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Context;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class ShopApiSource extends SalesChannelApiSource
{
    public string $type = 'shop-api';
}
