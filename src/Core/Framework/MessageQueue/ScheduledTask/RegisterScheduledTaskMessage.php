<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\ScheduledTask;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('framework')]
class RegisterScheduledTaskMessage implements AsyncMessageInterface
{
}
