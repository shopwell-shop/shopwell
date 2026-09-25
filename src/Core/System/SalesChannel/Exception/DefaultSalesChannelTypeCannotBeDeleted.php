<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class DefaultSalesChannelTypeCannotBeDeleted extends ShopwellHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Cannot delete system default sales channel type', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__SALES_CHANNEL_DEFAULT_TYPE_CANNOT_BE_DELETED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
