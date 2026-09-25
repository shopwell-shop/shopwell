<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class FieldNotStorageAwareException extends ShopwellHttpException
{
    public function __construct(string $field)
    {
        parent::__construct(
            'The field {{ field }} must implement the StorageAware interface to be accessible.',
            ['field' => $field]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__FIELD_IS_NOT_STORAGE_AWARE';
    }
}
