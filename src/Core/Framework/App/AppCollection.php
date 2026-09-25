<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppEntity::class;
    }
}
