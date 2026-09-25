<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class UpdatedByValueNotFoundException extends ShopwellHttpException
{
    public function __construct(
        string $entityName,
        string $field
    ) {
        parent::__construct('Data set "{{ entityName }}" is set to be updated by field "{{ field }}" but the field\'s column couldn\'t be found or isn\'t mapped', [
            'entityName' => $entityName,
            'field' => $field,
        ]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_UPDATED_BY';
    }
}
