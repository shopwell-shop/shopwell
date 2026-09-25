<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductLineItemInconsistentException extends ShopwellHttpException
{
    public function __construct(string $lineItemId)
    {
        $message = \sprintf(
            'To change the product of line item (%s), the following properties must also be updated: `productId`, `referencedId`, `payload.productNumber`.',
            $lineItemId
        );

        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_LINE_ITEM_INCONSISTENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
