<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class MissingRootFilterException extends ShopwellHttpException
{
    public function __construct()
    {
        parent::__construct('Missing root filter ');
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_EMPTY';
    }
}
