<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Core\Event;

use Shopwell\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * The event is dispatched, when location for a media should be generated afterward and can be used
 * to extend the data which is required for this process.
 *
 * @implements \IteratorAggregate<array-key, MediaLocationStruct>
 */
#[Package('discovery')]
class MediaLocationEvent implements \IteratorAggregate
{
    /**
     * @param array<string, MediaLocationStruct> $locations
     */
    public function __construct(public array $locations)
    {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->locations);
    }
}
