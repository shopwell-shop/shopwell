<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Increment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class IncrementGatewayNotFoundException extends ShopwellHttpException
{
    public function __construct(string $pool)
    {
        parent::__construct(
            'Increment gateway for pool "{{ pool }}" was not found.',
            ['pool' => $pool]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCREMENT_GATEWAY_NOT_FOUND';
    }
}
