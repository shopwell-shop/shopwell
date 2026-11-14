<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Definition\Type\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Event\WorkflowNodePortsEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowNodeTypeResolver::class)]
#[CoversClass(WorkflowNodePortsEvent::class)]
class WorkflowNodeTypeResolverTest extends TestCase
{
    public function testEachInstanceCanResolveItsOwnConfigurableBranches(): void
    {
        $loader = static::createStub(WorkflowNodeTypeLoader::class);
        $loader->method('load')->willReturn([new WorkflowNodeTypeSpecification('example:condition', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['fallback'])]);
        $types = new WorkflowNodeTypeRegistry([$loader]);
        $events = new EventDispatcher();
        $events->addListener(WorkflowNodePortsEvent::class, static function (WorkflowNodePortsEvent $event): void {
            if (\is_array($event->node->config) && ($event->node->config['hasExtraBranch'] ?? false) === true) {
                $event->setPorts(['in'], ['match', 'fallback']);
            }
        });
        $resolver = new WorkflowNodeTypeResolver($types, $events);
        $first = $resolver->resolve(new WorkflowNode('first', 'example:condition', 1, ['hasExtraBranch' => true]));
        $second = $resolver->resolve(new WorkflowNode('second', 'example:condition', 1));
        static::assertNotNull($first);
        static::assertNotNull($second);
        static::assertSame(['match', 'fallback'], $first->outputPorts);
        static::assertSame(['fallback'], $second->outputPorts);
        static::assertNull($resolver->resolve(new WorkflowNode('unknown', 'example:unknown', 1)));
    }
}
