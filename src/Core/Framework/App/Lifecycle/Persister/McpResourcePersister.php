<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Persister;

use Shopwell\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceCollection;
use Shopwell\Core\Framework\App\Mcp\Mcp;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends AbstractMcpCapabilityPersister<AppMcpResourceCollection>
 */
#[Package('framework')]
class McpResourcePersister extends AbstractMcpCapabilityPersister
{
    /**
     * @param EntityRepository<AppMcpResourceCollection> $mcpResourceRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpResourceRepository,
    ) {
    }

    protected function getItemsFromMcp(?Mcp $mcp): array
    {
        return $mcp?->getResources()?->getResources() ?? [];
    }

    /**
     * @return EntityRepository<AppMcpResourceCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->mcpResourceRepository;
    }

    /**
     * @return AppMcpResourceCollection
     */
    protected function fetchExisting(string $appId, Context $context): EntityCollection
    {
        return $this->searchByAppId($appId, $context);
    }
}
