<?php declare(strict_types=1);

namespace Shopwell\Core\System\Locale\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class InvalidLocaleCodeException extends ShopwellHttpException
{
    public function __construct(string $code)
    {
        parent::__construct('Cannot create or update locale with invalid code "{{ code }}"', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__INVALID_LOCALE_CODE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
