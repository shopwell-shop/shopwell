<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopwell\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

#[Package('framework')]
class CleanupOldCacheFolders implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @experimental stableVersion:v6.8.0 feature:DEDUPLICATABLE_MESSAGES
     */
    public function deduplicationId(): ?string
    {
        return 'cleanup-old-cache-folders';
    }
}
