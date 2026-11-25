<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalBindingSubscriber;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalRoutingExtensionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    #[DataProvider('amounts')]
    public function testAnExtensionSelectsThePublishedOutputFromTheFrozenRequest(int $amount, string $reviewNode): void
    {
        $kernel = static::getContainer();
        $connection = $kernel->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection)->getUserId();
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-route-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($filesystem, $directory, [AmountRouteCompiler::class, AmountRouteExecutor::class]);
        try {
            $runtime = ApprovalRuntimeFixture::container($kernel, $manifest, static function (ContainerBuilder $container): void {
                $container->register(AmountRouteLoader::class)->addTag('approval_flow.node_type_loader');
                $container->register(AmountRouteCompiler::class)->addTag('approval_flow.node_compiler');
                $container->register(AmountRouteExecutor::class)->addTag('approval_flow.node_executor');
                $container->getDefinition(NodeTypeRegistry::class)->setPublic(true);
            });
            $dispatcher = new EventDispatcher();
            $dispatcher->addSubscriber(new PaymentTransferApprovalBindingSubscriber($runtime->get(NodeTypeRegistry::class)));
            $runtime->set('event_dispatcher', $dispatcher);
            $releaseId = ApprovalRuntimeFixture::publish($runtime, $runtime, $this->graph($reviewer));
            $bindings = $runtime->get(ApprovalBindingService::class);
            $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
            $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
            $source = new AdminApiSource($reviewer);
            $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create', ApprovalInstanceService::DECIDE_PRIVILEGE]);
            $context = Context::createDefaultContext($source);
            $requests = $runtime->get(ApprovalRequestService::class);
            $request = $requests->create(
                'payment:transfer',
                Uuid::randomHex(),
                1,
                hash('sha256', 'routing revision'),
                json_encode(['amount' => $amount, 'currency' => 'CNY', 'recipient' => 'Recipient', 'paymentAccount' => 'Merchant', 'reason' => null], \JSON_THROW_ON_ERROR),
                $context,
            );

            $receipt = $requests->start($request->getId(), $context);

            static::assertSame(['start', 'route', $reviewNode], $connection->fetchFirstColumn('SELECT node_id FROM approval_flow_execution ORDER BY sequence'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
            static::assertEquals($receipt, $requests->start($request->getId(), $context));
            static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
            $taskId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM approval_flow_task'));
            $executionId = Uuid::fromBytesToHex($connection->fetchOne('SELECT execution_id FROM approval_flow_task'));
            $service = $runtime->get(ApprovalInstanceService::class);
            $service->decide($request->getInstanceId(), $taskId, Uuid::randomHex(), 2, ApprovalOutcome::Approved, '', $context);
            $completed = $service->progress($request->getInstanceId(), $executionId, Uuid::randomHex(), 3, $context);
            static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
            static::assertSame(['start', 'route', $reviewNode, 'end'], $connection->fetchFirstColumn('SELECT node_id FROM approval_flow_execution ORDER BY sequence'));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public static function amounts(): iterable
    {
        yield 'ordinary transfer' => [100, 'review'];
        yield 'large transfer' => [2000, 'high-review'];
    }

    private function graph(string $reviewer): string
    {
        $graph = json_decode(str_replace('finance-user', $reviewer, GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        static::assertIsArray($graph->nodes);
        static::assertIsArray($graph->connections);
        $route = json_decode('{"id":"route","type":"example:amount-route","typeVersion":"1.0.0","config":{"threshold":1000},"ports":[{"id":"in","direction":"input","channel":"control","cardinality":"one"},{"id":"low","direction":"output","channel":"control","cardinality":"one"},{"id":"high","direction":"output","channel":"control","cardinality":"one"}],"position":{"x":0,"y":100},"extensions":{}}', flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[1]);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[2]);
        static::assertIsArray($graph->nodes[2]->ports);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[2]->ports[0]);
        $graph->nodes[2]->ports[0]->cardinality = 'many';
        $high = clone $graph->nodes[1];
        $high->id = 'high-review';
        $graph->nodes[] = $route;
        $graph->nodes[] = $high;
        static::assertInstanceOf(\stdClass::class, $graph->connections[0]);
        $graph->connections[0]->target = 'route';
        $lowEdge = clone $graph->connections[0];
        $lowEdge->id = 'route-low';
        $lowEdge->source = 'route';
        $lowEdge->sourcePort = 'low';
        $lowEdge->target = 'review';
        $highEdge = clone $lowEdge;
        $highEdge->id = 'route-high';
        $highEdge->sourcePort = 'high';
        $highEdge->target = 'high-review';
        static::assertInstanceOf(\stdClass::class, $graph->connections[1]);
        $endEdge = clone $graph->connections[1];
        $endEdge->id = 'high-end';
        $endEdge->source = 'high-review';
        $graph->connections = [...$graph->connections, $lowEdge, $highEdge, $endEdge];

        return json_encode($graph, \JSON_THROW_ON_ERROR);
    }
}

/**
 * @internal
 */
class AmountRouteLoader implements NodeTypeLoader
{
    public function load(): array
    {
        return [new NodeTypeSpecification(
            type: 'example:amount-route',
            version: '1.0.0',
            labelSnippet: 'example.amountRoute',
            source: self::class,
            configurationSchema: '{"$id":"urn:example:amount-route","type":"object","additionalProperties":false,"properties":{"threshold":{"type":"integer"}},"required":["threshold"]}',
            ports: ['in' => 'input', 'low' => 'output', 'high' => 'output'],
            allowedEffects: [NodeEffectType::Advance],
        )];
    }
}

/**
 * @internal
 */
class AmountRouteCompiler implements NodeCompiler
{
    public function getTypes(): array
    {
        return ['example:amount-route' => ['1.0.0']];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        $configuration = json_decode($configurationJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($configuration) || !\is_int($configuration['threshold'] ?? null)) {
            throw new \InvalidArgumentException('An integer threshold is required.');
        }

        return new AmountRouteConfiguration($configuration['threshold']);
    }
}

/**
 * @internal
 */
final readonly class AmountRouteConfiguration implements NodeConfiguration
{
    public function __construct(public int $threshold)
    {
    }

    public function jsonSerialize(): array
    {
        return ['threshold' => $this->threshold];
    }
}

/**
 * @internal
 */
class AmountRouteExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return 'example:amount-route';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect
    {
        if (!$configuration instanceof AmountRouteConfiguration) {
            throw new \InvalidArgumentException('Expected amount route configuration.');
        }
        $form = json_decode($context->formJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($form) || !\is_int($form['amount'] ?? null)) {
            throw new \InvalidArgumentException('The published business form requires an integer amount.');
        }

        return new AdvanceEffect([$form['amount'] >= $configuration->threshold ? 'high' : 'low']);
    }
}
