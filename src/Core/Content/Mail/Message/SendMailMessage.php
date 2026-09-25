<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Mail\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class SendMailMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(public readonly string $mailDataPath)
    {
    }
}
