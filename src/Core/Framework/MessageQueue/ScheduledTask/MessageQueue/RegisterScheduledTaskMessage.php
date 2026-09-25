<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed as it was not dispatched anymore, call TaskRegistry synchronously
 */
class RegisterScheduledTaskMessage implements AsyncMessageInterface
{
}
