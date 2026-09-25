<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ExportNotGeneratedException extends ShopwellHttpException
{
    public function __construct()
    {
        parent::__construct('Export file has not been generated yet. Please make sure that the scheduled task are working or run the command manually.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_NOT_GENERATED';
    }
}
