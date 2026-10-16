<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
 */
#[Package('framework')]
class InvalidSyncOperationException extends ShopwellHttpException
{
    public function __construct(string $message)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_SYNC_OPERATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
