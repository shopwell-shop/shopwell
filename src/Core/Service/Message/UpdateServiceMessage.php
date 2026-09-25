<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class UpdateServiceMessage implements AsyncMessageInterface
{
    public function __construct(public string $name)
    {
    }
}
