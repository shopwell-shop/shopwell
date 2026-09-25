<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractMediaSerializer extends EntitySerializer
{
    abstract public function persistMedia(EntityWrittenEvent $event): void;
}
