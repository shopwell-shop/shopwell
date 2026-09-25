<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Log;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LogEntryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LogEntryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_log_entry_collection';
    }

    protected function getExpectedClass(): string
    {
        return LogEntryEntity::class;
    }
}
