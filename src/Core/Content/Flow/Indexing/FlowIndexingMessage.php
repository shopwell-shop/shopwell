<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Indexing;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
class FlowIndexingMessage extends EntityIndexingMessage
{
}
