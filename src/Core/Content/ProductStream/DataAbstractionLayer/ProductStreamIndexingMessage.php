<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamIndexingMessage extends EntityIndexingMessage
{
}
