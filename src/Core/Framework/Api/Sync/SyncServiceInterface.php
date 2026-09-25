<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Sync;

use Doctrine\DBAL\ConnectionException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface SyncServiceInterface
{
    /**
     * @param list<SyncOperation> $operations
     *
     * @throws ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult;
}
