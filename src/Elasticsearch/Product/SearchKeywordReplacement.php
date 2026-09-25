<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Product;

use Shopwell\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as `elasticsearch.indexing_enabled` already prevents the indexing of search keywords.
 */
#[Package('framework')]
class SearchKeywordReplacement extends SearchKeywordUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SearchKeywordUpdater $decorated,
        private readonly ElasticsearchHelper $helper
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->decorated->update($ids, $context);

            return;
        }

        if ($this->helper->allowIndexing()) {
            return;
        }

        $this->decorated->update($ids, $context);
    }

    public function reset(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->decorated->reset();

            return;
        }

        $this->decorated->reset();
    }
}
