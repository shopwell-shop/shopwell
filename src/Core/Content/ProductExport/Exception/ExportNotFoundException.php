<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ExportNotFoundException extends ShopwellHttpException
{
    public function __construct(
        ?string $id = null,
        ?string $fileName = null
    ) {
        $message = 'No product exports found';

        if ($id) {
            $message = 'Product export with ID {{ id }} not found. Make sure the export exists and the export sales channel is active';
        } elseif ($fileName) {
            $message = 'Product export with file name {{ fileName }} not found. Please check your access key.';
        }

        parent::__construct($message, ['id' => $id, 'fileName' => $fileName]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_NOT_FOUND';
    }
}
