<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SearchKeyword;

use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductSearchKeywordAnalyzerInterface
{
    /**
     * @param array<int, array{field: string, tokenize: '1'|'0'|bool, ranking: numeric-string|int|float, language_id?: string}> $configFields
     */
    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection;
}
