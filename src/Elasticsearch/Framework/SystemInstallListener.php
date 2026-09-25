<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework;

use Shopwell\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class SystemInstallListener
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
    ) {
    }

    public function __invoke(SystemInstallCompletedEvent $event): void
    {
        try {
            $this->indexer->createIndices();
        } catch (\Throwable) {
            // Unreachable or misconfigured Elasticsearch must not fail system:install
        }
    }
}
