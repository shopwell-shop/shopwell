<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SalesChannelNotFoundException extends ShopwellHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Sales channel with ID {{ id }} not found', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_SALES_CHANNEL_NOT_FOUND';
    }
}
