<?php declare(strict_types=1);

namespace Shopwell\Core\System\Integration;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<IntegrationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class IntegrationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'integration_collection';
    }

    protected function getExpectedClass(): string
    {
        return IntegrationEntity::class;
    }
}
