<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Persister;

use Shopwell\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\App\Mcp\Mcp;
use Shopwell\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends AbstractMcpCapabilityPersister<AppMcpToolCollection>
 */
#[Package('framework')]
class McpToolPersister extends AbstractMcpCapabilityPersister
{
    /**
     * @param EntityRepository<AppMcpToolCollection> $mcpToolRepository
     */
    public function __construct(
        private readonly EntityRepository $mcpToolRepository,
    ) {
    }

    public function validateRequiredPrivileges(Manifest $manifest, ?Mcp $mcp): void
    {
        $permissions = $manifest->getPermissions();
        if ($permissions === null) {
            return;
        }

        $tools = $mcp?->getTools()?->getTools() ?? [];
        $granted = $permissions->asParsedPrivileges();
        $appName = $manifest->getMetadata()->getName();

        foreach ($tools as $tool) {
            $required = $tool->getRequiredPrivileges();
            if ($required === []) {
                continue;
            }

            $missing = array_values(array_filter(
                $required,
                static fn (string $privilege): bool => !\in_array($privilege, $granted, true),
            ));

            if ($missing === []) {
                continue;
            }

            throw AppException::invalidConfiguration(
                $appName,
                new MissingPermissionError(array_map(
                    static fn (string $p): string => \sprintf('Tool "%s" requires "%s" but it is not declared in <permissions>', $tool->getName(), $p),
                    $missing,
                )),
            );
        }
    }

    protected function getItemsFromMcp(?Mcp $mcp): array
    {
        return $mcp?->getTools()?->getTools() ?? [];
    }

    /**
     * @return EntityRepository<AppMcpToolCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->mcpToolRepository;
    }

    /**
     * @return AppMcpToolCollection
     */
    protected function fetchExisting(string $appId, Context $context): EntityCollection
    {
        return $this->searchByAppId($appId, $context);
    }
}
