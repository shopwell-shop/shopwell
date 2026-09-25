<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Shopwell\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ConditionTypeNotFound extends \RuntimeException
{
}
