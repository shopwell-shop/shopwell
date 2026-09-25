<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelException;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class NoContextDataException extends SalesChannelException
{
}
