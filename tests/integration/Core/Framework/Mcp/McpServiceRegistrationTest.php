<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Core\Framework\Mcp\Controller\McpServerController;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopwell\Core\Framework\Mcp\Loader\AppMcpToolLoader;
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
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * Verifies that all MCP services are registered in the DI container.
 */
#[Package('framework')]
class McpServiceRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return iterable<string, array{string}>
     */
    public static function mcpServiceProvider(): iterable
    {
        yield McpContextProvider::class => [McpContextProvider::class];
        yield McpServerController::class => [McpServerController::class];
        yield DebugMcpCommand::class => [DebugMcpCommand::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield ShopwellContextPrompt::class => [ShopwellContextPrompt::class];
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield SalesChannelListResource::class => [SalesChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield AppMcpPromptLoader::class => [AppMcpPromptLoader::class];
        yield AppMcpResourceLoader::class => [AppMcpResourceLoader::class];
    }

    #[DataProvider('mcpServiceProvider')]
    public function testMcpServiceIsRegistered(string $serviceClass): void
    {
        static::assertTrue(
            static::getContainer()->has($serviceClass),
            \sprintf('Service "%s" should be registered.', $serviceClass),
        );
    }
}
