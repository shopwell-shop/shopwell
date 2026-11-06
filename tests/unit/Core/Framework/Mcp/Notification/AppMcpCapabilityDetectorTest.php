<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Feature\AppFeature;
use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\App\Feature\TranslatedString;
use Shopwell\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopwell\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopwell\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopwell\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppMcpCapabilityDetector::class)]
class AppMcpCapabilityDetectorTest extends TestCase
{
    public function testDetectsPersistedCapabilitiesForApp(): void
    {
        $appId = Uuid::randomHex();
        $tool = new McpToolConfig('sync-orders', 'https://app.example.com/mcp/sync-orders', [], null, new TranslatedString([]), new TranslatedString([]));
        $prompt = new McpPromptConfig('order-context', 'https://app.example.com/mcp/order-context', new TranslatedString([]), new TranslatedString([]));

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forApp')->willReturnMap([
            [$appId, McpToolConfig::class, [new AppFeature($appId, 'my-app', true, '1.0.0', true, new \DateTimeImmutable(), $tool)]],
            [$appId, McpPromptConfig::class, [new AppFeature($appId, 'my-app', true, '1.0.0', true, new \DateTimeImmutable(), $prompt)]],
            [$appId, McpResourceConfig::class, []],
        ]);

        $capabilities = (new AppMcpCapabilityDetector($storage))->persistedForApp($appId);

        static::assertEquals(new McpListChangedNotificationSet(tools: true, resources: false, prompts: true), $capabilities);
    }
}
