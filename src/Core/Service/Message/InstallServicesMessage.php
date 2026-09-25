<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class InstallServicesMessage implements AsyncMessageInterface
{
}
