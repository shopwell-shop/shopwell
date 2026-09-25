<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Indexing;

use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class PostUpdateIndexer extends EntityIndexer
{
    final public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        return null;
    }
}
