<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Lifecycle\Handler\McpLifecycleHandler;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Controller\McpServerController;
use Shopwell\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopwell\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopwell\Core\Framework\Mcp\McpToolsetRegistry;
use Shopwell\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopwell\Core\Framework\Mcp\Prompt\ShopwellContextPrompt;
use Shopwell\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopwell\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopwell\Core\Framework\Mcp\Resource\EntityListResource;
use Shopwell\Core\Framework\Mcp\Resource\ExtensionsResource;
use Shopwell\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopwell\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopwell\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopwell\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopwell\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopwell\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopwell\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopwell\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopwell\Core\Framework\Mcp\Tool\MediaUploadTool;
use Shopwell\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopwell\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopwell\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopwell\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Shopwell\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiContextTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class McpServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');
    }

    #[DataProvider('expectedServiceProvider')]
    public function testServiceIsRegistered(string $serviceId): void
    {
        static::assertTrue(
            $this->container->hasDefinition($serviceId),
            \sprintf('Service "%s" is not registered', $serviceId),
        );
    }

    #[DataProvider('toolServiceProvider')]
    public function testToolServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.tool'),
            \sprintf('Service "%s" is not tagged with mcp.tool', $serviceId),
        );
    }

    #[DataProvider('resourceServiceProvider')]
    public function testResourceServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.resource'),
            \sprintf('Service "%s" is not tagged with mcp.resource', $serviceId),
        );
    }

    public function testPromptServiceIsTagged(): void
    {
        static::assertTrue($this->container->getDefinition(ShopwellContextPrompt::class)->hasTag('mcp.prompt'));
    }

    public function testAppMcpToolLoaderIsTaggedAsMcpLoader(): void
    {
        static::assertTrue($this->container->getDefinition(AppMcpToolLoader::class)->hasTag('mcp.loader'));
    }

    public function testControllerIsPublic(): void
    {
        static::assertTrue($this->container->getDefinition(McpServerController::class)->isPublic());
        static::assertTrue($this->container->getDefinition(StoreApiMcpServerController::class)->isPublic());
    }

    public function testStoreApiToolServiceIsTagged(): void
    {
        static::assertTrue($this->container->getDefinition(StoreApiContextTool::class)->hasTag('shopwell.store_api_mcp.tool'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedServiceProvider(): iterable
    {
        yield McpLifecycleHandler::class => [McpLifecycleHandler::class];
        yield McpServerController::class => [McpServerController::class];
        yield StoreApiMcpServerController::class => [StoreApiMcpServerController::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield StoreApiContextTool::class => [StoreApiContextTool::class];
        yield ToolsetsListTool::class => [ToolsetsListTool::class];
        yield ToolsetEnableTool::class => [ToolsetEnableTool::class];
        yield ShopwellContextPrompt::class => [ShopwellContextPrompt::class];
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield SalesChannelListResource::class => [SalesChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield McpCapabilityCatalog::class => [McpCapabilityCatalog::class];
        yield McpToolsetRegistry::class => [McpToolsetRegistry::class];
        yield McpToolsetSessionStorage::class => [McpToolsetSessionStorage::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function toolServiceProvider(): iterable
    {
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield ToolsetsListTool::class => [ToolsetsListTool::class];
        yield ToolsetEnableTool::class => [ToolsetEnableTool::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resourceServiceProvider(): iterable
    {
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield SalesChannelListResource::class => [SalesChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
    }
}
