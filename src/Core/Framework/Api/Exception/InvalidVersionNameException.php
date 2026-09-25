<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidVersionNameException extends ShopwellHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid version name given. Only alphanumeric characters are allowed');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_VERSION_NAME';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
