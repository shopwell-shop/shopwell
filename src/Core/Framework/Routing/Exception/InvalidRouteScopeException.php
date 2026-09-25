<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidRouteScopeException extends RoutingException
{
    public function __construct(?string $routeName)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            parent::INVALID_ROUTE_SCOPE,
            'Invalid route scope for route {{ routeName }}.',
            ['routeName' => $routeName ?? '']
        );
    }
}
