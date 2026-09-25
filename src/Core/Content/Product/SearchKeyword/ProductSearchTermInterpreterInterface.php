<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SearchKeyword;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductSearchTermInterpreterInterface
{
    public function interpret(string $word, Context $context): SearchPattern;
}
