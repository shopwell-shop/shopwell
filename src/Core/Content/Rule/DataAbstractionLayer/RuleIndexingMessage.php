<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Rule\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class RuleIndexingMessage extends EntityIndexingMessage
{
}
