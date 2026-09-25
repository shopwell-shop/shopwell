<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelFile;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelFileEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelFileEntity::class;
    }
}
