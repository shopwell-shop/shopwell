<?php declare(strict_types=1);

namespace Shopwell\Core\Test\PHPUnit\Extension\Datadog;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<DatadogPayload>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DatadogPayloadCollection extends Collection
{
}
