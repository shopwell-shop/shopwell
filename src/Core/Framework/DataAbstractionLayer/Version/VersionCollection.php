<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Version;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<VersionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class VersionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_version_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}
