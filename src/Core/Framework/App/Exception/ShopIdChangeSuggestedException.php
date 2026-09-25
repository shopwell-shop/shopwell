<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Exception;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopwell\Core\Framework\App\ShopId\ShopId;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ShopIdChangeSuggestedException extends AppException
{
    public function __construct(
        public readonly ShopId $shopId,
        public readonly FingerprintComparisonResult $comparisonResult,
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            AppException::SHOP_ID_CHANGE_SUGGESTED,
            'Changes in your system were detected that suggest a change of the shop ID.'
        );
    }
}
