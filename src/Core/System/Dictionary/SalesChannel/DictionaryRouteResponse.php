<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<DictionaryEntryCollection>>
 */
#[Package('fundamentals@discovery')]
class DictionaryRouteResponse extends StoreApiResponse
{
    public function getEntries(): DictionaryEntryCollection
    {
        return $this->object->getEntities();
    }
}
