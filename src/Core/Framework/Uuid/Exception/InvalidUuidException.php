<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Uuid\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidUuidException extends ShopwellHttpException
{
    public function __construct(string $uuid)
    {
        parent::__construct('Value is not a valid UUID: {{ input }}', ['input' => $uuid]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_UUID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
