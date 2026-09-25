<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerNotLoggedInRoutingException extends RoutingException
{
}
