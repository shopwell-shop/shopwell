<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\WaitEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final class ParallelApprovalFixture
{
    public static function manifest(Filesystem $filesystem, string $directory): string
    {
        return PublicationFixture::writeManifest($filesystem, $directory, [ParallelCompiler::class, ParallelForkExecutor::class, ParallelJoinExecutor::class]);
    }

    public static function container(ContainerInterface $kernel, string $manifest): ContainerBuilder
    {
        return ApprovalRuntimeFixture::container($kernel, $manifest, static function (ContainerBuilder $container): void {
            $container->register(ParallelLoader::class)->addTag('approval_flow.node_type_loader');
            $container->register(ParallelCompiler::class)->addTag('approval_flow.node_compiler');
            $container->register(ParallelForkExecutor::class)->addTag('approval_flow.node_executor');
            $container->register(ParallelJoinExecutor::class)->addTag('approval_flow.node_executor');
            $container->getDefinition(ApprovalInstanceStore::class)->setPublic(true);
            $container->getDefinition(NodeTypeRegistry::class)->setPublic(true);
        });
    }

    /**
     * @param list<string> $outputs
     */
    public static function graph(string $reviewerId, array $outputs = ['left', 'right'], int $minimum = 0, bool $nested = false, bool $reuseBoundary = false, bool $escapeBoundary = false): string
    {
        $approval = ['assignees' => [['kind' => 'core:users', 'userIds' => [$reviewerId]]], 'policy' => ['kind' => 'core:all', 'reject' => 'veto'], 'fields' => [], 'emptyAssignees' => 'incident'];
        $nodes = [
            self::node('start', 'core:start', ['businessType' => 'payment:transfer'], ['out' => 'output']),
            self::node('fork', 'example:fork', ['joinNodeId' => 'join', 'outputs' => $outputs], ['in' => 'input', 'left' => 'output', 'right' => 'output']),
            self::node('finance', 'core:approval', $approval, ['in' => 'input', 'out' => 'output']),
            self::node('manager', 'core:approval', $approval, ['in' => 'input', 'out' => 'output']),
            self::node('join', 'example:join', ['minimum' => $minimum], ['in' => 'input', 'out' => 'output']),
            self::node('end', 'core:end', ['outcome' => 'approved'], ['in' => 'input']),
        ];
        $connections = [
            self::edge('start', 'fork'), self::edge('fork', 'finance', 'left'), self::edge('fork', 'manager', 'right'),
            self::edge('finance', 'join'), self::edge('manager', 'join'), self::edge('join', 'end'),
        ];

        if ($escapeBoundary) {
            $connections[3] = self::edge('finance', 'end');
        }
        if ($nested) {
            $nodes[2] = self::node('inner-fork', 'example:fork', ['joinNodeId' => $reuseBoundary ? 'join' : 'inner-join', 'outputs' => ['left', 'right']], ['in' => 'input', 'left' => 'output', 'right' => 'output']);
            $nodes[] = self::node('inner-finance', 'core:approval', $approval, ['in' => 'input', 'out' => 'output']);
            $nodes[] = self::node('inner-legal', 'core:approval', $approval, ['in' => 'input', 'out' => 'output']);
            $nodes[] = self::node('inner-join', 'example:join', ['minimum' => 0], ['in' => 'input', 'out' => 'output']);
            $connections[1] = self::edge('fork', 'inner-fork', 'left');
            $connections[3] = self::edge('inner-join', 'join');
            $connections[] = self::edge('inner-fork', 'inner-finance', 'left');
            $connections[] = self::edge('inner-fork', 'inner-legal', 'right');
            $connections[] = self::edge('inner-finance', 'inner-join');
            $connections[] = self::edge('inner-legal', 'inner-join');
        }

        return json_encode(['schemaVersion' => '1.0.0', 'id' => 'parallel', 'meta' => ['name' => 'Parallel review', 'editingMode' => 'oa', 'formSchemaRef' => 'payment:transfer'], 'nodes' => $nodes, 'connections' => $connections, 'layout' => ['algorithm' => 'core:layered', 'nodes' => []], 'extensions' => new \stdClass()], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $ports
     *
     * @return array<string, mixed>
     */
    private static function node(string $id, string $type, array $config, array $ports): array
    {
        $descriptors = [];
        foreach ($ports as $port => $direction) {
            $descriptors[] = ['id' => $port, 'direction' => $direction, 'channel' => 'control', 'cardinality' => \in_array($type, ['example:join', 'core:end'], true) && $direction === 'input' ? 'many' : 'one'];
        }

        return ['id' => $id, 'type' => $type, 'typeVersion' => '1.0.0', 'config' => $config, 'ports' => $descriptors, 'position' => ['x' => 0, 'y' => 0], 'extensions' => new \stdClass()];
    }

    /**
     * @return array<string, mixed>
     */
    private static function edge(string $source, string $target, string $sourcePort = 'out'): array
    {
        return ['id' => $source . '-' . $target, 'source' => $source, 'target' => $target, 'sourcePort' => $sourcePort, 'targetPort' => 'in', 'condition' => ['kind' => 'always'], 'extensions' => new \stdClass()];
    }
}

/**
 * @internal
 */
class ParallelLoader implements NodeTypeLoader
{
    public function load(): array
    {
        return [
            new NodeTypeSpecification(
                type: 'example:fork',
                version: '1.0.0',
                labelSnippet: 'example.fork',
                source: self::class,
                configurationSchema: '{"$id":"urn:example:fork","type":"object","additionalProperties":false,"properties":{"joinNodeId":{"type":"string"},"outputs":{"type":"array","items":{"type":"string"},"minItems":1,"uniqueItems":true}},"required":["joinNodeId","outputs"]}',
                ports: ['in' => 'input', 'left' => 'output', 'right' => 'output'],
                allowedEffects: [NodeEffectType::OpenScope]
            ),
            new NodeTypeSpecification(
                type: 'example:join',
                version: '1.0.0',
                labelSnippet: 'example.join',
                source: self::class,
                configurationSchema: '{"$id":"urn:example:join","type":"object","additionalProperties":false,"properties":{"minimum":{"type":"integer","minimum":0}},"required":["minimum"]}',
                ports: ['in' => 'input', 'out' => 'output'],
                allowedEffects: [NodeEffectType::Advance, NodeEffectType::Wait]
            ),
        ];
    }
}

/**
 * @internal
 */
class ParallelCompiler implements NodeCompiler
{
    public function getTypes(): array
    {
        return ['example:fork' => ['1.0.0'], 'example:join' => ['1.0.0']];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        $config = json_decode($configurationJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        if ($type === 'example:fork') {
            return new ParallelForkConfiguration($config['joinNodeId'], $config['outputs']);
        }

        return new ParallelJoinConfiguration($config['minimum']);
    }
}

/**
 * @internal
 */
final readonly class ParallelForkConfiguration implements NodeConfiguration
{
    /**
     * @param list<string> $outputs
     */
    public function __construct(public string $joinNodeId, public array $outputs)
    {
    }

    public function jsonSerialize(): array
    {
        return ['joinNodeId' => $this->joinNodeId, 'outputs' => $this->outputs];
    }
}

/**
 * @internal
 */
final readonly class ParallelJoinConfiguration implements NodeConfiguration
{
    public function __construct(public int $minimum)
    {
    }

    public function jsonSerialize(): array
    {
        return ['minimum' => $this->minimum];
    }
}

/**
 * @internal
 */
class ParallelForkExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return 'example:fork';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): OpenScopeEffect
    {
        if (!$configuration instanceof ParallelForkConfiguration) {
            throw new \InvalidArgumentException('Expected fork configuration.');
        }

        return new OpenScopeEffect($configuration->outputs, $configuration->joinNodeId);
    }
}

/**
 * @internal
 */
class ParallelJoinExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return 'example:join';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect|WaitEffect
    {
        if (!$configuration instanceof ParallelJoinConfiguration || $context->scope === null) {
            throw new \InvalidArgumentException('A join requires its configuration and runtime scope snapshot.');
        }
        $arrived = \count(array_filter($context->scope->branches, static fn (ApprovalExecutionBranchStatus $status): bool => $status === ApprovalExecutionBranchStatus::Arrived));
        $required = $configuration->minimum === 0 ? \count($context->scope->branches) : $configuration->minimum;

        return $arrived >= $required ? new AdvanceEffect() : new WaitEffect();
    }
}
