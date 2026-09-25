<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Persister;

use Shopwell\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptCollection;
use Shopwell\Core\Framework\App\Mcp\Mcp;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends AbstractMcpCapabilityPersister<AppMcpPromptCollection>
 */
#[Package('framework')]
class McpPromptPersister extends AbstractMcpCapabilityPersister
{
    /**
     * @param EntityRepository<AppMcpPromptCollection> $mcpPromptRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpPromptRepository,
    ) {
    }

    protected function getItemsFromMcp(?Mcp $mcp): array
    {
        return $mcp?->getPrompts()?->getPrompts() ?? [];
    }

    /**
     * @return EntityRepository<AppMcpPromptCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->mcpPromptRepository;
    }

    /**
     * @return AppMcpPromptCollection
     */
    protected function fetchExisting(string $appId, Context $context): EntityCollection
    {
        return $this->searchByAppId($appId, $context);
    }
}
