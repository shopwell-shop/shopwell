<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Notification;

use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopwell\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopwell\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class AppMcpCapabilityDetector
{
    public function __construct(
        private readonly AppFeatureStorage $storage,
    ) {
    }

    public function persistedForApp(string $appId): McpListChangedNotificationSet
    {
        return new McpListChangedNotificationSet(
            tools: $this->storage->forApp($appId, McpToolConfig::class) !== [],
            resources: $this->storage->forApp($appId, McpResourceConfig::class) !== [],
            prompts: $this->storage->forApp($appId, McpPromptConfig::class) !== [],
        );
    }
}
